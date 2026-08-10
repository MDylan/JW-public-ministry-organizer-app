<?php

namespace Tests\Feature\Weather;

use App\Http\Livewire\Events\Events;
use App\Http\Livewire\Groups\UpdateGroupForm as GroupEditComponent;
use App\Models\Group;
use App\Models\WeatherCity;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 20.1: the read path - what the publisher and the admin actually see.
 *
 * The calendar (app/Http/Livewire/Events/Events.php:287-345) is a PURE
 * READER: it never calls the API, it only draws what is in the
 * `weather_cities` row. This file therefore works without a network, from a
 * hand-inserted cache row - exactly as it works in production between two
 * admin saves.
 *
 * The component's `$cal_group_data` field is private, so every assertion
 * targets the RENDERED output. This is also a stronger contract: it captures
 * what the user sees.
 *
 * TIMEZONE - measured, and essential for understanding the filtering. The
 * application runs in the `Europe/Budapest` timezone (config/app.php:72 +
 * TIMEZONE in both .env and .env.testing), while OpenWeather's `dt_txt`
 * field is UTC, and Events.php:305 correctly reads it as such. The service
 * day's `date_start`/`date_end` fields, however, are in LOCAL time. The
 * practical consequence: an 08:00-12:00 Budapest service window catches the
 * slots between 06:00-10:00 UTC, and since OpenWeather provides data every 3
 * hours (00, 03, 06, 09, 12, 15, 18, 21 UTC), this means exactly TWO slots:
 * 06:00 and 09:00. That is why this file uses timestamps given in UTC, and
 * why the 12:00 UTC slot does not fall inside the window.
 */
class WeatherRenderTest extends FeatureTestCase
{
    /** The month we mount the calendar onto - fixed, so the month boundary does not shake it. */
    private const YEAR = 2026;
    private const MONTH = 8;
    private const DAY_ONE = '2026-08-12';
    private const DAY_TWO = '2026-08-13';

    protected function setUp(): void
    {
        parent::setUp();

        config(['weather' => 1]);

        // The same network-freedom guarantee as in WeatherCacheTest: if any
        // path were to reach an API call after all, the client throws in its
        // constructor, before a connection is opened.
        config(['openweather.api_key' => '']);
    }

    /** A 3-hour forecast slot in the shape of OpenWeather's `forecast` response. */
    private function slot(string $dtTxt, float $temp, float $wind, string $description, string $icon): array
    {
        return [
            'dt_txt'  => $dtTxt,
            'main'    => ['temp' => $temp],
            'wind'    => ['speed' => $wind],
            'weather' => [['description' => $description, 'icon' => $icon]],
        ];
    }

    /**
     * A cache row in the production shape, and a group bound to it.
     *
     * Before the v1-patch C package, there was double JSON encoding here
     * (manual json_encode alongside the `json` cast), which readers had to
     * manually decode again. From here on the cast is the single encoding point.
     */
    private function groupWithWeather(array $current, array $forecastSlots, int $weatherEnabled = 1): Group
    {
        $city = WeatherCity::create([
            'city'             => 'Szeged',
            'country'          => 'HU',
            'current_weather'  => $current,
            'forecast_weather' => ['list' => $forecastSlots],
            'last_try'         => now(),
        ]);

        return $this->createGroup([
            'weather_enabled' => $weatherEnabled,
            'city_id'         => $city->id,
        ]);
    }

    private function currentBlob(): array
    {
        return [
            'name'    => 'Szeged',
            'main'    => ['temp' => 21.5, 'humidity' => 60, 'temp_min' => 19.0, 'temp_max' => 24.0],
            'wind'    => ['speed' => 3.0],
            'weather' => [['description' => 'derült égbolt', 'icon' => '01d']],
        ];
    }

    /** Mounts the calendar onto the fixed month, with a logged-in member. */
    private function calendar(Group $group)
    {
        $user = $this->createUser(['email' => 'weather-view-'.uniqid().'@example.test']);
        $this->attachUserToGroup($user, $group, 'member', true);

        session(['groupId' => $group->id]);

        return Livewire::actingAs($user)
            ->test(Events::class, ['year' => self::YEAR, 'month' => self::MONTH]);
    }

    // =========================================================================
    // 1. The current weather box
    // =========================================================================

    public function test_the_current_weather_block_renders_temperature_city_and_icon(): void
    {
        $group = $this->groupWithWeather($this->currentBlob(), []);
        $this->createEventDate($group, self::DAY_ONE);

        $this->calendar($group)
            ->assertSee('21.5')                             // number_format(temp, 1)
            ->assertSee('Szeged')
            ->assertSee('/images/wt_icons/01d@2x.png', false)
            ->assertSee('derült égbolt')
            ->assertSee('60');                              // humidity %
    }

    // =========================================================================
    // 2. Daily aggregation
    // =========================================================================

