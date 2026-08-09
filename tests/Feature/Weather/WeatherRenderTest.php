<?php

namespace Tests\Feature\Weather;

use App\Http\Livewire\Events\Events;
use App\Http\Livewire\Groups\UpdateGroupForm as GroupEditComponent;
use App\Models\Group;
use App\Models\WeatherCity;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 20.1: az olvasási út - amit a publikátor és az admin ténylegesen lát.
 *
 * A naptár (app/Http/Livewire/Events/Events.php:287-345) TISZTA OLVASÓ: soha nem
 * hív API-t, csak azt rajzolja ki, ami a `weather_cities` sorban áll. Ez a fájl
 * ezért hálózat nélkül, kézzel felvett gyorsítótár-sorból dolgozik - pontosan úgy,
 * ahogy éles környezetben is működik két adminmentés között.
 *
 * A komponens `$cal_group_data` mezője privát, ezért minden állítás a KIRENDERELT
 * kimenetre szól. Ez erősebb szerződés is: azt rögzíti, amit a felhasználó lát.
 *
 * IDŐZÓNA - mérve, és a szűrés megértéséhez elengedhetetlen. Az alkalmazás
 * `Europe/Budapest` időzónában fut (config/app.php:72 + TIMEZONE a .env-ben és a
 * .env.testing-ben egyaránt), az OpenWeather `dt_txt` mezője viszont UTC, és az
 * Events.php:305 helyesen így is olvassa be. A szolgálati nap `date_start`/`date_end`
 * mezői ellenben HELYI időben állnak. A gyakorlati következmény: egy 08:00-12:00
 * budapesti szolgálati ablak a 06:00-10:00 UTC közötti szeleteket fogja meg, és mivel
 * az OpenWeather 3 óránként ad adatot (00, 03, 06, 09, 12, 15, 18, 21 UTC), ez pontosan
 * KETTŐ szeletet jelent: a 06:00-t és a 09:00-t. Ezért használ ez a fájl UTC-ben
 * megadott időpontokat, és ezért nem esik bele a 12:00 UTC-s szelet.
 */
class WeatherRenderTest extends FeatureTestCase
{
    /** A hónap, amire a naptárat felcsatoljuk - fix, hogy a hónaphatár ne ingassa. */
    private const YEAR = 2026;
    private const MONTH = 8;
    private const DAY_ONE = '2026-08-12';
    private const DAY_TWO = '2026-08-13';

    protected function setUp(): void
    {
        parent::setUp();

        config(['weather' => 1]);

        // Ugyanaz a hálózat-mentességi garancia, mint a WeatherCacheTest-ben: ha
        // valamelyik útvonal mégis API-hívásig jutna, a kliens a konstruktorában
        // dob, mielőtt kapcsolat nyílna.
        config(['openweather.api_key' => '']);
    }

    /** Egy 3 órás előrejelzés-szelet az OpenWeather `forecast` válaszának alakjában. */
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
     * Gyorsítótár-sor az éles alakban, és egy hozzá kötött csoport.
     *
     * A v1-patch C csomagja előtt itt kétszeres JSON-kódolás állt (kézi
     * json_encode a `json` cast mellett), amit az olvasóknak kézzel kellett
     * visszabontaniuk. A cast innentől az egyetlen kódolási pont.
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

    /** Felcsatolja a naptárat a fix hónapra, bejelentkezett taggal. */
    private function calendar(Group $group)
    {
        $user = $this->createUser(['email' => 'weather-view-'.uniqid().'@example.test']);
        $this->attachUserToGroup($user, $group, 'member', true);

        session(['groupId' => $group->id]);

        return Livewire::actingAs($user)
            ->test(Events::class, ['year' => self::YEAR, 'month' => self::MONTH]);
    }

    // =========================================================================
    // 1. A jelenlegi időjárás doboza
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
            ->assertSee('60');                              // páratartalom %
    }

    // =========================================================================
    // 2. A napi aggregáció
    // =========================================================================

    public function test_the_forecast_column_aggregates_min_and_max_across_the_service_day(): void
    {
        // A szolgálati nap 08:00-12:00 helyi idő = 06:00-10:00 UTC, tehát pontosan
        // a 06:00 és a 09:00 UTC szelet esik bele.
        $group = $this->groupWithWeather($this->currentBlob(), [
            $this->slot(self::DAY_ONE.' 06:00:00', 12.4, 2.0, 'felhős', '03d'),
            $this->slot(self::DAY_ONE.' 09:00:00', 18.6, 6.0, 'szitálás', '09d'),
        ]);
        $this->createEventDate($group, self::DAY_ONE);

        $this->calendar($group)
            ->assertSee('19°')       // number_format(max_temp = 18.6)
            ->assertSee('12°')       // number_format(min_temp = 12.4)
            ->assertSee('14')        // (min_wind 2.0 + max_wind 6.0) / 2 * 3.6 = 14.4
            ->assertSee('08.12');    // a nap felirata, m.d formátumban
    }

    public function test_the_description_and_icon_come_from_the_last_slot_of_the_day(): void
    {
        $group = $this->groupWithWeather($this->currentBlob(), [
            $this->slot(self::DAY_ONE.' 06:00:00', 12.4, 2.0, 'reggeli köd', '50d'),
            $this->slot(self::DAY_ONE.' 09:00:00', 15.0, 4.0, 'déli napsütés', '01d'),
        ]);
        $this->createEventDate($group, self::DAY_ONE);

        // A hőmérséklet és a szél min/max-szal aggregálódik, a leírás és az ikon
        // viszont EGYSZERŰ felülírással (Events.php:335-336), tehát a nap UTOLSÓ
        // 3 órás szeletéé nyer - nem a jellemzőé, nem a déli óráé. Mai viselkedés.
        $this->calendar($group)
            ->assertSee('déli napsütés')
            ->assertSee('/images/wt_icons/01d@2x.png', false)
            ->assertDontSee('reggeli köd');
    }

    // =========================================================================
    // 3. A két szűrés
    // =========================================================================

    public function test_forecast_entries_outside_the_service_window_are_ignored(): void
    {
        $group = $this->groupWithWeather($this->currentBlob(), [
            $this->slot(self::DAY_ONE.' 09:00:00', 12.4, 2.0, 'felhős', '03d'),
            // 12:00 UTC = 14:00 helyi idő, vagyis a 12:00-s zárás UTÁN (Events.php:314).
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
            // A DAY_TWO-ra nincs GroupDate sor, tehát a $dates[$day] nem talál
            // (Events.php:307) - az egész nap kimarad.
            $this->slot(self::DAY_TWO.' 09:00:00', 55.5, 9.0, 'hőség', '01n'),
        ]);
        $this->createEventDate($group, self::DAY_ONE);

        $this->calendar($group)
            ->assertSee('08.12')
            ->assertDontSee('08.13')
            ->assertDontSee('56');
    }

    // =========================================================================
    // 4. A két kapu
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
    // 5. Az admin előnézete
    // =========================================================================

    public function test_the_admin_preview_fills_from_a_fresh_cache_row_without_a_call(): void
    {
        $group = $this->groupWithWeather($this->currentBlob(), []);
        $editor = $this->createUser(['email' => 'weather-preview@example.test']);
        $this->attachUserToGroup($editor, $group, 'roler', true);

        // A mount a relációból tölti a városnevet és az országot
        // (UpdateGroupForm.php:83-85), a checkWeatherSettings pedig a friss
        // gyorsítótár-sorból - API-hívás nélkül.
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
