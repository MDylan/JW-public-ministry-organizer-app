<?php

namespace Tests\Feature\Setup;

use Illuminate\Support\Facades\DB;

/**
 * TODO 12: the installer's database step - and the Laravel 11 blocker.
 *
 * The success branch is DELIBERATELY not tested: configure() runs migrate:fresh
 * on the given connection and writes .env. A mistaken test would therefore
 * wipe a database. What IS measurable - and this is exactly the most valuable
 * part - is the databaseHasData() branch: it calls getDoctrineSchemaManager(),
 * which goes away in Laravel 11 (roadmap TODO 66).
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
    // 2. Protection of existing data - this is where getDoctrineSchemaManager() runs
    // =========================================================================

    public function test_an_existing_database_is_not_overwritten_without_consent(): void
    {
        // databaseHasData() lists the tables through doctrine/dbal's schema
        // manager. If it finds anything, configure() turns back WITHOUT
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

    public function test_the_doctrine_schema_manager_is_still_reachable_on_this_version(): void
    {
        // Explicit note that in Phase 8 THIS call is the one that will disappear:
        // Laravel 11 drops the doctrine/dbal integration, and with it the
        // getDoctrineSchemaManager() method. This test will fail at the moment
        // of the replacement, exactly where it should.
        $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();

        $this->assertContains('users', $tables);
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
