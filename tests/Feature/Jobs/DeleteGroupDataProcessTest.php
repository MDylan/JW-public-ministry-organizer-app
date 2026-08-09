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
     * A GroupDelete controller tömeges törlést használ
     * ($user->userGroupsDeletable()->where(...)->delete()), ami query builderen
     * fut, ezért NEM indít modell-eseményt. Ez itt lényeges: a
     * GroupObserver::deleted() a nem létező $group->group_id mezőt olvassa
     * (helyesen $group->id lenne), így Eloquent-törléskor a LogHistory.group_id
     * null lenne és az egész művelet elszállna. Lásd a lenti jellemzés-tesztet.
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
        // A v1-patch B10 előtt ez a teszt csak azért volt zöld, mert a
        // csoportot előtte soft-deleteltük: az anonimizálás feltétele
        // (count($user->user->userGroups) == 0) a groups táblához joinoló
        // reláción múlt. Most a job maga zárja ki a saját csoportját, tehát a
        // sorrend már nem számít - a soft-delete itt csak az éles
        // GroupDelete útvonalat utánozza.
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
        // MEGFORDÍTVA a v1-patch B10 javításával.
        //
        // A job némán sorrendfüggő volt: csak akkor anonimizált, ha a csoport a
        // futásakor MÁR soft-deleted volt, mert a userGroups reláció a groups
        // táblához joinol, és a törölt csoport kiesett belőle. Az éles
        // GroupDelete útvonal előbb töröl, aztán dispatch-el, ezért működött -
        // de a jobot élő csoportra hívva (kézi futtatás, más hívó, megváltozott
        // sorrend) az anonimizálás teljesen kimaradt, hibaüzenet nélkül.
        //
        // A feltétel most explicit: a saját csoportot zárjuk ki a számlálásból.
        (new DeleteGroupDataProcess($this->group->id, true))->handle();

        $fresh = User::find($this->member->id);

        $this->assertNotNull($fresh, 'A felhasználó sora megmarad, csak az adatai tűnnek el.');
        $this->assertNotSame('delete-member@example.test', $fresh->email);
    }

    public function test_handle_still_spares_a_member_of_another_live_group(): void
    {
        // A B10 kontroll-kísérlete: a tágabb feltétel nem anonimizálhat olyat,
        // akinek van másik csoportja - függetlenül attól, hogy az éppen
        // törlendő csoport él-e még.
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
        // TODO 10 javította a GroupObserver::deleted()-et: korábban a Group
        // modellen nem létező $group->group_id mezőt olvasta, így mindig null
        // került a NOT NULL log_histories.group_id oszlopba, és MINDEN
        // Eloquent-törlés elszállt. Élesben csak azért nem látszott, mert a
        // GroupDelete tömeges törlést használ, ami nem indít eseményt.
        //
        // Ez a teszt most a javított viselkedés regressziós védelme.
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
