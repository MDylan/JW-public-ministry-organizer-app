<?php

namespace Tests\Feature\Setup;

use App\Http\Controllers\Setup\DatabaseController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;

/**
 * TODO 12: the installer's database step - and the Laravel 11 blocker.
 *
 * The success branch is DELIBERATELY not tested: configure() runs migrate:fresh
 * on the given connection and writes .env. A mistaken test would therefore
 * wipe a database. What IS measurable - and this is exactly the most valuable
 * part - is the databaseHasData() branch. It used to call doctrine/dbal's
 * getDoctrineSchemaManager(), which Laravel 11 removes; TODO 57.1 moved it to
 * the framework's Schema::getTableListing() ahead of the hop.
 */
class SetupDatabaseTest extends SetupTestCase
{
    /**
     * The credentials of the running test database: this is guaranteed to
     * exist and to be full of tables, so the "already has data" branch is
     * guaranteed to run on it.
     */
    private function existingDatabaseCredentials(): array
    {
        $mysql = config('database.connections.mysql');

        return [
            'db_host' => $mysql['host'],
            'db_port' => (string) $mysql['port'],
            'db_name' => $mysql['database'],
            'db_user' => $mysql['username'],
            'db_password' => $mysql['password'],
        ];
    }

    /**
     * An unreachable connection: port 1 rejects immediately, so there is no
     * long timeout - and it can under no circumstances reach an existing database.
     */
    private function unreachableCredentials(): array
    {
        return [
            'db_host' => '127.0.0.1',
            'db_port' => '1',
            'db_name' => 'kozter_no_such_database',
            'db_user' => 'nobody',
            'db_password' => '',
        ];
    }

    // =========================================================================
    // 1. Validation
    // =========================================================================

    public function test_the_required_fields_are_validated(): void
    {
        $this->post(route('setup.save-database'), [])
            ->assertSessionHasErrors(['db_host', 'db_port', 'db_name', 'db_user']);
    }

    public function test_the_port_must_be_numeric(): void
    {
        $this->post(route('setup.save-database'), array_merge(
            $this->existingDatabaseCredentials(),
            ['db_port' => 'harom-ezer']
        ))->assertSessionHasErrors('db_port');
    }

    public function test_an_empty_password_is_accepted_by_the_validator(): void
    {
        // db_password is nullable - this is a deliberate decision, because of
        // local development MySQL instances' passwordless root user.
        $this->post(route('setup.save-database'), array_merge(
            $this->existingDatabaseCredentials(),
            ['db_password' => '']
        ))->assertSessionDoesntHaveErrors('db_password');
    }

    // =========================================================================
    // 2. Protection of existing data - this is where the table listing runs
    // =========================================================================

    public function test_an_existing_database_is_not_overwritten_without_consent(): void
    {
        // databaseHasData() lists the tables through the schema builder. If
        // it finds anything, configure() turns back WITHOUT
        // MIGRATING - which is why it is safe to measure this on the real test database.
        $response = $this->post(
            route('setup.save-database'),
            $this->existingDatabaseCredentials()
        );

        $response->assertRedirect();
        $response->assertSessionHas('data_present', true);
        $response->assertSessionHas('error_message', trans('setup.database.data_present'));

        // Check that no migration actually ran: our tables are still there.
        $this->assertNotEmpty(
            DB::select('SHOW TABLES LIKE "users"'),
            'A users tábla nem tűnhetett el.'
        );
    }

