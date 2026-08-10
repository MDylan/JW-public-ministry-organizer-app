<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DeleteGroupDataProcess;
use App\Models\DayStat;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupDayDisabledSlots;
use App\Models\GroupFutureChange;
use App\Models\GroupLiterature;
use App\Models\GroupMessage;
use App\Models\GroupNews;
use App\Models\GroupNewsUserLogs;
use App\Models\GroupPosters;
use App\Models\GroupSurvey;
use App\Models\GroupUser;
use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 05: real handle() execution.
 *
 * Dispatched from GroupDelete when a group is removed. It wipes every
 * group-scoped table and optionally anonymizes members who belong to no other
 * group. This is the most destructive job in the codebase and had zero
 * coverage before.
 */
class DeleteGroupDataProcessTest extends FeatureTestCase
{
    private Group $group;
    private Group $otherGroup;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->otherGroup = $this->createGroup();
        $this->member = $this->createUser(['email' => 'delete-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group);
        $this->actingAs($this->member);
    }

    /**
     * The GroupDelete controller uses a mass delete
     * ($user->userGroupsDeletable()->where(...)->delete()), which runs on the
     * query builder and therefore does NOT fire a model event. This matters
     * here: GroupObserver::deleted() reads the non-existent $group->group_id
     * field (it should be $group->id), so on an Eloquent delete
     * LogHistory.group_id would be null and the whole operation would fail.
     * See the characterization test below.
     */
    private function softDeleteGroupLikeTheController(Group $group): void
    {
        Group::where('id', $group->id)->delete();
    }

    private function seedGroupData(Group $group): void
    {
        $date = now()->addDay()->toDateString();

        GroupDate::factory()->create(['group_id' => $group->id, 'date' => $date]);
        Event::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->member->id,
            'day' => $date,
            'start' => $date.' 09:00:00',
            'end' => $date.' 10:00:00',
        ]);
        DayStat::factory()->create(['group_id' => $group->id, 'day' => $date, 'time_slot' => $date.' 09:00:00']);
        GroupDayDisabledSlots::factory()->create(['group_id' => $group->id]);
        GroupLiterature::factory()->create(['group_id' => $group->id]);
        GroupNews::factory()->create(['group_id' => $group->id, 'user_id' => $this->member->id]);
        GroupNewsUserLogs::factory()->create(['group_id' => $group->id, 'user_id' => $this->member->id]);
        GroupPosters::factory()->create(['group_id' => $group->id]);
        GroupSurvey::factory()->create(['group_id' => $group->id]);
        GroupMessage::factory()->create(['group_id' => $group->id, 'user_id' => $this->member->id]);
        GroupFutureChange::factory()->create(['group_id' => $group->id, 'user_id' => $this->member->id]);
    }

    public function test_handle_removes_every_group_scoped_record(): void
    {
        $this->seedGroupData($this->group);

        (new DeleteGroupDataProcess($this->group->id, false))->handle();

        $scoped = [
            Event::class, GroupDate::class, DayStat::class, GroupDayDisabledSlots::class,
            GroupLiterature::class, GroupNews::class, GroupNewsUserLogs::class,
            GroupPosters::class, GroupSurvey::class, GroupMessage::class, GroupFutureChange::class,
        ];

        foreach ($scoped as $model) {
            $this->assertSame(
                0,
                $model::where('group_id', $this->group->id)->count(),
                $model.' rows survived the group deletion.'
            );
        }
    }

    public function test_handle_leaves_other_groups_data_intact(): void
    {
        $this->seedGroupData($this->group);
        $this->attachUserToGroup($this->member, $this->otherGroup);
        $this->seedGroupData($this->otherGroup);

        (new DeleteGroupDataProcess($this->group->id, false))->handle();

        $this->assertSame(1, GroupDate::where('group_id', $this->otherGroup->id)->count());
        $this->assertSame(1, GroupPosters::where('group_id', $this->otherGroup->id)->count());
        $this->assertSame(1, GroupMessage::where('group_id', $this->otherGroup->id)->count());
    }

    public function test_handle_detaches_the_memberships(): void
    {
        (new DeleteGroupDataProcess($this->group->id, false))->handle();

        $this->assertSame(0, GroupUser::where('group_id', $this->group->id)->count());
    }

    public function test_handle_keeps_the_user_when_not_asked_to_delete_users(): void
    {
        (new DeleteGroupDataProcess($this->group->id, false))->handle();

        $fresh = User::find($this->member->id);
        $this->assertNotNull($fresh);
        $this->assertSame('delete-member@example.test', $fresh->email);
    }

    public function test_handle_anonymizes_a_member_who_has_no_other_group(): void
    {
        // Before v1-patch B10 this test was only green because we
        // soft-deleted the group beforehand: the anonymization condition
        // (count($user->user->userGroups) == 0) depended on the relation
        // that joins the groups table. Now the job itself excludes its own
        // group, so the order no longer matters - the soft-delete here just
        // mimics the live GroupDelete path.
        $this->softDeleteGroupLikeTheController($this->group);

        (new DeleteGroupDataProcess($this->group->id, true))->handle();

        $fresh = User::find($this->member->id);

        $this->assertNotNull($fresh, 'The user row itself must survive; only its data is anonymized.');
        $this->assertNotSame('delete-member@example.test', $fresh->email);
    }

    public function test_handle_does_not_anonymize_a_member_who_still_belongs_to_another_group(): void
    {
        $this->attachUserToGroup($this->member, $this->otherGroup);
        $this->softDeleteGroupLikeTheController($this->group);

        (new DeleteGroupDataProcess($this->group->id, true))->handle();

        $this->assertSame('delete-member@example.test', User::find($this->member->id)->email);
    }

    public function test_handle_anonymizes_even_while_the_group_is_still_live(): void
    {
        // REVERSED by the v1-patch B10 fix.
        //
        // The job was silently order-dependent: it only anonymized if the
        // group was ALREADY soft-deleted by the time it ran, because the
        // userGroups relation joins the groups table, and the deleted group
        // dropped out of it. The live GroupDelete path deletes first, then
        // dispatches, so it worked - but calling the job on a live group
        // (manual run, a different caller, changed order) made the
        // anonymization silently no-op, with no error message.
        //
        // The condition is now explicit: we exclude the job's own group from
        // the count.
        (new DeleteGroupDataProcess($this->group->id, true))->handle();

        $fresh = User::find($this->member->id);

        $this->assertNotNull($fresh, 'A felhasználó sora megmarad, csak az adatai tűnnek el.');
        $this->assertNotSame('delete-member@example.test', $fresh->email);
    }

    public function test_handle_still_spares_a_member_of_another_live_group(): void
    {
        // B10's control experiment: the broadened condition must not
        // anonymize someone who has another group - regardless of whether
        // the group currently being deleted is still live.
        $this->attachUserToGroup($this->member, $this->otherGroup);

        (new DeleteGroupDataProcess($this->group->id, true))->handle();

        $this->assertSame('delete-member@example.test', User::find($this->member->id)->email);
    }

    public function test_handle_is_safe_to_run_for_a_group_with_no_data(): void
    {
        $empty = $this->createGroup();

        (new DeleteGroupDataProcess($empty->id, true))->handle();

        $this->assertSame(0, GroupUser::where('group_id', $empty->id)->count());
    }

    public function test_deleting_a_group_through_eloquent_writes_a_history_record(): void
    {
        // TODO 10 fixed GroupObserver::deleted(): previously it read the
        // non-existent $group->group_id field on the Group model, so null
        // always went into the NOT NULL log_histories.group_id column, and
        // EVERY Eloquent delete failed. It was not visible in production
        // only because GroupDelete uses a mass delete, which does not fire
        // an event.
        //
        // This test is now the regression guard for the fixed behaviour.
        $group = $this->createGroup();

        $group->delete();

        $this->assertSoftDeleted('groups', ['id' => $group->id]);
        $this->assertDatabaseHas('log_histories', [
            'event'      => 'deleted',
            'group_id'   => $group->id,
            'model_id'   => $group->id,
            'model_type' => Group::class,
        ]);
    }
}
