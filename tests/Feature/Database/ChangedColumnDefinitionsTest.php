<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PinTheChangedColumnDefinitions;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 32: the migration that closes the Laravel 11 native change() trap.
 *
 * `2026_09_06_120000_pin_the_changed_column_definitions` restates the final
 * definition of every column that reached its shape through a `->change()`
 * call. Under Laravel 8-10 those calls go through doctrine/dbal, which keeps
 * whatever the migration does not redeclare; the native change() Laravel 11
 * introduces does not. Two of the eight columns would flip from nullable to
 * NOT NULL on a fresh install, and both carry the `encrypted` cast.
 *
 * The migration is the whole defence for that hop, and on every existing host
 * it runs once and changes nothing - so its behaviour has to be asserted here
 * rather than inferred from a green suite.
 *
 * WHY THE REPAIR CASES USE THEIR OWN DATABASE. DDL causes an implicit COMMIT
 * in MySQL, so a single ALTER TABLE inside a test ends the transaction
 * `RefreshDatabase` opened. The rollback afterwards then does nothing, the
 * fixtures this base class seeds survive into the next test, and it dies on a
 * duplicate e-mail address - which is how this was found. The repair cases
 * therefore build a throwaway database, point the default connection at it
 * for the duration, and leave the test database untouched.
 *
 * The migration itself is loaded by path, the way ReanonymizeBackfillTest
 * loads the anonymization backfill: migrations are not autoloaded.
 */
class ChangedColumnDefinitionsTest extends FeatureTestCase
{
    private const PROBE_DATABASE = 'kozter_ddl_probe';

    /**
     * The tables the migration touches, copied into the probe database so
     * that "exactly one statement" also means "it left the others alone".
     */
    private const PROBE_TABLES = [
        'events',
        'group_news_translations',
        'group_posters',
        'group_user',
        'groups',
        'jobs',
        'static_page_translations',
    ];

    private bool $probeBuilt = false;

    protected function tearDown(): void
    {
        if ($this->probeBuilt) {
            $this->serverConnection()->statement('DROP DATABASE IF EXISTS `'.self::PROBE_DATABASE.'`');
            $this->probeBuilt = false;
        }

        parent::tearDown();
    }

    private function migration(): PinTheChangedColumnDefinitions
    {
        require_once database_path(
            'migrations/2026_09_06_120000_pin_the_changed_column_definitions.php'
        );

        return new PinTheChangedColumnDefinitions();
    }

    private function serverConnection(): \Illuminate\Database\Connection
    {
        $config = Config::get('database.connections.'.Config::get('database.default'));
        $config['database'] = null;
        Config::set('database.connections.ddl_probe_server', $config);

        return DB::connection('ddl_probe_server');
    }

    /**
     * Run the callback with the default connection pointed at a fresh copy of
     * the schema, so any DDL it performs cannot reach the test database.
     */
    private function onACopyOfTheSchema(callable $callback): void
    {
        $source = DB::getDatabaseName();
        $config = Config::get('database.connections.'.Config::get('database.default'));

        $server = $this->serverConnection();
        $server->statement('DROP DATABASE IF EXISTS `'.self::PROBE_DATABASE.'`');
        $server->statement(
            'CREATE DATABASE `'.self::PROBE_DATABASE.'` DEFAULT CHARACTER SET '
            .$config['charset'].' COLLATE '.$config['collation']
        );
        $this->probeBuilt = true;

        foreach (self::PROBE_TABLES as $table) {
            $server->statement(
                'CREATE TABLE `'.self::PROBE_DATABASE.'`.`'.$table.'` LIKE `'.$source.'`.`'.$table.'`'
            );
        }

        $probe = $config;
        $probe['database'] = self::PROBE_DATABASE;
        Config::set('database.connections.ddl_probe', $probe);

        $previous = Config::get('database.default');
        DB::setDefaultConnection('ddl_probe');

        try {
            $callback();
        } finally {
            DB::setDefaultConnection($previous);
        }
    }

    /** @return array<int, string> */
    private function alterStatementsIssuedBy(callable $callback): array
    {
        $statements = [];

        DB::listen(function ($query) use (&$statements) {
            if (str_starts_with(strtoupper(ltrim($query->sql)), 'ALTER TABLE')) {
                $statements[] = $query->sql;
            }
        });

        $callback();

        return $statements;
    }

