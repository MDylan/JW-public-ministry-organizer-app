<?php

namespace Tests\Unit\Support;

use App\Support\Retention\RetentionWindow;
use Carbon\Carbon;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * v1-patch E1: the single source of truth for the retention floors.
 *
 * The two most important cases here are not the happy path, but the two
 * traps that the class's own docblock also highlights: a setting value
 * outside the whitelist (which, cast, would give a ZERO-month window, i.e. a
 * full table wipe) and end-of-month overflow.
 */
class RetentionWindowTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // --- eventsFloor ---

    public function test_the_events_floor_is_null_while_gdpr_is_disabled(): void
    {
        config(['gdpr.enabled' => false]);

        $this->assertNull(RetentionWindow::eventsFloor());
    }

    public function test_the_events_floor_is_the_configured_number_of_months_back(): void
    {
        Carbon::setTestNow('2026-08-08 14:30:00');
        config(['gdpr.enabled' => true, 'retention.events_months' => 13]);

        $this->assertSame('2025-07-08', RetentionWindow::eventsFloor()->toDateString());
    }

    public function test_the_events_floor_is_the_start_of_the_day(): void
    {
        Carbon::setTestNow('2026-08-08 14:30:00');
        config(['gdpr.enabled' => true]);

        // The day columns are of type DATE: a floor at 14:30 would delete or
        // keep the boundary-day rows depending on the hour of the run.
        $this->assertSame('00:00:00', RetentionWindow::eventsFloor()->format('H:i:s'));
    }

    public function test_the_events_floor_does_not_overflow_at_the_end_of_a_month(): void
    {
        // Carbon's subMonths() would by default give 2025-03-03.
        Carbon::setTestNow('2026-03-31 08:00:00');
        config(['gdpr.enabled' => true, 'retention.events_months' => 13]);

        $this->assertSame('2025-02-28', RetentionWindow::eventsFloor()->toDateString());
    }

    // --- groupDataFloor ---

    public function test_the_group_data_floor_is_null_when_the_setting_is_off(): void
    {
        config(['settings_group_data_retention' => '0']);

        $this->assertNull(RetentionWindow::groupDataFloor());
    }

    public function test_the_group_data_floor_is_null_when_the_setting_is_missing(): void
    {
        config(['settings_group_data_retention' => null]);

        $this->assertNull(RetentionWindow::groupDataFloor());
    }

    /**
     * This is the single most important case in the whole class. The
     * Livewire component writes to the settings table without validation, so
     * anything can end up in there. If the value were cast, (int) would give
     * zero, the zero-month window's floor would be TODAY, and the command
     * would delete every day_stats row on the installation.
     *
     * The sentence above used to quote a row count measured on a real
     * database, which AGENTS.md forbids in test comments for the same reason
     * it forbids it anywhere else: the repository ships to every install, and
     * the number describes one of them at one moment. The defect is the same
     * size whatever the count is.
     */
    #[DataProvider('garbageSettingValues')]
    public function test_the_group_data_floor_refuses_a_value_outside_the_whitelist($value): void
    {
        config(['settings_group_data_retention' => $value]);

        $this->assertNull(RetentionWindow::groupDataFloor());
    }

    public static function garbageSettingValues(): array
    {
        return [
            'letters'        => ['abc'],
            'empty string'   => [''],
            'negative'       => ['-5'],
            'zero months'    => ['0'],
            'not allowed'    => ['6'],
            'boolean true'   => [true],
            'array'          => [['12']],
        ];
    }

    public function test_the_group_data_floor_accepts_one_year(): void
    {
        Carbon::setTestNow('2026-08-08 14:30:00');
        config(['settings_group_data_retention' => '12']);

        $this->assertSame('2025-08-08', RetentionWindow::groupDataFloor()->toDateString());
    }

    public function test_the_group_data_floor_accepts_two_years(): void
    {
        Carbon::setTestNow('2026-08-08 14:30:00');
        config(['settings_group_data_retention' => '24']);

        $this->assertSame('2024-08-08', RetentionWindow::groupDataFloor()->toDateString());
    }

    public function test_the_group_data_floor_ignores_the_gdpr_switch(): void
    {
        Carbon::setTestNow('2026-08-08 14:30:00');
        config(['gdpr.enabled' => false, 'settings_group_data_retention' => '12']);

        // It contains no personal data - it's driven by size, so the GDPR
        // switch cannot turn it off.
        $this->assertSame('2025-08-08', RetentionWindow::groupDataFloor()->toDateString());
    }

    // --- displayFloor ---

    public function test_the_display_floor_is_null_when_neither_retention_is_active(): void
    {
        config(['gdpr.enabled' => false, 'settings_group_data_retention' => '0']);

        $this->assertNull(RetentionWindow::displayFloor());
    }

    public function test_the_display_floor_falls_back_to_the_only_active_floor(): void
    {
        Carbon::setTestNow('2026-08-08 14:30:00');

        config(['gdpr.enabled' => true, 'settings_group_data_retention' => '0']);
        $this->assertSame('2025-07-08', RetentionWindow::displayFloor()->toDateString());

        config(['gdpr.enabled' => false, 'settings_group_data_retention' => '24']);
        $this->assertSame('2024-08-08', RetentionWindow::displayFloor()->toDateString());
    }

    public function test_the_display_floor_is_the_later_of_the_two_active_floors(): void
    {
        Carbon::setTestNow('2026-08-08 14:30:00');
        config(['gdpr.enabled' => true, 'retention.events_months' => 13]);

        // The 1-year group-data window gives a LATER floor than the
        // 13-month event window - a calendar is only trustworthy for as long
        // as both of its sources are alive.
        config(['settings_group_data_retention' => '12']);
        $this->assertSame('2025-08-08', RetentionWindow::displayFloor()->toDateString());

        // The 2-year window is earlier, so the events floor wins.
        config(['settings_group_data_retention' => '24']);
        $this->assertSame('2025-07-08', RetentionWindow::displayFloor()->toDateString());
    }

    public function test_each_call_returns_a_fresh_instance(): void
    {
        Carbon::setTestNow('2026-08-08 14:30:00');
        config(['gdpr.enabled' => true]);

        $first = RetentionWindow::eventsFloor();
        $first->startOfMonth();

        $this->assertNotSame($first->toDateString(), RetentionWindow::eventsFloor()->toDateString());
    }
}
