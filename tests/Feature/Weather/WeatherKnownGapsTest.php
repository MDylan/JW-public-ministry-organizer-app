<?php

namespace Tests\Feature\Weather;

use App\Http\Livewire\Events\Events;
use App\Models\Group;
use App\Models\WeatherCity;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * A TODO 20.1 által mért nyolc hiányosság - MIND LEZÁRVA a v1-patch C csomagjában.
 *
 * A fájl eredetileg a hibás viselkedést rögzítette, hogy a javítás pillanatában
 * bukjon, és az a bukás legyen a reviewálható diff - ugyanaz a fegyelem, mint a
 * TODO 14 duplikált-route tripwire-jeinél. Ez a bukás bekövetkezett: mind a nyolc
 * eset megfordult, és a fájl most azt őrzi, hogy a rés ne nyíljon ki újra.
 *
 * A név szándékosan változatlan: a git történetben így követhető, hogy melyik
 * állítás melyik hiányosság helyére lépett.
 */
class WeatherKnownGapsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['weather' => 1]);
        config(['openweather.api_key' => '']);
    }

    // =========================================================================
    // 1. A frissítési kör - korábban nem létezett
    // =========================================================================

    public function test_a_scheduled_task_refreshes_the_weather_cache(): void
    {
        // Korábban EGYETLEN ütemezett feladat sem frissítette a weather_cities
        // táblát. A sorokba kizárólag akkor került adat, ha egy csoportadmin
        // mentette a csoport űrlapját vagy megnyomta az ellenőrzés gombot - a
        // naptár tiszta olvasó. A publikátorok tehát tetszőlegesen régi
        // előrejelzést láttak, akár hetekig.
        $scheduled = collect(app(Schedule::class)->events())
            ->map(fn ($event) => (string) $event->command)
            ->filter(fn ($command) => stripos($command, 'weather:refresh') !== false);

        $this->assertCount(1, $scheduled, 'Pontosan egy weather:refresh feladat legyen ütemezve.');
    }

    public function test_the_refresh_command_only_visits_cities_that_are_actually_used(): void
    {
        // A keret dönti el a gyakoriságot: az ingyenes szint 1000 hívás/nap, és
        // egy település frissítése 2 hívás. Ezért járjuk be DISTINCT módon csak
        // azokat a városokat, amelyekhez tartozik bekapcsolt csoport.
        $used = WeatherCity::factory()->create(['city' => 'Szeged', 'country' => 'HU']);
        $unused = WeatherCity::factory()->create(['city' => 'Debrecen', 'country' => 'HU']);

        $this->createGroup(['weather_enabled' => 1, 'city_id' => $used->id]);
        $this->createGroup(['weather_enabled' => 0, 'city_id' => $unused->id]);

        // API-kulcs nélkül minden lekérés elbukik, de a last_try így is megkapja
        // az időbélyeget - ezen látszik, melyik várost kereste fel a parancs.
        $this->artisan('weather:refresh')->assertExitCode(0);

        $this->assertNotNull($used->fresh()->last_try, 'A használt várost frissíti.');
        $this->assertNull($unused->fresh()->last_try, 'A kikapcsolt csoport városát nem.');
    }

    // =========================================================================
    // 2. Az őrizetlen dereferálás a naptárban
    // =========================================================================

    public function test_the_calendar_survives_weather_enabled_without_a_city(): void
    {
        // Korábban fatalt dobott: a weather_enabled = 1 önmagában nem jelenti,
        // hogy van város, a `weather` reláció ilyenkor null, és a render()
        // őrizetlenül dereferálta. Ez éppen az az állapot, amit a régi hibaág
        // állított elő, amikor egy sikertelen API-hívás nullázta a city_id-t.
        $group = $this->createGroup(['weather_enabled' => 1, 'city_id' => null]);

        $user = $this->createUser(['email' => 'weather-nocity@example.test']);
        $this->attachUserToGroup($user, $group, 'member', true);
        session(['groupId' => $group->id]);

        Livewire::actingAs($user)
            ->test(Events::class, ['year' => 2026, 'month' => 8])
            ->assertOk();
    }

    public function test_the_calendar_survives_a_forecast_blob_without_a_list_key(): void
    {
        // A másik őrizetlen pont: egy csonka vagy hibás válasz blobjában
        // hiányozhat a `list` kulcs, és a korábbi count() ezen is elhasalt.
        $city = WeatherCity::create([
            'city'             => 'Szeged',
            'country'          => 'HU',
            'current_weather'  => ['main' => ['temp' => 21.5], 'name' => 'Szeged'],
            'forecast_weather' => ['cod' => '429', 'message' => 'rate limited'],
            'last_try'         => now(),
        ]);

        $group = $this->createGroup(['weather_enabled' => 1, 'city_id' => $city->id]);

        $user = $this->createUser(['email' => 'weather-nolist@example.test']);
        $this->attachUserToGroup($user, $group, 'member', true);
        session(['groupId' => $group->id]);

        Livewire::actingAs($user)
            ->test(Events::class, ['year' => 2026, 'month' => 8])
            ->assertOk();
    }

    // =========================================================================
    // 3. Az idegen kulcs, ami néma no-op volt
    // =========================================================================

    public function test_the_groups_city_id_column_has_a_foreign_key(): void
    {
        // A 2024_12_04_194500 migráció ->constrained()-t hívott
        // unsignedBigInteger() után; az viszont a foreignId() párja, tehát néma
        // no-op. Idegen kulcs soha nem jött létre.
        $foreignKeys = DB::select(
            'SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "groups"
                AND COLUMN_NAME = "city_id"
                AND REFERENCED_TABLE_NAME IS NOT NULL'
        );

        $this->assertCount(1, $foreignKeys, 'A groups.city_id-nak idegen kulcsa kell legyen.');
        $this->assertSame('weather_cities', $foreignKeys[0]->REFERENCED_TABLE_NAME);
    }

    public function test_deleting_a_city_nulls_the_reference_instead_of_orphaning_it(): void
    {
        // Az onDelete('set null') érdemi hatása: egy törölt település nem hagy
        // maga után árva hivatkozást, amin a naptár később elhasalna.
        $city = WeatherCity::factory()->create(['city' => 'Szeged', 'country' => 'HU']);
        $group = $this->createGroup(['weather_enabled' => 1, 'city_id' => $city->id]);

        $city->delete();

        $this->assertNull($group->fresh()->city_id);
    }

    // =========================================================================
    // 4. A törött reláció
    // =========================================================================

    public function test_the_weather_city_groups_relation_points_at_the_real_column(): void
    {
        // A hasMany(Group::class) alapértelmezés szerint `weather_city_id`-t
        // keresett a groups táblában; ilyen oszlop nincs, a valódi neve
        // `city_id`. A reláció minden hívása hibára futott volna - használat
        // híján ez eddig nem derült ki.
        $city = WeatherCity::factory()->create(['city' => 'Szeged', 'country' => 'HU']);
        $group = $this->createGroup(['weather_enabled' => 1, 'city_id' => $city->id]);
        $this->createGroup(['weather_enabled' => 0, 'city_id' => null]);

        $related = $city->groups()->get();

        $this->assertCount(1, $related);
        $this->assertTrue($related->first()->is($group));
    }

    // =========================================================================
    // 5. Az országkód, ami csak az egyik végpontra ment el
    // =========================================================================

    public function test_both_endpoints_take_a_country_code(): void
    {
        // A csomag `get3HourlyByCity(string $city)` szignatúrája EGY paramétert
        // fogadott, a hívó viszont kettőt adott át: az 5 napos előrejelzés
        // országkód NÉLKÜL oldódott fel, a jelenlegi időjárás viszont vele.
        //
        // A tényleges kimenő kérést az OpenWeatherClientTest méri Http::fake()-kel;
        // itt a szignatúra-szerződést rögzítjük, mert ez volt a hiba forrása.
        foreach (['currentByCity', 'forecastByCity'] as $method) {
            $reflection = new \ReflectionMethod(\App\Support\Weather\OpenWeatherClient::class, $method);

            $this->assertSame(
                2,
                $reflection->getNumberOfParameters(),
                $method.': a település ÉS az országkód is paraméter.'
            );
            $this->assertSame('country', $reflection->getParameters()[1]->getName());
        }
    }

    // =========================================================================
    // 6. A nyelv és a fordítások
    // =========================================================================

    public function test_the_api_language_is_not_hardwired_to_english(): void
    {
        // A config korábban bedrótozott 'en'-t adott, ezért a magyar és a német
        // felületen is angol időjárás-leírások jelentek meg.
        $this->assertSame('', (string) config('openweather.lang'), 'Alapból üres: a lokál dönt.');
    }

    public function test_the_weather_translations_exist_in_every_maintained_locale(): void
    {
        // A group.weather.* blokk KIZÁRÓLAG hu-ban létezett, tehát en és de
        // alatt nyers kulcsok jelentek meg a felületen.
        $keys = array_keys(__('group.weather', [], 'hu'));
        $this->assertNotEmpty($keys);

        foreach (['en', 'de'] as $locale) {
            $translated = __('group.weather', [], $locale);

            $this->assertIsArray($translated, $locale.': hiányzik a weather blokk.');

            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $translated, $locale.': hiányzó kulcs - '.$key);
                $this->assertNotSame(
                    __('group.weather.'.$key, [], 'hu'),
                    null,
                    $locale.': '.$key
                );
            }
        }
    }

    // =========================================================================
    // 7. A számláló, amit senki nem olvasott
    // =========================================================================

    public function test_the_monthly_call_counter_is_gone(): void
    {
        // A weather_monthly_call sort minden sikeres lekérés növelte, és SENKI
        // nem olvasta - egy fölösleges updateOrCreate DB-írás minden hívásra.
        // A döntés: törölni az írást.
        $writers = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()
                && $file->getExtension() === 'php'
                && str_contains(file_get_contents($file->getPathname()), 'weather_monthly_call')) {
                $writers[] = $file->getPathname();
            }
        }

        $this->assertSame([], $writers, 'A weather_monthly_call számlálót senki nem írhatja.');
    }

    // =========================================================================
    // 8. A csomag, ami eltűnt
    // =========================================================================

    public function test_the_vendor_package_is_gone(): void
    {
        // A két fogyasztott végpont az App\Support\Weather névtérbe költözött.
        // A csomag azért ment, mert a WeatherClientje konténer-kötés nélkül
        // példányosított Guzzle klienst, tehát a sikeres ág nem volt tesztelhető.
        $this->assertFalse(class_exists('RakibDevs\\Weather\\Weather'));

        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $this->assertArrayNotHasKey('rakibdevs/openweather-laravel-api', $composer['require']);
    }
}
