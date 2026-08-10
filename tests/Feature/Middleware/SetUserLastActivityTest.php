<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 09: the SetUserLastActivity middleware.
 *
 * It is a member of the web group (Kernel.php:46), so it runs on EVERY web
 * request, and it maintains the users.last_activity field. This field
 * underlies Groups\ListUsers's "online" and "inactive" filters, as well as
 * GDPR's inactivity-based anonymization - if it silently stops working, both
 * give wrong results, without an error message.
 *
 * It currently has zero coverage.
 */
class SetUserLastActivityTest extends FeatureTestCase
{
    private function homeUrl(): string
    {
        return route('static_page', ['slug' => 'home']);
    }

    /**
     * last_activity is not in User's $casts, so we read it raw - this way it
     * is also visible if the value did not change at all.
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
    // 1. Who gets a write at all
    // =========================================================================

    public function test_a_guest_request_writes_nothing(): void
    {
        // The entire body sits behind Auth::check(), so public page traffic
        // does not load the database.
        $user = $this->userWithLastActivity(null, 'activity-guest@example.test');

        $this->get($this->homeUrl())->assertStatus(200);

        $this->assertNull($this->lastActivityOf($user));
    }

    public function test_a_null_last_activity_is_filled_in_on_the_first_request(): void
    {
        // The second half of the condition (last_activity == null) covers
        // this case: diffInSeconds(null) returns zero, so the first half
        // would not be satisfied.
        $user = $this->userWithLastActivity(null, 'activity-null@example.test');

        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);

        $this->assertNotNull($this->lastActivityOf($user));
    }

    // =========================================================================
    // 2. The 60-second threshold
    // =========================================================================

    public function test_a_recent_activity_is_not_rewritten(): void
    {
        // The economy rule: we do not overwrite an entry fresher than 60
        // seconds, so there is not a write on every single request.
        $recent = now()->subSeconds(10);
        $user = $this->userWithLastActivity($recent, 'activity-recent@example.test');

        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);

        $this->assertSame($recent->toDateTimeString(), $this->lastActivityOf($user));
    }

    public function test_an_old_activity_is_refreshed(): void
    {
        // THIS TEST FAILS UNDER CARBON 3.
        //
        // The condition: Carbon::now()->diffInSeconds($user->last_activity) >= 60.
        // In the current Carbon 2.57, diffIn* returns an ABSOLUTE value, so a
        // positive number for a past timestamp - the condition is satisfied.
        //
        // In Carbon 3, diffIn* became SIGNED: the same call would return a
        // negative number, the >= 60 would never be satisfied, and
        // last_activity would NEVER refresh again - silently, without an
        // error message. To be checked at the Laravel 11 hop, where Carbon 3
        // appears.
        $old = now()->subMinutes(2);
        $user = $this->userWithLastActivity($old, 'activity-old@example.test');

        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);

        $stored = $this->lastActivityOf($user);

        $this->assertNotSame($old->toDateTimeString(), $stored, 'A régi bejegyzést felül kell írni.');
        $this->assertTrue(Carbon::parse($stored)->greaterThan($old));
    }

    public function test_the_threshold_itself_triggers_a_refresh(): void
    {
        // The condition is >=, so an entry that is exactly 60 seconds old
        // still gets refreshed. The boundary value is at the threshold, not above it.
        $threshold = now()->subSeconds(60);
        $user = $this->userWithLastActivity($threshold, 'activity-threshold@example.test');

        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);

        $this->assertNotSame($threshold->toDateTimeString(), $this->lastActivityOf($user));
    }

    // =========================================================================
    // 3. Side effects of the write
    // =========================================================================

    public function test_the_write_also_bumps_the_updated_at_column(): void
    {
        // The bulk User::where()->update() goes through the Eloquent
        // Builder, which automatically appends updated_at. In other words,
        // every active user's users row "changes" every minute - worth
        // knowing if anything ever relies on updated_at (syncing, a cache
        // key, a report).
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
        // The bulk update does not fire an Eloquent event, so UserObserver
        // does not run either - the name_index recalculation job (which a
        // plain $user->save() would trigger every time) is skipped here.
        // This is deliberate and important: without it, every request would
        // trigger a full user recalculation.
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
