<?php

namespace Tests\Feature\Dates;

use App\Livewire\Events\EventEdit;
use App\Livewire\Events\Modal as EventsModal;
use Carbon\Carbon;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 72.3: date windows measured in calendar days, not in
 * 24-hour blocks.
 *
 * The suite runs in Europe/Budapest (.env.testing), where a day can have
 * 23 or 25 hours. `strtotime($date) - 30 * 24 * 60 * 60` from a midnight
 * timestamp lands on 23:00 of the day before whenever the span crosses the
 * spring change, and `+ N * 24 * 60 * 60` across the autumn one lands an
 * hour short - so date('Y-m-d') of the result was a calendar day off. The
 * dates below are the ones on which that happens.
 */
class ClockChangeTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * EventEdit shows a servant the chosen member's events from 30 days back
     * to 15 days ahead. 2026-03-30 is the day after the spring change.
     */
    public function test_the_statistics_window_starts_thirty_calendar_days_back(): void
    {
        [$admin, $group] = $this->adminWithGroup('clock-back');

        $this->actingAs($admin);
        foreach (['2026-02-27', '2026-02-28', '2026-04-14', '2026-04-15'] as $day) {
            $this->createEventInRange($group, $admin, $day, '09:00', '10:00');
        }
        $this->createEventDate($group, '2026-03-30');

        $component = Livewire::actingAs($admin)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => '2026-03-30']);

        $this->assertSame(['2026-02-28', '2026-04-14'], $this->statisticDays($component));
    }

    /**
     * 2026-10-20 + 15 days crosses the autumn change (2026-10-25).
     */
    public function test_the_statistics_window_ends_fifteen_calendar_days_ahead(): void
    {
        [$admin, $group] = $this->adminWithGroup('clock-ahead');

        $this->actingAs($admin);
        foreach (['2026-11-04', '2026-11-05'] as $day) {
            $this->createEventInRange($group, $admin, $day, '09:00', '10:00');
        }
        $this->createEventDate($group, '2026-10-20');

        $component = Livewire::actingAs($admin)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => '2026-10-20']);

        $this->assertSame(['2026-11-04'], $this->statisticDays($component));
    }

    /**
     * The day modal's "next day" arrow stops at max_extend_days from today.
     * Half past midnight, ten days before a date past the autumn change:
     * `time() + 10 * 24h` was 23:30 on the ninth day.
     */
    public function test_the_next_day_limit_counts_calendar_days_from_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 00:30:00'));

        $member = $this->createUser(['email' => 'clock-modal@example.test']);
        $group = $this->createGroup(['max_extend_days' => 10]);
        $this->attachUserToGroup($member, $group, 'member', true);

        foreach (['2026-10-29', '2026-10-30', '2026-10-31'] as $date) {
            $this->createEventDate($group, $date);
        }

        $modal = fn (string $date) => Livewire::actingAs($member)
            ->test(EventsModal::class)
            ->call('openModal', $date, $group->id)
            ->viewData('day_data')['next_date'];

        $this->assertSame('2026-10-30', $modal('2026-10-29'), 'Today + 10 days is still reachable.');
        $this->assertFalse($modal('2026-10-30'), 'Today + 11 days is not.');
    }

    private function adminWithGroup(string $prefix): array
    {
        $admin = $this->createUser(['email' => $prefix.'@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($admin, $group, 'admin', true);

        return [$admin, $group];
    }

    private function statisticDays($component): array
    {
        $days = array_map(
            fn (array $row) => substr($row['start'], 0, 10),
            $component->get('user_statistics')
        );
        sort($days);

        return $days;
    }
}
