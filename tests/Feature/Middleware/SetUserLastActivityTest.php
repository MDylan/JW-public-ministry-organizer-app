<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 09: a SetUserLastActivity middleware.
 *
 * A web csoport tagja (Kernel.php:46), tehát MINDEN webes kérésen lefut, és
 * a users.last_activity mezőt tartja karban. Erre a mezőre épül a
 * Groups\ListUsers "online" és "inaktív" szűrője, valamint a GDPR
 * inaktivitás-alapú anonimizálása - ha csendben leáll, mindkettő téves
 * eredményt ad, hibaüzenet nélkül.
 *
 * Ma nulla lefedettsége van.
 */
class SetUserLastActivityTest extends FeatureTestCase
{
    private function homeUrl(): string
    {
        return route('static_page', ['slug' => 'home']);
    }

    /**
     * A last_activity nincs a User $casts-jában, ezért nyersen olvassuk -
     * így az is látszik, ha az érték egyáltalán nem változott.
     */
    private function lastActivityOf(User $user): ?string
    {
        return DB::table('users')->where('id', $user->id)->value('last_activity');
    }

    private function userWithLastActivity(?Carbon $when, string $email): User
    {
        $user = $this->createUser(['email' => $email]);

        DB::table('users')->where('id', $user->id)->update([
            'last_activity' => $when?->toDateTimeString(),
        ]);

        return $user->fresh();
    }

    // =========================================================================
    // 1. Ki kap írást egyáltalán
    // =========================================================================

    public function test_a_guest_request_writes_nothing(): void
    {
        // A teljes törzs Auth::check() mögött van, tehát a nyilvános
        // oldalak forgalma nem terheli az adatbázist.
        $user = $this->userWithLastActivity(null, 'activity-guest@example.test');

        $this->get($this->homeUrl())->assertStatus(200);

        $this->assertNull($this->lastActivityOf($user));
    }

    public function test_a_null_last_activity_is_filled_in_on_the_first_request(): void
    {
        // A feltétel második fele (last_activity == null) fedi le ezt az
        // esetet: a diffInSeconds(null) nullát ad, tehát az első fele nem
        // teljesülne.
        $user = $this->userWithLastActivity(null, 'activity-null@example.test');

        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);

        $this->assertNotNull($this->lastActivityOf($user));
    }

    // =========================================================================
    // 2. A 60 másodperces küszöb
    // =========================================================================

    public function test_a_recent_activity_is_not_rewritten(): void
    {
        // A takarékossági szabály: 60 másodpercnél frissebb bejegyzést nem
        // írunk felül, hogy ne legyen írás minden egyes kérésnél.
        $recent = now()->subSeconds(10);
        $user = $this->userWithLastActivity($recent, 'activity-recent@example.test');

        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);

        $this->assertSame($recent->toDateTimeString(), $this->lastActivityOf($user));
    }

    public function test_an_old_activity_is_refreshed(): void
    {
        // EZ A TESZT BUKIK EL A CARBON 3 ALATT.
        //
        // A feltétel: Carbon::now()->diffInSeconds($user->last_activity) >= 60.
        // A jelenlegi Carbon 2.57-ben a diffIn* ABSZOLÚT értéket ad, tehát egy
        // múltbeli időpontra pozitív számot - a feltétel teljesül.
        //
        // A Carbon 3-ban a diffIn* ELŐJELES lett: ugyanez a hívás negatívat
        // adna, a >= 60 sosem teljesülne, és a last_activity SOHA többé nem
        // frissülne - csendben, hibaüzenet nélkül. Ellenőrizendő a Laravel 11
        // hopnál, ahol a Carbon 3 megjelenik.
        $old = now()->subMinutes(2);
        $user = $this->userWithLastActivity($old, 'activity-old@example.test');

        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);

        $stored = $this->lastActivityOf($user);

        $this->assertNotSame($old->toDateTimeString(), $stored, 'A régi bejegyzést felül kell írni.');
        $this->assertTrue(Carbon::parse($stored)->greaterThan($old));
    }

    public function test_the_threshold_itself_triggers_a_refresh(): void
    {
        // A feltétel >=, tehát a pontosan 60 másodperces bejegyzés még
        // frissül. A határérték a küszöb alatt van, nem fölötte.
        $threshold = now()->subSeconds(60);
        $user = $this->userWithLastActivity($threshold, 'activity-threshold@example.test');

        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);

        $this->assertNotSame($threshold->toDateTimeString(), $this->lastActivityOf($user));
    }

    // =========================================================================
    // 3. Az írás mellékhatásai
    // =========================================================================

    public function test_the_write_also_bumps_the_updated_at_column(): void
    {
        // A tömeges User::where()->update() az Eloquent Builderen megy át,
        // ami automatikusan hozzáfűzi az updated_at-et. Vagyis minden aktív
        // felhasználó users sora percenként "módosul" - amit érdemes tudni,
        // ha valaha bármi az updated_at-re épül (szinkronizálás, cache-kulcs,
        // riport).
        $user = $this->userWithLastActivity(now()->subMinutes(5), 'activity-touch@example.test');

        DB::table('users')->where('id', $user->id)->update([
            'updated_at' => now()->subDays(3)->toDateTimeString(),
        ]);

        $this->actingAs($user->fresh())->get($this->homeUrl())->assertStatus(200);

        $updatedAt = DB::table('users')->where('id', $user->id)->value('updated_at');

        $this->assertTrue(Carbon::parse($updatedAt)->greaterThan(now()->subMinute()));
    }

    public function test_the_write_bypasses_the_model_events(): void
    {
        // A tömeges update nem indít Eloquent eseményt, tehát a UserObserver
        // sem fut - a name_index-újraszámoló job (amit egy sima $user->save()
        // minden alkalommal elindítana) itt elmarad. Ez szándékos és fontos:
        // enélkül minden kérés egy teljes felhasználó-újraszámozást indítana.
        $user = $this->userWithLastActivity(now()->subMinutes(5), 'activity-events@example.test');

        DB::table('users')->where('id', $user->id)->update(['name_index' => 4242]);

        $this->actingAs($user->fresh())->get($this->homeUrl())->assertStatus(200);

        $this->assertSame(
            4242,
            (int) DB::table('users')->where('id', $user->id)->value('name_index'),
            'A name_index érintetlen: nem futott observer.'
        );
    }

    public function test_only_the_authenticated_user_is_touched(): void
    {
        $active = $this->userWithLastActivity(now()->subMinutes(5), 'activity-active@example.test');
        $idle = $this->userWithLastActivity(now()->subMinutes(5), 'activity-idle@example.test');
        $idleBefore = $this->lastActivityOf($idle);

        $this->actingAs($active)->get($this->homeUrl())->assertStatus(200);

        $this->assertSame($idleBefore, $this->lastActivityOf($idle));
    }
}
