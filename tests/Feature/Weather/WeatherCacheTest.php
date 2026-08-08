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

        // Biztonsági rögzítés, nem díszlet: üres kulcsnál az OpenWeatherClient
        // dob, MIELŐTT bármilyen kapcsolat nyílna. Ez garantálja, hogy ez a fájl
        // akkor sem indít valódi hálózati kérést, ha valaki később
        // OPENWEATHER_API_KEY-t tesz a .env-be. A sikeres ágat a
        // WeatherClientTest fedi, Http::fake()-kel.
        config(['openweather.api_key' => '']);
    }

    /**
     * Gyorsítótár-sor az éles alakban.
     *
     * A v1-patch C csomagja előtt a mentés KÉTSZER kódolt: kézi json_encode() a
     * modell `json` castja MELLETT, tehát az oszlopban JSON-ba csomagolt
     * JSON-sztring állt, és minden olvasónak kézzel kellett dekódolnia. A cast
     * innentől az egyetlen kódolási pont, ezért a fixture nyers tömböt ír.
     */
    private function cacheRow(string $city, string $country, ?array $current = null, ?array $forecast = null): WeatherCity
    {
        return WeatherCity::create([
            'city'             => $city,
            'country'          => $country,
            'current_weather'  => $current,
            'forecast_weather' => $forecast,
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

        // A visszaadott érték TÖMB - a `json` cast adja így, egyetlen
        // dekódolással. Korábban a mentés kétszer kódolt, ezért a helpernek
        // kézzel kellett még egyszer dekódolnia.
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

        // A v1-patch C óta a fék MELLETT a legutóbbi ismert adatot is
        // visszakapjuk, ha van. Egy órás késésű előrejelzés használhatóbb, mint
        // a semmi, és a naptár így nem ürül ki csak azért, mert épp fékezünk.
        $this->assertSame(21.5, $result['current_weather']['main']['temp']);
    }

    // =========================================================================
    // 4. A hiányzó API-kulcs útja
    // =========================================================================

    public function test_a_missing_api_key_surfaces_as_a_real_message(): void
    {
        // MEGFORDÍTVA a v1-patch C csomagjával.
        //
        // A csomag `InvalidConfiguration` kivétele ÜZENET NÉLKÜL példányosult,
        // ezért a helper `['error' => '']`-t adott vissza: a csoportadmin üres
        // hibapanelt kapott, és semmi nem árulta el, hogy a kulcs hiányzik. A
        // WeatherException minden gyártó metódusa megnevezi az okot.
        $result = pwbs_weather_api_call('Szeged', 'HU');

        $this->assertArrayHasKey('error', $result);
        $this->assertNotSame('', $result['error']);
        $this->assertStringContainsString('OPENWEATHER_API_KEY', $result['error']);

        // A hívó city_id-t IS kap: a sikertelen kísérlet is létrehozza a sort,
        // különben a csoport mentése blokkolódna - lásd lentebb.
        $this->assertArrayHasKey('city_id', $result);
        $this->assertSame(1, WeatherCity::count());
    }

    public function test_the_missing_key_path_records_a_last_try_so_the_throttle_engages(): void
    {
        // MEGFORDÍTVA a v1-patch C csomagjával.
        //
        // A kivétel korábban a WeatherCity::updateOrCreate() ELÉ esett, tehát a
        // last_try nem frissült: rosszul konfigurált kulcsnál a 15 perces fék
        // SOHA nem kapcsolt be, és minden oldalletöltés újrapróbálkozott.
        $row = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);
        $this->age(
            $row,
            now()->subHours(2)->toDateTimeString(),
            now()->subHours(2)->toDateTimeString()
        );

        pwbs_weather_api_call('Szeged', 'HU');

        $this->assertTrue(
            $row->fresh()->last_try->gt(now()->subMinute()),
            'A sikertelen kísérlet is időbélyeget kap.'
        );

        $second = pwbs_weather_api_call('Szeged', 'HU');
        $this->assertSame(__('group.weather.too_many_requests'), $second['error'] ?? null);
    }

    // =========================================================================
    // 5. A csoportmentés, amit egy hibázó hívás megbénít
    // =========================================================================

    public function test_a_failing_call_no_longer_blocks_the_group_save(): void
    {
        // MEGFORDÍTVA a v1-patch C csomagjával.
        //
        // A hibaág korábban NULLÁZTA a city_id-t (UpdateGroupForm.php:219),
        // miközben a :251 required_if:weather_enabled,1-et validál rá. Aki tehát
        // bekapcsolta az időjárást és az API épp nem válaszolt, EGYÁLTALÁN nem
        // tudta menteni a csoportot - a hiba ráadásul olyan mezőre esett,
        // aminek nincs beviteli eleme az űrlapon. Egy külső szolgáltatás
        // elérhetetlensége blokkolta a teljes űrlapot, olyan mezőkkel együtt,
        // amiknek semmi közük az időjáráshoz.
        //
        // A sikertelen kísérlet is létrehozza a weather_cities sort, tehát van
        // city_id, és a mentés mehet; a hibát a felhasználó a
        // weather_messages panelen látja.
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
            ->assertHasNoErrors();

        $fresh = $group->fresh();
        $this->assertSame('Weather Group', $fresh->name, 'A mentés végigment.');
        $this->assertNotNull($fresh->city_id, 'A city_id nem nullázódik egy hibás lekéréstől.');
        $this->assertNotSame($city->id, (int) $fresh->city_id, 'És az új városra mutat.');
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

    public function test_what_the_helper_writes_reads_back_from_the_cast_as_an_array(): void
    {
        // MEGFORDÍTVA a v1-patch C csomagjával.
        //
        // A mentés KÉTSZER kódolt: kézi json_encode() a modell `json` castja
        // MELLETT. Az oszlopban ezért JSON-ba csomagolt JSON-sztring állt, a cast
        // egyszer dekódolt, és sztringet adott vissza - minden olvasónak kézzel
        // kellett még egyszer dekódolnia (helpers.php:96-97, Events.php:297,300).
        // A cast innentől az egyetlen kódolási pont.
        $row = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);

        $this->assertIsArray($row->fresh()->current_weather);

        // A factory ugyanezt az alakot írja - korábban a kettő KIZÁRTA egymást:
        // a factory tömböt, a termelés kétszer kódolt sztringet, tehát a
        // ModelFactoryTest olyan alakot állított, amit éles kód nem tudott
        // előállítani. Innentől egyetlen alak van.
        $fromFactory = WeatherCity::factory()->withWeatherData()->create();
        $this->assertIsArray($fromFactory->fresh()->current_weather);
        $this->assertIsArray($fromFactory->fresh()->forecast_weather);
        $this->assertArrayHasKey('list', $fromFactory->fresh()->forecast_weather);
    }
}
