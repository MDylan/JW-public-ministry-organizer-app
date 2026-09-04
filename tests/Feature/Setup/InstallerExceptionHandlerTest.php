<?php

namespace Tests\Feature\Setup;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;

/**
 * TODO 12: the installer branch of Exceptions\Handler.
 *
 * Handler::register() (:56) catches every QueryException, and if there is no
 * installed.txt, redirects to the installer's opening page. This is what,
 * after a fresh unpacking - when there is no database yet - steers the user
 * into setup instead of showing a raw error.
 *
 * The other branch - installed state, i.e. an existing sentinel - is in
 * InstalledExceptionHandlerTest. That was previously uncoverable, because the
 * handler ended with dd(), which would have killed the PHPUnit process via
 * exit; TODO 12.1 removed that.
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
        // The two conditions build on the same file: routes/web.php:104
        // registers the route, and Handler:57 redirects here. If the sentinel
        // appeared, the handler would try to redirect to a non-existent route -
        // which is why it is important that the two branches always move together.
        $this->assertTrue(Route::has('setup.welcome'));

        $this->get('/__test/query-exception')->assertRedirect(route('setup.welcome'));
    }
}
