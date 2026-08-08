<?php

namespace Tests\Unit\Support;

use App\Support\Retention\RetentionWindow;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * v1-patch E1: a retenciós padlók egyetlen igazságforrása.
 *
 * A két legfontosabb eset itt nem a boldog út, hanem a két csapda, amit az
 * osztály docblockja is kiemel: a whitelisten kívüli beállításérték (ami
 * castolva NULLA hónapos ablakot, azaz teljes táblatörlést adna) és a
 * hónap végi túlcsordulás.
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

        // A day oszlopok DATE típusúak: egy 14:30-as padló a határnapi
        // sorokat a futás órájától függően törölné vagy hagyná.
        $this->assertSame('00:00:00', RetentionWindow::eventsFloor()->format('H:i:s'));
    }

    public function test_the_events_floor_does_not_overflow_at_the_end_of_a_month(): void
    {
        // A Carbon subMonths() alapból 2025-03-03-at adna.
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
     * Ez a legfontosabb eset az egész osztályban. A settings táblába a
     * Livewire komponens validáció nélkül ír, tehát bármi bekerülhet. Ha az
     * érték castolva lenne, a (int) nullát adna, a nulla hónapos ablak
     * padlója a MAI nap lenne, és a parancs mind a 471 754 day_stats sort
     * kitörölné.
     *
     * @dataProvider garbageSettingValues
     */
    public function test_the_group_data_floor_refuses_a_value_outside_the_whitelist($value): void
    {
        config(['settings_group_data_retention' => $value]);

        $this->assertNull(RetentionWindow::groupDataFloor());
    }

    public function garbageSettingValues(): array
    {
        return [
            'betűk'          => ['abc'],
            'üres string'    => [''],
            'negatív'        => ['-5'],
            'nulla hónap'    => ['0'],
            'nem engedett'   => ['6'],
            'igaz logikai'   => [true],
            'tömb'           => [['12']],
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

        // Nincs benne személyes adat, a méret hajtja - a GDPR kapcsoló nem
        // kapcsolhatja ki.
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

        // Az 1 éves csoportadat-ablak KÉSŐBBI padlót ad, mint a 13 hónapos
        // eseményablak - egy naptár csak addig hiteles, ameddig mindkét
        // forrása él.
        config(['settings_group_data_retention' => '12']);
        $this->assertSame('2025-08-08', RetentionWindow::displayFloor()->toDateString());

        // A 2 éves ablak korábbi, tehát az események padlója nyer.
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
