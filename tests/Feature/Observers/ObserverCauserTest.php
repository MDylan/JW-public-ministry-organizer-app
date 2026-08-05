<?php

namespace Tests\Feature\Observers;

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDay;
use App\Models\GroupLiterature;
use App\Models\GroupNews;
use App\Models\GroupNewsTranslation;
use App\Models\GroupUser;
use App\Models\LogHistory;
use App\Models\User;
use App\Notifications\EventCreatedNotification;
use App\Notifications\EventDeletedNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 10: a "ki okozta a változást" szerződés.
 *
 * Az observerek modell-eseményekre futnak, azok pedig nem csak HTTP-kérésből
 * indulhatnak: ütemezett parancs, sorkezelő, konzol vagy seeder is írhat
 * modellt. Korábban HÁROMFÉLE viselkedés élt egymás mellett erre a
 * helyzetre - volt, ahol kimaradt a naplóbejegyzés, volt, ahol 0 került
 * bele, és nyolc helyen az auth()->user()->id egyszerűen fatalt adott.
 *
 * A TODO 10 egységesítette: causer_id = 0 jelentése "a rendszer okozta".
 * Ez a fájl az egységesítés regressziós védelme.
 */
class ObserverCauserTest extends FeatureTestCase
{
    private Group $group;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->member = $this->createUser(['email' => 'causer-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group, 'member');
    }

    private function latestHistory(string $modelType, string $event): ?LogHistory
    {
        return LogHistory::where('model_type', $modelType)
            ->where('event', $event)
            ->orderByDesc('id')
            ->first();
    }

    private function makeEvent(): Event
    {
        return Event::factory()
            ->forGroup($this->group)
            ->forUser($this->member)
            ->accepted()
            ->create([
                'day'   => now()->addDay()->toDateString(),
                'start' => now()->addDay()->toDateString().' 09:00:00',
                'end'   => now()->addDay()->toDateString().' 11:00:00',
            ]);
    }

    // =========================================================================
    // 1. Az események írása bejelentkezés nélkül nem hasal el
    // =========================================================================

    public function test_an_event_can_be_created_updated_and_deleted_without_an_authenticated_user(): void
    {
        // Az EventObserver::created() korábban ŐRIZETLENÜL olvasta az
        // auth()->user()->id-t, tehát bármely sorkezelőből vagy konzolról
        // indított esemény-létrehozás fatalt adott. A fixtúráink emiatt
        // kényszerültek actingAs()-re (lásd TODO 04 tanulságai).
        Notification::fake();

        $this->assertGuest();

        $event = $this->makeEvent();
        $this->assertSame(0, $this->latestHistory(Event::class, 'created')->causer_id);

        $event->update(['comment' => 'rendszer által módosítva']);
        $this->assertSame(0, $this->latestHistory(Event::class, 'updated')->causer_id);

        $event->delete();
        $this->assertSame(0, $this->latestHistory(Event::class, 'deleted')->causer_id);
    }

    public function test_an_authenticated_user_is_recorded_as_the_causer(): void
    {
        Notification::fake();

        $actor = $this->createUser(['email' => 'causer-actor@example.test']);
        $this->actingAs($actor);

        $this->makeEvent();

        $this->assertSame($actor->id, $this->latestHistory(Event::class, 'created')->causer_id);
    }

    public function test_a_system_created_event_still_notifies_its_owner_with_the_system_name(): void
    {
        // A causerId() 0-t ad, ami sosem egyezik meg egy valódi user_id-val,
        // tehát az értesítés kimegy - és a nevet a causerName() adja.
        Notification::fake();

        $this->makeEvent();

        Notification::assertSentTo(
            $this->member,
            EventCreatedNotification::class,
            function ($notification) {
                return $this->notificationPayload($notification)['userName'] === 'SYSTEM';
            }
        );
    }

    public function test_a_system_deleted_event_reports_system_instead_of_an_empty_name(): void
    {
        // VISELKEDÉSVÁLTOZÁS, szándékos: az EventObserver::deleted() korábban
        // false-t adott a userName mezőnek rendszer-törléskor, ami ÜRESEN
        // jelent meg a levélben. Most "SYSTEM", ugyanaz, amit az updated()
        // már régóta használ.
        Notification::fake();

        $this->makeEvent()->delete();

        Notification::assertSentTo(
            $this->member,
            EventDeletedNotification::class,
            function ($notification) {
                return $this->notificationPayload($notification)['userName'] === 'SYSTEM';
            }
        );
    }

    /**
     * A notification $data property-je privát, ezért reflexióval olvassuk -
     * ugyanaz a minta, amit a NotificationRegressionTest is használ.
     */
    private function notificationPayload($notification): array
    {
        $property = new \ReflectionProperty($notification, 'data');
        $property->setAccessible(true);

        return $property->getValue($notification);
    }

    // =========================================================================
    // 2. A csoport törlése - a group_id hiba regressziós védelme
    // =========================================================================

    public function test_deleting_a_group_writes_the_correct_group_id(): void
    {
        // A GroupObserver::deleted() korábban $group->group_id-t olvasott,
        // ami a Group modellen nem létezik - mindig null került a NOT NULL
        // oszlopba, és minden Eloquent-törlés elszállt.
        $group = $this->createGroup();

        $group->delete();

        $history = $this->latestHistory(Group::class, 'deleted');

        $this->assertSame($group->id, $history->group_id);
        $this->assertSame($group->id, $history->model_id);
        $this->assertSame(0, $history->causer_id);
    }

    public function test_group_updates_are_now_logged_even_without_an_authenticated_user(): void
    {
        // VISELKEDÉSVÁLTOZÁS, szándékos: a GroupObserver::updated() korábban
        // egy && (auth()->user() !== null) feltétel mögött volt, tehát az
        // ütemezett csoportmódosítások nyomtalanul történtek. A teljesebb
        // audit trail kedvéért ez a feltétel kikerült.
        $this->group->update(['max_publishers' => 9]);

        $history = $this->latestHistory(Group::class, 'updated');

        $this->assertNotNull($history, 'A rendszer okozta módosítás is naplóba kerül.');
        $this->assertSame(0, $history->causer_id);
        $this->assertSame($this->group->id, $history->group_id);
    }

    public function test_membership_changes_are_logged_without_an_authenticated_user(): void
    {
        // Ugyanez a GroupUserObserver::updated()-re.
        $pivot = GroupUser::where('group_id', $this->group->id)
            ->where('user_id', $this->member->id)
            ->firstOrFail();

        $pivot->update(['group_role' => 'roler']);

        $updated = $this->latestHistory(GroupUser::class, 'updated');
        $this->assertNotNull($updated);
        $this->assertSame(0, $updated->causer_id);

        $pivot->delete();

        $deleted = $this->latestHistory(GroupUser::class, 'deleted');
        $this->assertNotNull($deleted);
        $this->assertSame(0, $deleted->causer_id);
    }

    // =========================================================================
    // 3. A tartalmi observerek
    // =========================================================================

    public function test_literature_lifecycle_is_logged_without_an_authenticated_user(): void
    {
        $literature = GroupLiterature::factory()->forGroup($this->group)->create();

        $this->assertSame(0, $this->latestHistory(GroupLiterature::class, 'created')->causer_id);

        $literature->update(['name' => 'Átnevezett kiadvány']);
        $this->assertSame(0, $this->latestHistory(GroupLiterature::class, 'updated')->causer_id);

        $literature->delete();
        $this->assertSame(0, $this->latestHistory(GroupLiterature::class, 'deleted')->causer_id);
    }

    public function test_news_lifecycle_is_logged_without_an_authenticated_user(): void
    {
        $news = GroupNews::factory()->create([
            'group_id' => $this->group->id,
            'user_id'  => $this->member->id,
        ]);

        $news->update(['status' => 0]);
        $this->assertSame(0, $this->latestHistory(GroupNews::class, 'updated')->causer_id);

        $news->delete();
        $this->assertSame(0, $this->latestHistory(GroupNews::class, 'deleted')->causer_id);
    }

    public function test_news_translation_lifecycle_is_logged_without_an_authenticated_user(): void
    {
        $news = GroupNews::factory()->create([
            'group_id' => $this->group->id,
            'user_id'  => $this->member->id,
        ]);

        $translation = GroupNewsTranslation::factory()->forNews($news)->create();

        $this->assertSame(0, $this->latestHistory(GroupNewsTranslation::class, 'created')->causer_id);

        $translation->update(['title' => 'Új cím']);
        $this->assertSame(0, $this->latestHistory(GroupNewsTranslation::class, 'updated')->causer_id);

        $translation->delete();
        $this->assertSame(0, $this->latestHistory(GroupNewsTranslation::class, 'deleted')->causer_id);
    }

    // =========================================================================
    // 4. A GroupDayObserver szándékosan kikapcsolva marad
    // =========================================================================

    public function test_the_group_day_observer_is_still_not_registered(): void
    {
        // A GroupDayObserver megkapta ugyan a causer-kezelést (hogy a későbbi
        // bekapcsolás ne az ütemező elhasalásával kezdődjön), de NINCS
        // regisztrálva az EventServiceProvider-ben.
        //
        // Ez tudatos döntés: az általa indított GroupDayUpdatedProcess és
        // GroupDayDeletedProcess a KÉT JOB EGYETLEN indítója, és a
        // bekapcsolásuk elkezdené törölni a felhasználók már felvett
        // jövőbeli eseményeit, ha egy admin szűkíti a csoport napsablonját.
        //
        // Ha valaki regisztrálja, ennek a tesztnek kell elsőként elbuknia.
        Bus::fake();

        $day = GroupDay::factory()->create(['group_id' => $this->group->id]);
        $day->update(['start_time' => '09:00']);
        $day->delete();

        $this->assertSame(
            0,
            LogHistory::where('model_type', GroupDay::class)->count(),
            'Regisztrálatlan observer nem ír naplót.'
        );
        Bus::assertNothingDispatched();
    }
}
