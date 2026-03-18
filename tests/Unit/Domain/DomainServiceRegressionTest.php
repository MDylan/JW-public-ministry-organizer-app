<?php

namespace Tests\Unit\Domain;

use App\Classes\GenerateStat;
use App\Classes\GroupUserMoves;
use App\Helpers\GroupDateHelper;
use App\Jobs\CalculateDateProcess;
use App\Jobs\GenerateStatProcess;
use App\Models\GroupDate;
use App\Models\GroupDay;
use App\Notifications\GroupUserAddedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

class DomainServiceRegressionTest extends TestCase
{
    use RefreshDatabase;
    use BuildsDomainFixtures;

    public function test_generate_stat_dispatches_only_when_run_job_flag_allows_it(): void
    {
        Bus::fake();

        $group = $this->createGroup();
        $date = now()->addDay()->toDateString();

        GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date,
            'run_job' => 0,
        ]);

        $service = new GenerateStat();
        $service->generate($group->id, $date);

        Bus::assertDispatched(GenerateStatProcess::class);

        Bus::fake();
        GroupDate::where('group_id', $group->id)->where('date', $date)->update(['run_job' => 1]);
        $service->generate($group->id, $date);

        Bus::assertNotDispatched(GenerateStatProcess::class);
    }

    public function test_group_date_helper_generates_date_and_dispatches_recalculate_job(): void
    {
        Bus::fake();

        $user = $this->createUser(['email' => 'helper-user@example.test']);
        $group = $this->createGroup();

        $date = now()->addDay();

        GroupDay::factory()->create([
            'group_id' => $group->id,
            'day_number' => (int) $date->format('w'),
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);

        $this->actingAs($user);

        $helper = new GroupDateHelper($group->id);
        $generated = $helper->generateDate($date->toDateString(), true);

        $this->assertIsArray($generated);
        $this->assertDatabaseHas('group_dates', [
            'group_id' => $group->id,
            'date' => $date->toDateString(),
            'date_status' => 1,
        ]);

        $helper->recalculateDates();

        Bus::assertDispatched(CalculateDateProcess::class);
    }

    public function test_group_user_moves_attach_notifies_only_verified_users(): void
    {
        Notification::fake();

        $admin = $this->createUser([
            'role' => 'mainAdmin',
            'email' => 'moves-admin@example.test',
        ]);
        $verified = $this->createUser(['email' => 'moves-verified@example.test']);
        $unverified = $this->createUser([
            'email' => 'moves-unverified@example.test',
            'email_verified_at' => null,
        ]);

        $group = $this->createGroup();

        $this->actingAs($admin);

        (new GroupUserMoves($group->id, $verified->id))->attach();
        (new GroupUserMoves($group->id, $unverified->id))->attach();

        Notification::assertSentTo($verified, GroupUserAddedNotification::class);
        Notification::assertNotSentTo($unverified, GroupUserAddedNotification::class);
    }
}
