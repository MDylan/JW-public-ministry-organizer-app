<?php

namespace Tests\Feature\Weather;

use App\Http\Livewire\Events\Events;
use App\Models\WeatherCity;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 20.1: az időjárás-funkció hiányosságai, ahogy MA viselkednek.
 *
 * Ugyanaz a fegyelem, mint a TODO 14 duplikált-route tripwire-jeinél, amelyeket a
 * TODO 26 szándékosan átír, és a TODO 19.1 PendingEmailKnownGapsTest fájljánál: ezek
 * a tesztek nem a helyes viselkedést rögzítik, hanem a hibásat. A javítás pillanatában
 * BUKNIUK KELL, és az a bukás a reviewálható diff.
 *
 * Mindegyik teszt megnevezi, melyik TODO tartozik hozzá.
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
    // TODO 33.7 - a hiányzó frissítési kör
    // =========================================================================

    public function test_gap_nothing_ever_refreshes_the_weather_cache(): void
    {
        $scheduled = collect(app(Schedule::class)->events())
            ->map(fn ($event) => (string) $event->command)
            ->filter(fn ($command) => stripos($command, 'weather') !== false);

        $this->assertCount(
            0,
            $scheduled,
            'Ma egyetlen ütemezett feladat sem frissíti a weather_cities táblát. A sorokba '
            .'KIZÁRÓLAG akkor kerül adat, ha egy csoportadmin menti a csoport űrlapját '
            .'(UpdateGroupForm.php:215) vagy megnyomja az ellenőrzés gombot (:524) - a naptár '
            .'tiszta olvasó. A publikátorok tehát tetszőlegesen régi előrejelzést látnak. '
            .'A TODO 33.7 `weather:refresh` parancsa ezt a tesztet meg fogja buktatni, és '
            .'egyben a SchedulerRegressionTest::EXPECTED_SCHEDULE listáját is bővíteni kell.'
        );
    }

    // =========================================================================
    // TODO 33.7 - az őrizetlen dereferálás
    // =========================================================================

    public function test_gap_the_calendar_fatals_when_weather_is_enabled_without_a_city(): void
    {
        // Pontosan az az állapot, amit egy hibázó API-hívás állít elő:
        // UpdateGroupForm.php:219 city_id = null-t ír, a weather_enabled marad 1.
        $group = $this->createGroup(['weather_enabled' => 1, 'city_id' => null]);
        $user = $this->createUser(['email' => 'weather-gap-null@example.test']);
        $this->attachUserToGroup($user, $group, 'member', true);

        session(['groupId' => $group->id]);

        $this->withoutExceptionHandling();
        $this->expectException(\ErrorException::class);

        // Events.php:296 a null relációt tömbként indexeli.
        Livewire::actingAs($user)->test(Events::class);
    }

    // =========================================================================
    // TODO 33.7 - a soha létre nem jött idegen kulcs
    // =========================================================================

    public function test_gap_the_groups_city_id_column_carries_no_foreign_key(): void
    {
        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['groups', 'city_id']
        );

        $this->assertCount(
            0,
            $constraints,
            'A 2024_12_04_194500_add_city_id_to_groups_table.php:17 a ->constrained()-et '
            .'unsignedBigInteger()-en hívja, ahol az néma no-op: sem idegen kulcs, sem '
            .'onDelete(set null) nem jött létre. Egy törölt weather_cities sor tehát lógó '
            .'groups.city_id-t hagy, ami egyenesen a fenti naptár-elhasalásba fut.'
        );
    }

    // =========================================================================
    // TODO 33.7 - a törött reláció
    // =========================================================================

    public function test_gap_the_weather_city_groups_relation_points_at_a_missing_column(): void
    {
        $city = WeatherCity::create(['city' => 'Szeged', 'country' => 'HU']);

        // A WeatherCity::groups() hasMany(Group::class), tehát a konvenció szerinti
        // groups.weather_city_id oszlopot keresi - a valódi oszlop viszont
        // groups.city_id (lásd Group::weather()). Törött csonk, amit soha senki nem hív.
        $this->expectException(QueryException::class);

        $city->groups()->get();
    }

    // =========================================================================
    // TODO 33.6 - az elnyelt argumentum
    // =========================================================================

    public function test_gap_the_forecast_is_requested_without_the_country_code(): void
    {
        $signature = new ReflectionMethod(\RakibDevs\Weather\Weather::class, 'get3HourlyByCity');

        $this->assertSame(
            1,
            $signature->getNumberOfParameters(),
            'A csomag get3HourlyByCity() metódusa EGY paramétert fogad (Weather.php:61).'
        );

        $helper = file_get_contents(base_path('app/Helpers/helpers.php'));

        $this->assertStringContainsString(
            'get3HourlyByCity($city, $country)',
            $helper,
            'A hívási hely viszont KETTŐT ad át (helpers.php:112). PHP a userland függvények '
            .'felesleges argumentumát némán eldobja, tehát az 5 napos előrejelzés országkód '
            .'NÉLKÜL oldódik fel, míg a jelenlegi időjárás (:113) tartalmazza azt - a "Szeged" '
            .'és a "Szeged, HU" pedig különböző városokat adhat vissza.'
        );
    }

    // =========================================================================
    // TODO 33.6 / 33.7 - a lokalizáció
    // =========================================================================

    public function test_gap_the_api_language_is_hardwired_to_english(): void
    {
        app()->setLocale('hu');

        $this->assertSame(
            'en',
            config('openweather.lang'),
            'A config/openweather.php:55 a lang-ot env-ből olvassa `en` alapértékkel, és soha '
            .'nem köti az app()->getLocale()-hoz. Az OpenWeather `description` mezője tehát '
            .'angolul jön vissza, és mindkét widget nyersen írja ki.'
        );
    }

    public function test_gap_the_weather_translations_exist_only_in_hungarian(): void
    {
        $key = 'group.weather.humidity';

        $this->assertNotSame($key, __($key, [], 'hu'), 'Magyarul le van fordítva.');

        // A 22 lokálkönyvtárból mindössze a de, az en és a hu tartalmaz group.php-t,
        // és a weather blokkot csak a hu hordozza. A másik kettő alatt a widget NYERS
        // kulcsot rajzol ki.
        $this->assertSame($key, __($key, [], 'en'));
        $this->assertSame($key, __($key, [], 'de'));
    }

    // =========================================================================
    // TODO 33.7 - a kvótaszámláló, aminek nincs olvasója
    // =========================================================================

    public function test_gap_the_monthly_call_counter_is_written_but_never_read(): void
    {
        $occurrences = [];

        foreach (['app', 'resources/views', 'routes', 'database'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($directory))
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php'
                    && str_contains(file_get_contents($file->getPathname()), 'weather_monthly_call')) {
                    $occurrences[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertSame(
            ['app'.DIRECTORY_SEPARATOR.'Helpers'.DIRECTORY_SEPARATOR.'helpers.php'],
            $occurrences,
            'A weather_monthly_call számlálót egyedül a helpers.php:130-137 írja, és SENKI nem '
            .'olvassa - a tervezett kvótakijelző sosem készült el. Vagy kapjon felületet, vagy '
            .'tűnjön el az írás (TODO 33.7).'
        );
    }
}
