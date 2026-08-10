<?php

namespace Tests\Feature\Setup;

use Illuminate\Database\QueryException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12.1: the installed branch of Exceptions\Handler.
 *
 * Its counterpart is InstallerExceptionHandlerTest, which measures the branch
 * WITHOUT the sentinel (there it redirects into the installer). This file
 * deliberately builds on the normal FeatureTestCase, so the real storage is
 * in effect, with installed.txt in it.
 *
 * This branch previously ended with dd(), which would have killed the
 * PHPUnit process via exit - which is why it was uncoverable. After removing
 * the dd(), the exception falls back to the built-in handler.
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
        // Prerequisite: without this, the handler would go to the installer
        // branch, and this file would measure something other than what its name promises.
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
        // The installer's routes do not even exist in the installed state, so
        // a redirect to there would lead nowhere.
        $response = $this->get('/__test/query-exception');

        $this->assertNull($response->headers->get('Location'));
    }

    public function test_the_raw_database_message_does_not_reach_the_browser(): void
    {
        // This measures the actual gain. dd() printed the raw message
        // REGARDLESS of the APP_DEBUG value; the normal handler renders the
        // errors/500 view without debug. The test environment has
        // APP_DEBUG=true, so we switch it off here - a production deployment,
        // per .env.example, starts with false by default anyway.
        config(['app.debug' => false]);

        $response = $this->get('/__test/query-exception');

        $response->assertStatus(500);
        $response->assertDontSee('nem_letezo_tabla');
        $response->assertDontSee('SQLSTATE');
    }

    public function test_the_exception_is_still_reported(): void
    {
        // Per the roadmap's original wording, dd() supposedly meant "no
        // logging" - this was a mistake. Pipeline::handleException() calls
        // report() first, only then render(), and the empty reportable()
        // callback returns null, not false, so the built-in logging runs.
        // This test records that this stays true after the fix too.
        Log::spy();

        $this->get('/__test/query-exception');

        Log::shouldHaveReceived('error')->once();
    }

    // =========================================================================
    // MissingAppKeyException
    // =========================================================================

    public function test_a_missing_app_key_does_not_touch_the_environment_file(): void
    {
        // PREREQUISITE, not decoration: the handler's copying branch only runs
        // if .env does NOT exist - in that case it would create it from
        // .env.example and generate a key. If this assertion fails, the test
        // stopped before writing into the developer's environment.
        $this->assertFileExists(base_path('.env'));

        $before = file_get_contents(base_path('.env'));

        $this->get('/__test/missing-app-key')->assertStatus(500);

        $this->assertSame($before, file_get_contents(base_path('.env')));
    }
}
