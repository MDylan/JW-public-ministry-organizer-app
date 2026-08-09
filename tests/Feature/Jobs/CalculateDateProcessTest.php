<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CalculateDateProcess;
use App\Models\DayStat;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 05: real handle() execution.
 *
 * Dispatched from GroupDateHelper and Groups\SpecialDateModal. It delegates to
 * CalculateDatesEvents::generate() and then optionally prunes disabled dates.
 */
class CalculateDateProcessTest extends FeatureTestCase
{
    private Group $group;
    private User $member;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = now()->addDay()->toDateString();
        $this->group = $this->createGroup();
        $this->member = $this->createUser(['email' => 'calc-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group);
        $this->actingAs($this->member);

        GroupDate::factory()->create([
            'group_id' => $this->group->id,
            'date' => $this->date,
        ]);
    }

    public function test_handle_runs_without_a_delete_list_and_leaves_the_date_in_place(): void
    {
        (new CalculateDateProcess($this->group->id, $this->date, $this->member->id))->handle();

        $this->assertDatabaseHas('group_dates', [
            'group_id' => $this->group->id,
            'date' => $this->date,
        ]);
    }

    public function test_handle_accepts_an_array_of_dates(): void
    {
        // A konstruktor array|string uniót deklarál - mindkettőt fedjük.
        $second = now()->addDays(2)->toDateString();
        GroupDate::factory()->create(['group_id' => $this->group->id, 'date' => $second]);

        (new CalculateDateProcess($this->group->id, [$this->date, $second], $this->member->id))->handle();

        $this->assertSame(2, GroupDate::where('group_id', $this->group->id)->count());
    }

    public function test_handle_deletes_only_disabled_dates_from_the_delete_list(): void
    {
        $disabled = now()->addDays(3)->toDateString();
        GroupDate::factory()->create([
            'group_id' => $this->group->id,
            'date' => $disabled,
            'date_status' => 0,
        ]);

        (new CalculateDateProcess($this->group->id, $this->date, $this->member->id, [$disabled, $this->date]))
            ->handle();

        // A letiltott dátum törlődik...
        $this->assertDatabaseMissing('group_dates', ['group_id' => $this->group->id, 'date' => $disabled]);
        // ...az aktív viszont marad, mert a törlés date_status = 0 feltételhez kötött.
        $this->assertDatabaseHas('group_dates', ['group_id' => $this->group->id, 'date' => $this->date]);
    }

    public function test_handle_also_purges_day_stats_for_every_date_in_the_delete_list(): void
    {
        // A DayStat törlés nincs date_status-hoz kötve: minden felsorolt napra fut.
        DayStat::factory()->create([
            'group_id' => $this->group->id,
            'day' => $this->date,
            'time_slot' => $this->date.' 09:00:00',
        ]);

        (new CalculateDateProcess($this->group->id, $this->date, $this->member->id, [$this->date]))->handle();

        $this->assertSame(0, DayStat::where('group_id', $this->group->id)->where('day', $this->date)->count());
    }

    public function test_handle_leaves_other_groups_untouched(): void
    {
        $otherGroup = $this->createGroup();
        GroupDate::factory()->create([
            'group_id' => $otherGroup->id,
            'date' => $this->date,
            'date_status' => 0,
        ]);

        (new CalculateDateProcess($this->group->id, $this->date, $this->member->id, [$this->date]))->handle();

        $this->assertDatabaseHas('group_dates', ['group_id' => $otherGroup->id, 'date' => $this->date]);
    }
}