    public function test_the_data_check_does_not_depend_on_doctrine_dbal(): void
    {
        // REVERSED by TODO 57.1. This case used to call
        // getDoctrineSchemaManager() itself, as a tripwire that would fail
        // the moment Laravel 11 removed it. The guard now lists the tables
        // with the framework's own schema builder, which exists on Laravel 10
        // and every later version, so the tripwire became an assertion that
        // the installer no longer reaches for doctrine/dbal at all. TODO 57
        // removed the package after the hop (DoctrineDbalRemovedTest).
        // Comments stripped: the controller explains the change in words,
        // and only the code may not reach for the package.
        $source = php_strip_whitespace(app_path('Http/Controllers/Setup/DatabaseController.php'));

        $this->assertStringNotContainsString('getDoctrineSchemaManager', $source);
        $this->assertStringNotContainsString('Doctrine', $source);

        // Laravel 12 lists every schema on the server and qualifies the
        // names unless asked otherwise; the controller asks for the current
        // database only (TODO 61), and so does this check.
        $schema = Schema::connection('mysql');

        $this->assertContains('users', $schema->getTableListing($schema->getCurrentSchemaListing(), false));
    }

    public function test_an_empty_database_next_to_other_databases_has_no_data(): void
    {
        // TODO 61: Laravel 12's getTableListing() without a schema lists the
        // tables of EVERY non-system database the server holds. Called that
        // way, the check reported data in a freshly created, empty database
        // whenever the same MySQL server held any other one - the usual case
        // on shared hosting and on Laragon - and the installer refused to
        // continue without the "overwrite" box ticked. Measured here with the
        // test database next door.
        //
        // The database is created and dropped on a connection of its own:
        // CREATE / DROP DATABASE commit implicitly, and on the default
        // connection that would end RefreshDatabase's transaction.
        $empty = 'kozter_testing_setup_empty';
        Config::set('database.connections.setup_probe', config('database.connections.mysql'));
        $probe = DB::connection('setup_probe');
        $probe->statement("DROP DATABASE IF EXISTS `{$empty}`");
        $probe->statement("CREATE DATABASE `{$empty}`");

        try {
            $controller = app(DatabaseController::class);
            $credentials = array_merge($this->existingDatabaseCredentials(), ['db_name' => $empty]);

            (new ReflectionMethod($controller, 'createTempDatabaseConnection'))->invoke($controller, $credentials);

            $this->assertFalse(
                (new ReflectionMethod($controller, 'databaseHasData'))->invoke($controller),
                'An empty database must not count as one with data because another database shares its server.'
            );
        } finally {
            DB::purge('setup');
            $probe->statement("DROP DATABASE IF EXISTS `{$empty}`");
            DB::purge('setup_probe');
        }
    }

    // =========================================================================
    // 3. The error branch
    // =========================================================================

    public function test_an_unreachable_database_reports_the_error_and_returns(): void
    {
        // With an unreachable connection, databaseHasData() swallows the
        // PDOException and returns false, so the process moves on to the
        // migration - which fails, and the catch branch flashes the error.
        $response = $this->post(route('setup.save-database'), array_merge(
            $this->unreachableCredentials(),
            ['overwrite_data' => '1']
        ));

        $response->assertRedirect();
        $this->assertStringStartsWith(
            trans('setup.database.config_error'),
            session('error_message'),
            'A hibaüzenet a fordított előtaggal kezdődik.'
        );
    }

    public function test_a_failed_attempt_leaves_the_default_connection_alone(): void
    {
        // Laravel 11's migrate:fresh switches the default connection to
        // 'setup' around its table wipe and does not switch it back when the
        // wipe throws. Before the controller restored it (TODO 55), the rest
        // of the request - and this test's own RefreshDatabase rollback - ran
        // on the unreachable connection and died with a PDOException.
        $before = DB::getDefaultConnection();

        $this->post(route('setup.save-database'), array_merge(
            $this->unreachableCredentials(),
            ['overwrite_data' => '1']
        ))->assertRedirect();

        $this->assertSame($before, DB::getDefaultConnection());
    }

    public function test_the_failed_attempt_does_not_advance_the_wizard(): void
    {
        // The success branch leads to setup.mail; the failing one must not advance.
        $response = $this->post(route('setup.save-database'), array_merge(
            $this->unreachableCredentials(),
            ['overwrite_data' => '1']
        ));

        $this->assertNotSame(
            route('setup.mail'),
            $response->headers->get('Location')
        );
    }
}
