<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CalculateDateProcess;
use App\Jobs\CalulcateUserNameIndexProcess;
use App\Jobs\DeleteGroupDataProcess;
use App\Jobs\GenerateStatProcess;
use App\Jobs\GroupDayUpdatedProcess;
use App\Jobs\UserLogoutFromGroupProcess;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 05: queue serialization round-trip for every job.
 *
 * There were 8 when this was written; TODO 10.2 deleted GroupDayDeletedProcess,
 * leaving 7.
 *
 * Every job uses SerializesModels, which replaces Eloquent models with a
 * model-identifier stub on serialize and re-queries them on unserialize.
 * The serialized payload format and the model-restoration path are exactly
 * what changes between framework majors (Laravel 11 reworked queue
 * serialization), so a job that cannot survive serialize/unserialize will
 * fail in production long after the upgrade PR is merged - when a worker
 * picks up a job written by the previous release.
 *
 * These tests also run on a real queue connection via the sync driver, so the
 * full dispatch path is exercised, not just handle().
 */
class JobSerializationTest extends FeatureTestCase
{
    private Group $group;
    private User $member;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = now()->addDay()->toDateString();
        $this->group = $this->createGroup();
        $this->member = $this->createUser(['email' => 'serialize-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group);
        $this->actingAs($this->member);

        GroupDate::factory()->create([
            'group_id' => $this->group->id,
            'date' => $this->date,
            'date_start' => $this->date.' 08:00:00',
            'date_end' => $this->date.' 12:00:00',
            'date_min_time' => 60,
        ]);
    }

    /** One realistic instance of each job. */
    private function jobs(): array
    {
        return [
            CalculateDateProcess::class => new CalculateDateProcess($this->group->id, $this->date, $this->member->id),
            CalulcateUserNameIndexProcess::class => new CalulcateUserNameIndexProcess(),
            DeleteGroupDataProcess::class => new DeleteGroupDataProcess($this->group->id, false),
            GenerateStatProcess::class => new GenerateStatProcess($this->group->id, $this->date, false),
            GroupDayUpdatedProcess::class => new GroupDayUpdatedProcess($this->date, $this->group->id, 3, '08:00', '12:00', $this->member->id),
            UserLogoutFromGroupProcess::class => new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User'),
        ];
    }

    public function test_the_job_set_matches_what_is_actually_on_disk(): void
    {
        // NEW with v1-patch B9.
        //
        // This file names the FULL set of jobs individually, so it is only
        // worth something if the list never drifts from reality. The earlier
        // version listed 8, then 7 jobs; deleting EventAutoCheck left 6.
        // Adding a new job from now on will break this test - which is
        // exactly the intent: its serializability must be added here too.
        $files = glob(app_path('Jobs/*.php'));

        $onDisk = array_map(
            fn (string $file): string => 'App\\Jobs\\'.basename($file, '.php'),
            $files
        );
        sort($onDisk);

        $declared = array_keys($this->jobs());
        sort($declared);

        $this->assertSame($onDisk, $declared);
    }

    public function test_the_deleted_event_auto_check_job_stays_deleted(): void
    {
        // EventAutoCheck was unable to run - empty foreach, an invalid
        // '=<' SQL operator, and its body read an object property on an
        // array element -, and its two dispatch sites were commented out
        // from the start, so it demonstrably never ran. Deleted in
        // v1-patch B9.
        //
        // If someone ever writes automatic approval, it must be a new job,
        // not a resurrection of this one - hence the guard on the class
        // name too, not just the file.
        $this->assertFalse(class_exists('App\\Jobs\\EventAutoCheck'));
        $this->assertFileDoesNotExist(app_path('Jobs/EventAutoCheck.php'));

        $observer = file_get_contents(app_path('Observers/EventObserver.php'));
        $this->assertStringNotContainsString('EventAutoCheck::dispatch', $observer);
    }

    public function test_every_job_survives_a_serialize_unserialize_round_trip(): void
    {
        foreach ($this->jobs() as $class => $job) {
            $restored = unserialize(serialize($job));

            $this->assertInstanceOf($class, $restored, $class.' did not survive serialization.');
        }
    }

    public function test_every_job_is_queueable(): void
    {
        foreach ($this->jobs() as $class => $job) {
            $this->assertInstanceOf(
                ShouldQueue::class,
                $job,
                $class.' is no longer queueable; the scheduler and observers assume it is.'
            );
        }
    }

    public function test_jobs_holding_models_restore_them_after_unserialize(): void
    {
        // This is the point of SerializesModels: the payload only stores the
        // identifier, the worker re-queries the model. If the model
        // disappears in the meantime, the job fails with a
        // ModelNotFoundException - so it matters that the restoration
        // actually works.
        $job = new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User');

        $restored = unserialize(serialize($job));

        $reflection = new \ReflectionClass($restored);

        $group = $reflection->getProperty('group');
        $group->setAccessible(true);
        $user = $reflection->getProperty('user');
        $user->setAccessible(true);

        $this->assertInstanceOf(Group::class, $group->getValue($restored));
        $this->assertSame($this->group->id, $group->getValue($restored)->id);
        $this->assertInstanceOf(User::class, $user->getValue($restored));
        $this->assertSame($this->member->id, $user->getValue($restored)->id);
    }

    public function test_serialized_payload_stores_a_model_identifier_not_the_model_state(): void
    {
        // The payload must not contain the model's attributes - partly
        // because of size, and partly because User.name and Group.name are
        // encrypted columns and must not leak into the queue table as plain
        // text.
        $job = new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User');

        $payload = serialize($job);

        $this->assertStringContainsString('ModelIdentifier', $payload);
        $this->assertStringNotContainsString('serialize-member@example.test', $payload);
    }

    public function test_a_serialized_job_still_does_its_work_after_being_restored(): void
    {
        // End to end: serialization, restoration, execution.
        $event = Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => $this->date,
            'start' => $this->date.' 09:00:00',
            'end' => $this->date.' 10:00:00',
            'status' => 1,
            'accepted_at' => now(),
            'accepted_by' => $this->member->id,
        ]);

        $restored = unserialize(serialize(new GenerateStatProcess($this->group->id, $this->date, false)));
        $restored->handle();

        $this->assertSame(4, \App\Models\DayStat::where('group_id', $this->group->id)->count());
        $this->assertNotNull(Event::find($event->id));
    }

    public function test_dispatching_through_the_sync_queue_executes_the_job(): void
    {
        // phpunit.xml sets QUEUE_CONNECTION=sync, so the dispatch walks the
        // full queue path - including serialization.
        GroupDate::where('group_id', $this->group->id)->update(['run_job' => 1]);

        GenerateStatProcess::dispatch($this->group->id, $this->date, false);

        $this->assertSame(0, (int) GroupDate::where('group_id', $this->group->id)->value('run_job'));
    }
}
