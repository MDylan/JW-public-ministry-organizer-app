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
 * TODO 10: the "who caused the change" contract.
 *
 * Observers run on model events, and those can be triggered not only by an
 * HTTP request: a scheduled command, a queue worker, the console, or a seeder
 * can also write a model. Previously THREE DIFFERENT behaviors coexisted for
 * this situation - in some places the log entry was missing, in others a 0
 * was inserted, and in eight places auth()->user()->id simply produced a fatal.
 *
 * TODO 10 unified this: causer_id = 0 means "caused by the system".
 * This file is the regression protection for that unification.
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
    // 1. Writing events without being logged in does not fail
    // =========================================================================

    public function test_an_event_can_be_created_updated_and_deleted_without_an_authenticated_user(): void
    {
        // EventObserver::created() previously read auth()->user()->id
        // UNGUARDED, so any event creation started from a queue worker or the
        // console produced a fatal. Our fixtures were forced into actingAs()
        // because of this (see the findings of TODO 04).
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
        // causerId() returns 0, which never matches a real user_id, so the
        // notification goes out - and causerName() supplies the name.
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
        // BEHAVIOR CHANGE, deliberate: EventObserver::deleted() previously
        // returned false for the userName field on a system delete, which
        // appeared EMPTY in the email. Now it is "SYSTEM", the same as what
        // updated() has long used.
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
     * The notification's $data property is private, so we read it via
     * reflection - the same pattern that NotificationRegressionTest also uses.
     */
    private function notificationPayload($notification): array
    {
        $property = new \ReflectionProperty($notification, 'data');
        $property->setAccessible(true);

        return $property->getValue($notification);
    }

    // =========================================================================
    // 2. Deleting a group - regression protection for the group_id bug
    // =========================================================================

    public function test_deleting_a_group_writes_the_correct_group_id(): void
    {
        // GroupObserver::deleted() previously read $group->group_id, which
        // does not exist on the Group model - null always went into the
        // NOT NULL column, and every Eloquent delete failed.
        $group = $this->createGroup();

        $group->delete();

        $history = $this->latestHistory(Group::class, 'deleted');

        $this->assertSame($group->id, $history->group_id);
        $this->assertSame($group->id, $history->model_id);
        $this->assertSame(0, $history->causer_id);
    }

    public function test_group_updates_are_now_logged_even_without_an_authenticated_user(): void
    {
        // BEHAVIOR CHANGE, deliberate: GroupObserver::updated() previously sat
        // behind an && (auth()->user() !== null) condition, so scheduled
        // group modifications happened without a trace. For the sake of a
        // more complete audit trail, this condition was removed.
        $this->group->update(['max_publishers' => 9]);

        $history = $this->latestHistory(Group::class, 'updated');

        $this->assertNotNull($history, 'A rendszer okozta módosítás is naplóba kerül.');
        $this->assertSame(0, $history->causer_id);
        $this->assertSame($this->group->id, $history->group_id);
    }

    public function test_membership_changes_are_logged_without_an_authenticated_user(): void
    {
        // Same for GroupUserObserver::updated().
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
    // 3. The content observers
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
    // 4. GroupDayObserver deliberately remains disabled
    // =========================================================================

    public function test_the_group_day_observer_is_still_not_registered(): void
    {
        // GroupDayObserver did get the causer handling, but it is NOT
        // registered in EventServiceProvider.
        //
        // This is a deliberate decision (TODO 10.1). The TODO 10 note still
        // said that enabling it would fill in a missing capability; this was
        // mistaken. The cleanup after narrowing the day template still runs
        // today, just via the GroupDateHelper -> CalculateDateProcess ->
        // CalculateDatesEvents chain - the same engine that
        // GroupDayUpdatedProcess would also call. The observer and its two
        // jobs are therefore a REPLACED implementation: enabling them would
        // not add a new capability, it would just run the same thing a second time.
        //
        // The other side of this is held by
        // tests/Feature/Groups/GroupDayTemplateCleanupTest.php: it proves
        // that the cleanup happens even without the observer.
        //
        // If someone registers it, this test must be the first to fail.
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
