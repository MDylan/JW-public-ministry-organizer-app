<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TODO 32: state the final definition of every ->change() column outright.
 *
 * THE PROBLEM. Eight columns reached their current shape through a
 * `->change()` migration. Under Laravel 8-10 that call goes through
 * doctrine/dbal, which reads the column's CURRENT definition and alters only
 * what the migration redeclares. Laravel 11 makes change() native, and the
 * native one DROPS every attribute that is not redeclared - nullability,
 * default, charset, collation.
 *
 * WHO IT WOULD HAVE BITTEN, AND WHEN. Nobody on an existing installation:
 * those migrations have run and never run again. Only a FRESH install after
 * the Laravel 11 hop, which is exactly what the web installer does on
 * customer hosting. Old and new installations would then carry different
 * schemas permanently, and the difference would not surface at migration
 * time - it would surface the first time somebody saved an event with no
 * comment, months later, as a QueryException.
 *
 * WHAT WAS ACTUALLY MEASURED. Of the eight, six redeclare enough to survive
 * unchanged. Two do not, and both differ on nullability alone:
 *
 *   events.comment      declared `$table->text('comment')->change()`
 *   group_user.note     declared `$table->text('note')->change()`
 *
 * Neither declares ->nullable(), and Laravel's default is NOT NULL, so the
 * native change() would turn two nullable columns into NOT NULL ones. Both
 * carry the `encrypted` cast, and null bypasses that cast in both directions
 * - so these are precisely the columns where a nullability flip is a write
 * failure rather than a cosmetic difference.
 *
 * The charset and collation halves of the trap are harmless in this schema
 * as it stands, because every table already defaults to utf8mb4 /
 * utf8mb4_unicode_ci, which is what the columns would fall back to. They are
 * restated here anyway: relying on a table default to be right is the same
 * bet that produced this migration.
 *
 * WHY IT RESTATES RATHER THAN REPAIRS. The definitions below are the final
 * shape, written as raw ALTER TABLE. They do not depend on what any version
 * of change() preserves or drops, so this migration is correct whether it
 * runs before or after the Laravel 11 hop, and it needs no revisiting when
 * the framework changes its mind again.
 *
 * WHY IT COMPARES FIRST. A MODIFY on a TEXT column can rebuild the whole
 * table, and on a deployed host this runs inside the self-updater's web
 * request, against tables that are not small. Every column below is already
 * correct on every existing installation, so the guard means those hosts
 * issue no ALTER at all. Only a schema that has actually drifted pays for it,
 * and that case is a fresh install with empty tables.
 *
 * Pinned by tests/Feature/Models/EncryptedColumnSchemaTest.php, which asserts
 * type, nullability, length, charset and collation for the nine encrypted
 * columns - four of the eight below among them.
 */
class PinTheChangedColumnDefinitions extends Migration
{
    /**
     * Table, column, column type, nullable, collation.
     *
     * A null collation means the type carries no character set (the one
     * integer column below).
     */
    private const COLUMNS = [
        ['group_news_translations', 'title', 'varchar(255)', true, 'utf8mb4_unicode_ci'],
        ['group_news_translations', 'content', 'text', true, 'utf8mb4_unicode_ci'],
        ['groups', 'name', 'text', false, 'utf8mb4_unicode_ci'],
        ['events', 'comment', 'text', true, 'utf8mb4_unicode_ci'],
        ['group_posters', 'info', 'mediumtext', false, 'utf8mb4_unicode_ci'],
        ['group_user', 'note', 'text', true, 'utf8mb4_unicode_ci'],
        ['static_page_translations', 'content', 'longtext', false, 'utf8mb4_unicode_ci'],
        ['jobs', 'attempts', 'smallint unsigned', false, null],
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (self::COLUMNS as [$table, $column, $type, $nullable, $collation]) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            if ($this->matches($table, $column, $type, $nullable, $collation)) {
                continue;
            }

            $definition = $type;

            if ($collation !== null) {
                $definition .= ' CHARACTER SET '.explode('_', $collation)[0].' COLLATE '.$collation;
            }

            $definition .= $nullable ? ' NULL' : ' NOT NULL';

            DB::statement('ALTER TABLE `'.$table.'` MODIFY `'.$column.'` '.$definition);
        }
    }

    /**
     * Is the column already exactly what it should be?
     */
    private function matches(string $table, string $column, string $type, bool $nullable, ?string $collation): bool
    {
        $row = DB::selectOne(
            'select COLUMN_TYPE, IS_NULLABLE, COLLATION_NAME
             from information_schema.COLUMNS
             where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ?',
            [DB::getDatabaseName(), $table, $column]
        );

        if ($row === null) {
            return false;
        }

        return strtolower($row->COLUMN_TYPE) === $type
            && ($row->IS_NULLABLE === 'YES') === $nullable
            && $row->COLLATION_NAME === $collation;
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Deliberately empty. This migration does not introduce a shape, it
        // states the one the schema already had, so there is nothing to undo -
        // and reversing it would mean restoring whichever wrong definition it
        // happened to correct.
    }
}
