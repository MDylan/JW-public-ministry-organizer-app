<?php

namespace Tests\Feature\Setup;

use Illuminate\Database\QueryException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12.1: az Exceptions\Handler telepített ága.
 *
 * A párja az InstallerExceptionHandlerTest, ami a sentinel NÉLKÜLI ágat méri
 * (ott a telepítőbe irányít). Ez a fájl szándékosan a normál FeatureTestCase-re
 * épül, tehát a valódi storage van érvényben, benne az installed.txt-vel.
 *
 * Ez az ág korábban dd()-vel zárt, ami exit-tel megölte volna a PHPUnit
 * folyamatát - ezért volt lefedhetetlen. A dd() eltávolítása után a kivétel a
 * beépített kezelőre esik vissza.
 */
class InstalledExceptionHandlerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/__test/query-exception', function () {
            throw new QueryException(
                'select * from nem_letezo_tabla',
                [],
                new \PDOException('SQLSTATE[42S02]: Base table or view not found: nem_letezo_tabla')
            );
        });

        Route::middleware('web')->get('/__test/missing-app-key', function () {
            throw new MissingAppKeyException();
        });
    }

    public function test_the_sentinel_is_present_for_these_tests(): void
    {
        // Előfeltétel: enélkül a handler a telepítő ágára menne, és ez a fájl
        // mást mérne, mint amit a neve ígér.
        $this->assertTrue(Storage::exists('installed.txt'));
    }

    // =========================================================================
    // QueryException
    // =========================================================================

    public function test_a_query_exception_produces_a_server_error_response(): void
    {
        $this->get('/__test/query-exception')->assertStatus(500);
    }

    public function test_it_does_not_redirect_to_the_installer(): void
    {
        // A telepítő útvonalai telepített állapotban nem is léteznek, tehát egy
        // ide irányított átirányítás sehová nem vinne.
        $response = $this->get('/__test/query-exception');

        $this->assertNull($response->headers->get('Location'));
    }

    public function test_the_raw_database_message_does_not_reach_the_browser(): void
    {
        // Ez méri a tényleges nyereséget. A dd() az APP_DEBUG értékétől
        // FÜGGETLENÜL kiírta a nyers üzenetet; a normál kezelő debug nélkül az
        // errors/500 nézetet rendereli. A tesztkörnyezet APP_DEBUG=true, ezért
        // itt kapcsoljuk ki - éles telepítés a .env.example szerint eleve
        // false-szal indul.
        config(['app.debug' => false]);

        $response = $this->get('/__test/query-exception');

        $response->assertStatus(500);
        $response->assertDontSee('nem_letezo_tabla');
        $response->assertDontSee('SQLSTATE');
    }

    public function test_the_exception_is_still_reported(): void
    {
        // A roadmap eredeti megfogalmazása szerint a dd() miatt "no logging"
        // volt - ez tévedés. A Pipeline::handleException() előbb hívja a
        // report()-ot, csak utána a render()-t, az üres reportable() visszahívás
        // pedig null-t ad vissza, nem false-ot, tehát a beépített naplózás fut.
        // Ez a teszt rögzíti, hogy ez a javítás után is így marad.
        Log::spy();

        $this->get('/__test/query-exception');

        Log::shouldHaveReceived('error')->once();
    }

    // =========================================================================
    // MissingAppKeyException
    // =========================================================================

    public function test_a_missing_app_key_does_not_touch_the_environment_file(): void
    {
        // ELŐFELTÉTEL, nem dísz: a handler másoló ága csak akkor fut, ha a .env
        // NEM létezik - olyankor létrehozná .env.example-ből és kulcsot
        // generálna. Ha ez az állítás elbukik, a teszt megállt, mielőtt a
        // fejlesztő környezetébe írna.
        $this->assertFileExists(base_path('.env'));

        $before = file_get_contents(base_path('.env'));

        $this->get('/__test/missing-app-key')->assertStatus(500);

        $this->assertSame($before, file_get_contents(base_path('.env')));
    }
}
