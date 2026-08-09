<?php

namespace Tests\Feature\Setup;

use Illuminate\Support\Facades\DB;

/**
 * TODO 12: a telepítő adatbázis-lépése - és a Laravel 11-es blokkoló.
 *
 * A sikerág SZÁNDÉKOSAN nincs tesztelve: a configure() migrate:fresh-t futtat a
 * megadott kapcsolaton, és .env-et ír. Egy elhibázott teszt tehát adatbázist
 * ürítene. Ami viszont mérhető - és pont ez a legértékesebb -, az a
 * databaseHasData() ág: ez hívja a getDoctrineSchemaManager()-t, ami a
 * Laravel 11-ben megszűnik (roadmap TODO 66).
 */
class SetupDatabaseTest extends SetupTestCase
{
    /**
     * A futó teszt-adatbázis adatai: ez garantáltan létezik és tele van
     * táblákkal, tehát a "van már benne adat" ág biztosan lefut rajta.
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
     * Elérhetetlen kapcsolat: az 1-es port azonnal elutasít, tehát nincs
     * hosszú időtúllépés - és semmiképp nem érhet el létező adatbázist.
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
    // 1. Validáció
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
        // A db_password nullable - a helyi fejlesztői MySQL-ek jelszó nélküli
        // root felhasználója miatt ez tudatos döntés.
        $this->post(route('setup.save-database'), array_merge(
            $this->existingDatabaseCredentials(),
            ['db_password' => '']
        ))->assertSessionDoesntHaveErrors('db_password');
    }

    // =========================================================================
    // 2. A meglévő adat védelme - itt fut a getDoctrineSchemaManager()
    // =========================================================================

    public function test_an_existing_database_is_not_overwritten_without_consent(): void
    {
        // A databaseHasData() a doctrine/dbal séma-managerén keresztül listázza
        // a táblákat. Ha talál bármit, a configure() MIGRÁCIÓ NÉLKÜL fordul
        // vissza - ezért biztonságos ezt a valódi teszt-adatbázison mérni.
        $response = $this->post(
            route('setup.save-database'),
            $this->existingDatabaseCredentials()
        );

        $response->assertRedirect();
        $response->assertSessionHas('data_present', true);
        $response->assertSessionHas('error_message', trans('setup.database.data_present'));

        // Ellenőrzés, hogy tényleg nem futott migráció: a tábláink megvannak.
        $this->assertNotEmpty(
            DB::select('SHOW TABLES LIKE "users"'),
            'A users tábla nem tűnhetett el.'
        );
    }

    public function test_the_doctrine_schema_manager_is_still_reachable_on_this_version(): void
    {
        // Explicit, hogy a Phase 8-ban EZ a hívás fog eltűnni: a Laravel 11
        // kidobja a doctrine/dbal integrációt, vele a getDoctrineSchemaManager()
        // metódust is. Ez a teszt a lecserélés pillanatában fog megbukni,
        // pontosan ott, ahol kell.
        $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();

        $this->assertContains('users', $tables);
    }

    // =========================================================================
    // 3. A hibaág
    // =========================================================================

    public function test_an_unreachable_database_reports_the_error_and_returns(): void
    {
        // Elérhetetlen kapcsolatnál a databaseHasData() PDOException-t nyel és
        // false-t ad, tehát a folyamat továbbmegy a migrációra - ami elszáll,
        // és a catch ág flash-eli a hibát.
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
        // A sikeres ág a setup.mail-re visz; a hibás nem mehet tovább.
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