    private function columnOf(string $table, string $column): object
    {
        return DB::selectOne(
            'select COLUMN_TYPE, IS_NULLABLE, COLLATION_NAME from information_schema.COLUMNS
             where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ?',
            [DB::getDatabaseName(), $table, $column]
        );
    }

    public function test_it_alters_nothing_when_every_column_is_already_correct(): void
    {
        // The load-bearing property on a deployed host. A MODIFY on a TEXT
        // column can rebuild the table, and this runs inside the updater's web
        // request - so "correct schema" has to mean "no statement issued", not
        // "a statement that happens to be harmless". This case reads only, so
        // it can run against the test database directly.
        $this->assertSame(
            [],
            $this->alterStatementsIssuedBy(fn () => $this->migration()->up()),
            'A migráció módosított egy oszlopot, pedig a séma már helyes volt.'
        );
    }

    public function test_it_restores_a_column_that_lost_its_nullability(): void
    {
        // events.comment is one of the two the measurement found: declared as
        // `$table->text('comment')->change()` with no ->nullable(), so the
        // native change() would leave it NOT NULL.
        $this->onACopyOfTheSchema(function () {
            DB::statement('ALTER TABLE `events` MODIFY `comment` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');

            $this->assertSame('NO', $this->columnOf('events', 'comment')->IS_NULLABLE);

            $statements = $this->alterStatementsIssuedBy(fn () => $this->migration()->up());

            $this->assertCount(1, $statements, 'Pontosan egy oszlopot kellett volna javítania.');
            $this->assertSame('YES', $this->columnOf('events', 'comment')->IS_NULLABLE);
        });
    }

    public function test_it_restores_a_column_that_lost_its_collation(): void
    {
        // The quiet half of the same attribute group. Nothing in the
        // application would fail on this - only comparison semantics change,
        // which is exactly why it needs an assertion rather than a symptom.
        $this->onACopyOfTheSchema(function () {
            DB::statement('ALTER TABLE `groups` MODIFY `name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL');

            $statements = $this->alterStatementsIssuedBy(fn () => $this->migration()->up());

            $this->assertCount(1, $statements);
            $this->assertSame('utf8mb4_unicode_ci', $this->columnOf('groups', 'name')->COLLATION_NAME);
        });
    }

    public function test_running_it_twice_issues_no_second_statement(): void
    {
        $this->onACopyOfTheSchema(function () {
            DB::statement('ALTER TABLE `group_user` MODIFY `note` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');

            $this->assertCount(1, $this->alterStatementsIssuedBy(fn () => $this->migration()->up()));
            $this->assertSame([], $this->alterStatementsIssuedBy(fn () => $this->migration()->up()));
        });
    }

    public function test_it_covers_every_change_call_in_the_migration_path(): void
    {
        // The list is the assertion, the same shape as the encrypted cast list
        // (TODO 13) and the factory list (TODO 04). A new ->change() migration
        // that is not pinned here would carry the Laravel 11 trap back in
        // without failing anything else.
        $found = [];

        foreach (glob(database_path('migrations/*.php')) as $path) {
            preg_match_all(
                "/Schema::table\(\s*'([^']+)'.*?(?=Schema::table\(|\z)/s",
                file_get_contents($path),
                $blocks,
                PREG_SET_ORDER
            );

            foreach ($blocks as [$block, $table]) {
                preg_match_all(
                    "/\\\$table->\w+\(\s*'([^']+)'[^;]*->change\(\)/",
                    $block,
                    $columns
                );

                foreach ($columns[1] as $column) {
                    $found[$table.'.'.$column] = true;
                }
            }
        }

        $found = array_keys($found);
        sort($found);

        $pinned = array_map(
            static fn ($row) => $row[0].'.'.$row[1],
            (new \ReflectionClass(PinTheChangedColumnDefinitions::class))->getConstant('COLUMNS')
        );
        sort($pinned);

        $this->assertSame(
            $pinned,
            $found,
            'Egy ->change() hívás olyan oszlopon van, amit a korrekciós migráció nem rögzít.'
        );
    }
}