    public function test_the_forecast_column_aggregates_min_and_max_across_the_service_day(): void
    {
        // The service day 08:00-12:00 local time = 06:00-10:00 UTC, so
        // exactly the 06:00 and 09:00 UTC slots fall inside it.
        $group = $this->groupWithWeather($this->currentBlob(), [
            $this->slot(self::DAY_ONE.' 06:00:00', 12.4, 2.0, 'felhős', '03d'),
            $this->slot(self::DAY_ONE.' 09:00:00', 18.6, 6.0, 'szitálás', '09d'),
        ]);
        $this->createEventDate($group, self::DAY_ONE);

        $this->calendar($group)
            ->assertSee('19°')       // number_format(max_temp = 18.6)
            ->assertSee('12°')       // number_format(min_temp = 12.4)
            ->assertSee('14')        // (min_wind 2.0 + max_wind 6.0) / 2 * 3.6 = 14.4
            ->assertSee('08.12');    // the day's label, in m.d format
    }

    public function test_the_description_and_icon_come_from_the_last_slot_of_the_day(): void
    {
        $group = $this->groupWithWeather($this->currentBlob(), [
            $this->slot(self::DAY_ONE.' 06:00:00', 12.4, 2.0, 'reggeli köd', '50d'),
            $this->slot(self::DAY_ONE.' 09:00:00', 15.0, 4.0, 'déli napsütés', '01d'),
        ]);
        $this->createEventDate($group, self::DAY_ONE);

        // Temperature and wind are aggregated with min/max, while the
        // description and the icon are SIMPLY OVERWRITTEN (Events.php:335-336),
        // so the day's LAST 3-hour slot wins - not the most characteristic
        // one, not the midday one. Current behavior.
        $this->calendar($group)
            ->assertSee('déli napsütés')
            ->assertSee('/images/wt_icons/01d@2x.png', false)
            ->assertDontSee('reggeli köd');
    }

    // =========================================================================
    // 3. The two filters
    // =========================================================================

    public function test_forecast_entries_outside_the_service_window_are_ignored(): void
    {
        $group = $this->groupWithWeather($this->currentBlob(), [
            $this->slot(self::DAY_ONE.' 09:00:00', 12.4, 2.0, 'felhős', '03d'),
            // 12:00 UTC = 14:00 local time, i.e. AFTER the 12:00 close (Events.php:314).
            $this->slot(self::DAY_ONE.' 12:00:00', 47.3, 9.0, 'hőség', '01n'),
        ]);
        $this->createEventDate($group, self::DAY_ONE);

        $this->calendar($group)
            ->assertSee('12°')
            ->assertDontSee('47');
    }

    public function test_forecast_entries_on_days_without_a_service_date_are_ignored(): void
    {
        $group = $this->groupWithWeather($this->currentBlob(), [
            $this->slot(self::DAY_ONE.' 09:00:00', 12.4, 2.0, 'felhős', '03d'),
            // There is no GroupDate row for DAY_TWO, so $dates[$day] finds
            // nothing (Events.php:307) - the whole day is dropped.
            $this->slot(self::DAY_TWO.' 09:00:00', 55.5, 9.0, 'hőség', '01n'),
        ]);
        $this->createEventDate($group, self::DAY_ONE);

        $this->calendar($group)
            ->assertSee('08.12')
            ->assertDontSee('08.13')
            ->assertDontSee('56');
    }

    // =========================================================================
    // 4. The two gates
    // =========================================================================

    public function test_the_widget_is_suppressed_when_the_global_setting_is_off(): void
    {
        config(['weather' => 0]);

        $group = $this->groupWithWeather($this->currentBlob(), [
            $this->slot(self::DAY_ONE.' 09:00:00', 12.4, 2.0, 'felhős', '03d'),
        ]);
        $this->createEventDate($group, self::DAY_ONE);

        $this->calendar($group)->assertDontSee('/images/wt_icons/', false);
    }

    public function test_the_widget_is_suppressed_when_the_group_has_weather_disabled(): void
    {
        $group = $this->groupWithWeather($this->currentBlob(), [
            $this->slot(self::DAY_ONE.' 09:00:00', 12.4, 2.0, 'felhős', '03d'),
        ], 0);
        $this->createEventDate($group, self::DAY_ONE);

        $this->calendar($group)->assertDontSee('/images/wt_icons/', false);
    }

    // =========================================================================
    // 5. The admin's preview
    // =========================================================================

    public function test_the_admin_preview_fills_from_a_fresh_cache_row_without_a_call(): void
    {
        $group = $this->groupWithWeather($this->currentBlob(), []);
        $editor = $this->createUser(['email' => 'weather-preview@example.test']);
        $this->attachUserToGroup($editor, $group, 'roler', true);

        // mount loads the city name and country from the relation
        // (UpdateGroupForm.php:83-85), while checkWeatherSettings loads them
        // from the fresh cache row - without an API call.
        Livewire::actingAs($editor)
            ->test(GroupEditComponent::class, ['group' => $group->fresh()])
            ->assertSet('weather.city', 'Szeged')
            ->assertSet('weather.country', 'HU')
            ->call('checkWeatherSettings')
            ->assertSet('weather_messages.main.humidity', 60)
            ->assertSee('19')                                   // temp_min
            ->assertSee('24');                                  // temp_max
    }
}
