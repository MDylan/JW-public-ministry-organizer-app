<?php

namespace Tests\Unit\Scheduler;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulerRegressionTest extends TestCase
{
    public function test_scheduler_registers_expected_command_events(): void
    {
        $events = collect(app(Schedule::class)->events());

        $commandEvents = $events->filter(static fn ($event) => $event->command !== null);

        $this->assertTrue(
            $commandEvents->contains(
                static fn ($event) => str_contains($event->command, 'queue:work --name=kozteruletek-job-1')
                    && $event->expression === '* * * * *'
            )
        );

        $this->assertTrue(
            $commandEvents->contains(
                static fn ($event) => str_contains($event->command, 'gdpr:anonymizeInactiveUsers')
                    && $event->expression === '0 0 * * *'
            )
        );
    }

    public function test_scheduler_registers_expected_callback_frequencies(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(static fn ($event) => $event->command === null);

        $this->assertCount(9, $events);

        $expressions = $events->pluck('expression')->toArray();

        $this->assertSame(2, count(array_keys($expressions, '* * * * *', true)));
        $this->assertSame(1, count(array_keys($expressions, '50 * * * *', true)));
        $this->assertSame(1, count(array_keys($expressions, '*/5 * * * *', true)));
        $this->assertSame(1, count(array_keys($expressions, '0 7 * * *', true)));
        $this->assertSame(1, count(array_keys($expressions, '10 7 * * *', true)));
        $this->assertSame(2, count(array_keys($expressions, '0 0 * * *', true)));
        $this->assertSame(1, count(array_keys($expressions, '0 * * * *', true)));
    }
}
