<?php

namespace Tests\Feature\Observers;

use App\Jobs\CalulcateUserNameIndexProcess;
use App\Jobs\GroupDayUpdatedProcess;
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
use App\Notifications\EventStatusChangedNotification;
use App\Notifications\UserRoleIsGroupCreatorNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

class ObserverRegressionTest extends FeatureTestCase
{
    public function test_user_observer_dispatches_index_job_and_role_notification(): void
    {
        Bus::fake();
        Notification::fake();

        $admin = $this->createUser([
            'role' => 'mainAdmin',
            'email' => 'existing-admin@example.test',
        ]);
        $this->actingAs($admin);

        $user = User::factory()->create([
            'email' => 'observer-user@example.test',
            'role' => 'registered',
        ]);

        Bus::assertDispatched(CalulcateUserNameIndexProcess::class);

        $user->role = 'groupCreator';
        $user->save();

        Notification::assertSentTo($user, UserRoleIsGroupCreatorNotification::class);
    }

    public function test_event_observer_logs_and_sends_notifications(): void
    {
        Notification::fake();

        $creator = $this->createUser(['email' => 'event-creator@example.test']);
        $assignee = $this->createUser(['email' => 'event-assignee@example.test']);
        $group = $this->createGroup();

        $this->attachUserToGroup($creator, $group, 'roler', true);
        $this->attachUserToGroup($assignee, $group, 'member', true);

        $this->actingAs($creator);

        $event = Event::factory()->create([
            'group_id' => $group->id,
            'user_id' => $assignee->id,
            'status' => 0,
        ]);

        $this->assertDatabaseHas('log_histories', [
            'event' => 'created',
            'model_type' => Event::class,
            'model_id' => $event->id,
        ]);
        Notification::assertSentTo($assignee, EventCreatedNotification::class);

        $event->status = 1;
        $event->save();

        Notification::assertSentTo($assignee, EventStatusChangedNotification::class);
    }

    public function test_group_observer_creates_log_history_on_update(): void
    {
        $user = $this->createUser(['email' => 'group-observer@example.test']);
        $group = $this->createGroup();

        $this->actingAs($user);
        $group->max_extend_days = 60;
        $group->save();

        $this->assertDatabaseHas('log_histories', [
            'event' => 'updated',
            'model_type' => Group::class,
            'model_id' => $group->id,
        ]);
    }

    public function test_group_user_observer_logs_update_and_delete(): void
    {
        $user = $this->createUser(['email' => 'group-user-observer@example.test']);
        $group = $this->createGroup();

        $this->actingAs($user);

        $membership = GroupUser::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'group_role' => 'member',
            'accepted_at' => now(),
        ]);

        $membership->group_role = 'roler';
        $membership->save();
        $membership->delete();

        $this->assertGreaterThanOrEqual(
            2,
            LogHistory::where('model_type', GroupUser::class)->where('model_id', $membership->id)->count()
        );
    }

    public function test_group_literature_observer_logs_lifecycle_events(): void
    {
        $user = $this->createUser(['email' => 'literature-observer@example.test']);
        $group = $this->createGroup();

        $this->actingAs($user);

        $literature = new GroupLiterature();
        $literature->group_id = $group->id;
        $literature->name = 'Magazine';
        $literature->save();

        $literature->name = 'Book';
        $literature->save();
        $literature->delete();

        $this->assertGreaterThanOrEqual(
            3,
            LogHistory::where('model_type', GroupLiterature::class)->where('model_id', $literature->id)->count()
        );
    }

    public function test_group_news_observer_and_translation_observer_log_events(): void
    {
        $user = $this->createUser(['email' => 'news-observer@example.test']);
        $group = $this->createGroup();

        $this->actingAs($user);

        $news = $this->createGroupNews($group, $user);
        $news->status = 0;
        $news->save();

        $translation = GroupNewsTranslation::where('group_news_id', $news->id)
            ->where('locale', config('app.locale', 'hu'))
            ->firstOrFail();

        $translation->title = 'Uj cim';
        $translation->save();

        $this->assertDatabaseHas('log_histories', [
            'event' => 'updated',
            'model_type' => GroupNews::class,
            'model_id' => $news->id,
        ]);

        $this->assertDatabaseHas('log_histories', [
            'event' => 'updated',
            'model_type' => GroupNewsTranslation::class,
            'model_id' => $translation->id,
        ]);
    }

    public function test_group_day_observer_is_inactive_by_default(): void
    {
        Bus::fake();

        $user = $this->createUser(['email' => 'group-day-observer@example.test']);
        $group = $this->createGroup();

        $this->actingAs($user);

        $groupDay = GroupDay::factory()->create([
            'group_id' => $group->id,
            'day_number' => 2,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $groupDay->start_time = '11:00';
        $groupDay->save();

        Bus::assertNotDispatched(GroupDayUpdatedProcess::class);
        $this->assertDatabaseMissing('log_histories', [
            'model_type' => GroupDay::class,
            'model_id' => $groupDay->id,
        ]);
    }
}
