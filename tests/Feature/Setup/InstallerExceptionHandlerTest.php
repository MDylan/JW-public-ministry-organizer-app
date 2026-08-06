<?php

namespace Tests\Feature\Setup;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;

/**
 * TODO 12: az Exceptions\Handler telepítő-ága.
 *
 * A Handler::register() (:56) minden QueryException-t elkap, és ha nincs
 * installed.txt, a telepítő nyitóoldalára visz. Ez az, ami egy friss
 * kicsomagolás után - amikor még nincs adatbázis - a felhasználót a setupba
 * tereli ahelyett, hogy nyers hibát mutatna.
 *
 * A MÁSIK ÁG SZÁNDÉKOSAN NINCS TESZTELVE. Telepített állapotban a handler
 * dd($e->getMessage())-et hív, a dd() pedig exit-tel zár - egy ilyen teszt a
 * PHPUnit folyamatát ölné meg. A viselkedés így is rögzítendő: éles üzemben egy
 * adatbázishiba nyers hibaüzenetet dob a böngészőbe, hibaoldal és naplózás
 * nélkül. Külön javítási tétel a roadmapben.
 */
class InstallerExceptionHandlerTest extends SetupTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/__test/query-exception', function () {
            throw new QueryException(
                'select * from nem_letezo_tabla',
                [],
                new \PDOException('SQLSTATE[42S02]: Base table or view not found')
            );
        });
    }

    public function test_a_query_exception_sends_the_visitor_to_the_installer(): void
    {
        $this->get('/__test/query-exception')
            ->assertRedirect(route('setup.welcome'));
    }

    public function test_the_redirect_target_exists_precisely_because_the_sentinel_is_missing(): void
    {
        // A két feltétel ugyanarra a fájlra épül: a routes/web.php:78 a
        // route-ot regisztrálja, a Handler:57 pedig ide irányít. Ha a sentinel
        // megjelenne, a handler egy nem létező útvonalra próbálna irányítani -
        // ezért is fontos, hogy a két ág mindig együtt mozogjon.
        $this->assertTrue(Route::has('setup.welcome'));

        $this->get('/__test/query-exception')->assertRedirect(route('setup.welcome'));
    }
}
