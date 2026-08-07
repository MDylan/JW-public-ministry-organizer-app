<?php

namespace Tests\Feature\Weather;

use App\Http\Livewire\Groups\UpdateGroupForm as GroupEditComponent;
use App\Models\WeatherCity;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 20.1: az időjárás-gyorsítótár, ágról ágra.
 *
 * A mért helyzet: a `pwbs_weather_api_call()` (app/Helpers/helpers.php:84-164) az
 * EGYETLEN hely, ahonnan a projekt a `rakibdevs/openweather-laravel-api` csomagot
 * használja, és ez a függvény egyszerre gyorsítótár, fék és API-kliens. A TODO 20
 * felmérése előtt egyetlen teszt sem gyakorolta.
 *
 * MIÉRT NINCS TESZT A SIKERES API-ÁGRA, ÉS MIÉRT NEM IS LEHET.
 * A csomag `WeatherClient::client()` metódusa (vendor/.../WeatherClient.php:88-96)
 * helyben példányosít egy `GuzzleHttp\Client`-et, konténerkötés és injektálási pont
 * nélkül. A `Http::fake()` a Laravel SAJÁT kliensgyárát fogja el, ehhez hozzá sem
 * ér - lásd a tests/Feature/Middleware/CheckRecaptchaTest.php mintáját, ami pont
 * így fake-el egy kimenő API-t, és ami itt nem alkalmazható. A sikeres ág tehát
 * csak valódi hálózati hívással volna elérhető, amit egy teszt nem tehet meg.
 *
 * Ez a rés a TODO 20 döntésének legerősebb érve: a csomag lecserélése után
 * (TODO 33.6) a `Http::fake()` működik, és a sikeres ág is lefedhető lesz.
 *
 * Amit viszont MA is meg lehet mérni, az minden más: a kikapcsolt állapot, a
 * gyorsítótár-találat, a normalizálás, a 15 perces fék, és a hiányzó API-kulcs
 * útja - ez utóbbi a kliens KONSTRUKTORÁBAN dob, mielőtt socket nyílna.
 */
class WeatherCacheTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A funkció alapból ki van kapcsolva (CoreSettingsSeeder.php:30 => '0'), és
        // az AppServiceProvider boot időben tölti a config('weather')-t a settings
        // táblából. A teszt ezért közvetlenül a configot állítja.
        config(['weather' => 1]);

        // Biztonsági rögzítés, nem díszlet: üres kulcsnál a WeatherClient a
        // konstruktorában dob (:57-63), MIELŐTT bármilyen kapcsolat nyílna. Ez
        // garantálja, hogy ez a fájl akkor sem indít valódi hálózati kérést, ha
        // valaki később OPENWAETHER_API_KEY-t tesz a .env-be.
        config(['openweather.api_key' => '']);
    }

    /**
     * Egy gyorsítótár-sor az ÉLES alakban.
     *
     * A helpers.php:116-117 `json_encode()`-ol, majd a modell 'json' castja íráskor
     * még egyszer kódol. Ha a teszt tömböt írna (ahogy a WeatherCityFactory teszi),
     * olyan alakot rögzítene, ami élesben nem fordulhat elő.
     */
    private function cacheRow(string $city, string $country, ?array $current = null, ?array $forecast = null): WeatherCity
    {
        return WeatherCity::create([
            'city'             => $city,
            'country'          => $country,
            'current_weather'  => $current === null ? null : json_encode($current),
            'forecast_weather' => $forecast === null ? null : json_encode($forecast),
            'last_try'         => now(),
        ]);
    }

    /** Öregíti a sort a cast és az observerek megkerülésével. */
    private function age(WeatherCity $row, ?string $updatedAt = null, ?string $lastTry = null): void
    {
        $payload = [];

        if ($updatedAt !== null) {
            $payload['updated_at'] = $updatedAt;
        }

        if ($lastTry !== null) {
            $payload['last_try'] = $lastTry;
        }

        DB::table('weather_cities')->where('id', $row->getKey())->update($payload);
    }

    // =========================================================================
    // 1. A kikapcsolt alapállapot
    // =========================================================================

    public function test_the_helper_returns_null_and_writes_nothing_when_the_feature_is_off(): void
    {
        config(['weather' => 0]);

        $this->assertNull(pwbs_weather_api_call('Szeged', 'HU'));
        $this->assertSame(0, WeatherCity::count(), 'A kikapcsolt kapu (helpers.php:86) minden egyéb előtt visszatér.');
    }

    // =========================================================================
    // 2. A gyorsítótár-találat
    // =========================================================================

    public function test_a_row_newer_than_59_minutes_is_served_from_the_cache(): void
    {
        $row = $this->cacheRow(
            'Szeged',
            'HU',
            ['main' => ['temp' => 21.5, 'humidity' => 60], 'name' => 'Szeged'],
            ['list' => [['dt_txt' => '2026-08-08 09:00:00']]]
        );

        $result = pwbs_weather_api_call('Szeged', 'HU');

        $this->assertSame($row->id, $result['city_id']);
        $this->assertArrayNotHasKey('error', $result);

        // A visszaadott érték TÖMB, mert a helper a cast által stringként visszaadott
        // értéket még egyszer dekódolja (helpers.php:96-97).
        $this->assertSame(21.5, $result['current_weather']['main']['temp']);
        $this->assertSame('Szeged', $result['current_weather']['name']);
        $this->assertSame('2026-08-08 09:00:00', $result['forecast_weather']['list'][0]['dt_txt']);

        $this->assertSame(1, WeatherCity::count(), 'A találat semmit nem ír.');
    }

    public function test_the_lookup_is_case_insensitive_and_trims_the_input(): void
    {
        $row = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);

        // A `trim()` a helper dolga (helpers.php:88-89), a kis- és nagybetű
        // függetlenség viszont NEM: azt a `weather_cities` oszlopainak
        // `utf8mb4_*_ci` kollációja adja. Mérési következmény: a `ucfirst()` nem a
        // találatot dönti el, hanem azt, hogy az ELSŐ írás milyen alakban rögzíti
        // a várost, és milyen alakban megy ki a név az OpenWeather felé.
        foreach (['  szeged  ', 'SZEGED', 'sZeGeD', 'Szeged'] as $input) {
            $result = pwbs_weather_api_call($input, 'hu');

            $this->assertSame($row->id, $result['city_id'], "A(z) '{$input}' bemenet ugyanarra a sorra talált.");
            $this->assertSame(21.5, $result['current_weather']['main']['temp']);
        }

        $this->assertSame(1, WeatherCity::count(), 'Egyetlen sor sem keletkezett újra.');
    }

    // =========================================================================
    // 3. A 15 perces fék
    // =========================================================================

    public function test_a_stale_row_whose_last_try_is_recent_returns_the_throttle_error(): void
    {
        $row = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);

        // Régebbi, mint 59 perc -> nincs találat; de 15 percen belül próbálkoztunk.
        $this->age(
            $row,
            now()->subHours(2)->toDateTimeString(),
            now()->subMinutes(5)->toDateTimeString()
        );

        $result = pwbs_weather_api_call('Szeged', 'HU');

        $this->assertSame($row->id, $result['city_id']);
        $this->assertSame(__('group.weather.too_many_requests'), $result['error']);
        $this->assertArrayNotHasKey('current_weather', $result);
    }

    // =========================================================================
    // 4. A hiányzó API-kulcs útja
    // =========================================================================

    public function test_a_missing_api_key_is_caught_and_surfaces_as_an_empty_error_message(): void
    {
        $result = pwbs_weather_api_call('Szeged', 'HU');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(
            '',
            $result['error'],
            'Az InvalidConfiguration üzenet nélkül példányosul (WeatherClient.php:61), '
            .'így a helpers.php:158 üres hibaszöveget ad tovább. Mai viselkedés, nem megőrzendő.'
        );

        // A hívó ezen az ágon city_id-t sem kap - lásd a következő tesztet.
        $this->assertArrayNotHasKey('city_id', $result);
        $this->assertSame(0, WeatherCity::count(), 'A kivétel a sor létrehozása előtt száll fel.');
    }

    public function test_the_missing_key_path_never_records_a_last_try_so_the_throttle_never_engages(): void
    {
        $row = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);
        $this->age(
            $row,
            now()->subHours(2)->toDateTimeString(),
            now()->subHours(2)->toDateTimeString()
        );

        pwbs_weather_api_call('Szeged', 'HU');

        // A kivétel a WeatherCity::updateOrCreate() elé esik (helpers.php:110-156),
        // tehát a last_try nem frissül: rosszul konfigurált kulcsnál a 15 perces fék
        // SOHA nem kapcsol be, és minden mentés újrapróbálkozik.
        $this->assertTrue(
            $row->fresh()->last_try->lt(now()->subHour()),
            'A last_try változatlan maradt, tehát a fék nem lép működésbe.'
        );

        $second = pwbs_weather_api_call('Szeged', 'HU');
        $this->assertNotSame(__('group.weather.too_many_requests'), $second['error'] ?? null);
    }

    // =========================================================================
    // 5. A csoportmentés, amit egy hibázó hívás megbénít
    // =========================================================================

    public function test_a_failing_call_wipes_an_existing_city_id_and_blocks_the_save(): void
    {
        // A csoportnak MÁR VAN működő városa - ezt kell a hibázó hívásnak elrontania.
        // Enélkül a teszt akkor is zöld maradna, ha a hívás egyszerűen kikerülne a
        // kódból: "a csoport nem menthető" ugyanis igaz marad, ha a city_id eleve null.
        // (Ezt a rést a kontroll-kísérlet mutatta ki, a TODO 19.1 mintájára.)
        $city = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);

        $group = $this->createGroup(['weather_enabled' => 1, 'city_id' => $city->id]);
        $editor = $this->createUser(['email' => 'weather-save-block@example.test']);
        $this->attachUserToGroup($editor, $group, 'roler', true);

        Livewire::actingAs($editor)
            ->test(GroupEditComponent::class, ['group' => $group->fresh()])
            ->set('state.name', 'Weather Group')
            ->set('state.max_extend_days', 30)
            ->set('state.need_approval', 1)
            ->set('state.min_publishers', 2)
            ->set('state.max_publishers', 4)
            ->set('state.min_time', 60)
            ->set('state.max_time', 180)
            ->set('state.showPhone', 1)
            ->set('state.messages_on', 1)
            ->set('state.messages_write', 1)
            ->set('state.messages_priority', 1)
            ->set('state.weather_enabled', 1)
            // Másik város: nincs rá gyorsítótár-sor, tehát az API-ágra fut, és
            // API-kulcs híján hibát ad.
            ->set('weather.city', 'Debrecen')
            ->set('weather.country', 'HU')
            ->set('days.1.day_number', '1')
            ->set('days.1.start_time', '08:00')
            ->set('days.1.end_time', '10:00')
            ->set('change_date', now()->toDateString())
            ->call('updateGroup')
            // A hibázó hívás city_id = null-t állít (UpdateGroupForm.php:219), a :251
            // pedig required_if:weather_enabled,1-et validál rá. A csoport tehát
            // MENTHETETLEN, és a hiba olyan mezőre esik, aminek nincs beviteli eleme.
            ->assertHasErrors(['city_id']);

        $fresh = $group->fresh();
        $this->assertNotSame('Weather Group', $fresh->name, 'A validáció a teljes mentést megállította.');
        $this->assertSame($city->id, (int) $fresh->city_id, 'Az adatbázisban a régi város maradt.');
    }

    public function test_a_warm_cache_lets_the_group_save_and_writes_city_id(): void
    {
        // A gyorsítótár-találat ága NEM hív API-t (helpers.php:93-98), tehát ez a
        // mentés hálózat nélkül fut végig - és ez az egyetlen teszt, amelyik a
        // pozitív irányból bizonyítja, hogy a hívási hely (UpdateGroupForm.php:215)
        // egyáltalán létezik. Ha a hívást kivennénk, a city_id null maradna, és ez
        // a teszt bukna.
        $city = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);

        $group = $this->createGroup();
        $editor = $this->createUser(['email' => 'weather-save-ok@example.test']);
        $this->attachUserToGroup($editor, $group, 'roler', true);

        Livewire::actingAs($editor)
            ->test(GroupEditComponent::class, ['group' => $group->fresh()])
            ->set('state.name', 'Warm Cache Group')
            ->set('state.max_extend_days', 30)
            ->set('state.need_approval', 1)
            ->set('state.min_publishers', 2)
            ->set('state.max_publishers', 4)
            ->set('state.min_time', 60)
            ->set('state.max_time', 180)
            ->set('state.showPhone', 1)
            ->set('state.messages_on', 1)
            ->set('state.messages_write', 1)
            ->set('state.messages_priority', 1)
            ->set('state.weather_enabled', 1)
            ->set('weather.city', 'Szeged')
            ->set('weather.country', 'HU')
            ->set('days.1.day_number', '1')
            ->set('days.1.start_time', '08:00')
            ->set('days.1.end_time', '10:00')
            ->set('change_date', now()->toDateString())
            ->call('updateGroup')
            ->assertHasNoErrors();

        $fresh = $group->fresh();
        $this->assertSame($city->id, (int) $fresh->city_id, 'A találat city_id-je a csoportra íródott.');
        $this->assertSame(1, (int) $fresh->weather_enabled);
        $this->assertSame('Warm Cache Group', $fresh->name);
    }

    // =========================================================================
    // 6. Az éles oszlopalak
    // =========================================================================

    public function test_what_the_helper_writes_reads_back_from_the_cast_as_a_string_not_an_array(): void
    {
        $row = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);

        // A kettős kódolás következménye: a cast egyszer dekódol, és a JSON-stringet
        // adja vissza, nem a tömböt.
        $this->assertIsString($row->fresh()->current_weather);

        // A factory viszont TÖMBÖT ír, tehát onnan tömb jön vissza - ezt állítja a
        // ModelFactoryTest:197-204 is. A két alak kizárja egymást, és a factory
        // alakja az, amelyik élesben nem fordulhat elő.
        $fromFactory = WeatherCity::factory()->withWeatherData()->create();
        $this->assertIsArray($fromFactory->fresh()->current_weather);
    }
}
