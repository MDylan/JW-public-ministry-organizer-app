<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CalculateDateProcess;
use App\Jobs\CalulcateUserNameIndexProcess;
use App\Jobs\DeleteGroupDataProcess;
use App\Jobs\EventAutoCheck;
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

    private function event(): Event
    {
        return Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => $this->date,
            'start' => $this->date.' 09:00:00',
            'end' => $this->date.' 10:00:00',
            'status' => 0,
        ]);
    }

    /** Minden job egy-egy realisztikus példánya. */
    private function jobs(): array
    {
        $event = $this->event();

        return [
            CalculateDateProcess::class => new CalculateDateProcess($this->group->id, $this->date, $this->member->id),
            CalulcateUserNameIndexProcess::class => new CalulcateUserNameIndexProcess(),
            DeleteGroupDataProcess::class => new DeleteGroupDataProcess($this->group->id, false),
            EventAutoCheck::class => new EventAutoCheck($event, strtotime($event->start), strtotime($event->end)),
            GenerateStatProcess::class => new GenerateStatProcess($this->group->id, $this->date, false),
            GroupDayUpdatedProcess::class => new GroupDayUpdatedProcess($this->date, $this->group->id, 3, '08:00', '12:00', $this->member->id),
            UserLogoutFromGroupProcess::class => new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User'),
        ];
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
        // Ez a SerializesModels lényege: a payload csak az azonosítót tárolja,
        // a modellt a worker kérdezi le újra. Ha a modell időközben eltűnik,
        // a job ModelNotFoundException-nel bukik - ezért fontos, hogy a
        // visszatöltés ténylegesen működjön.
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
        // A payload nem tartalmazhatja a modell attribútumait - egyrészt
        // méret miatt, másrészt mert a User.name és a Group.name titkosított
        // oszlop, és nem szivároghat a queue táblába nyílt szövegként.
        $job = new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User');

        $payload = serialize($job);

        $this->assertStringContainsString('ModelIdentifier', $payload);
        $this->assertStringNotContainsString('serialize-member@example.test', $payload);
    }

    public function test_a_serialized_job_still_does_its_work_after_being_restored(): void
    {
        // Végponttól végpontig: szerializálás, visszatöltés, futtatás.
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
        // A phpunit.xml QUEUE_CONNECTION=sync beállítású, így a dispatch a
        // teljes queue útvonalat végigjárja - beleértve a szerializálást.
        GroupDate::where('group_id', $this->group->id)->update(['run_job' => 1]);

        GenerateStatProcess::dispatch($this->group->id, $this->date, false);

        $this->assertSame(0, (int) GroupDate::where('group_id', $this->group->id)->value('run_job'));
    }
}
