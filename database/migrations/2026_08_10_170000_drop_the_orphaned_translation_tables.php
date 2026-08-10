<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * TODO 33.3: drop the two tables joedixon/laravel-translation left behind.
 *
 * WHY THEY EXIST AT ALL
 *
 * The package registered its migrations with loadMigrationsFrom() and did so
 * UNCONDITIONALLY - the `driver` setting was never consulted. This project ran
 * on the `file` driver from the beginning, so `languages` and `translations`
 * were created on every install and read by nothing, ever. The languages
 * migration also seeded rows through an Eloquent model in its own body.
 *
 * WHY DROPPING THEM IS THE SMALLER CHANGE
 *
 * With the package gone the two creating migrations vanish from the migration
 * path, so a fresh install no longer produces the tables at all. Leaving the
 * existing ones in place would mean old and new installations diverge on schema
 * permanently, for tables nothing reads - which is worse than one deliberate
 * drop. TODO 32 squashes the migration history later and would have had to
 * decide the same question anyway.
 *
 * WHY down() DOES NOT RECREATE THEM
 *
 * There is nothing to go back to: the migrations that defined their columns
 * left with the package, so a faithful rollback is not expressible here. A
 * rollback of this migration therefore simply leaves them absent, which is the
 * same state a fresh install is in.
 */
class DropTheOrphanedTranslationTables extends Migration
{
    public function up()
    {
        // The order matters if a foreign key was ever added between them by a
        // future version of the package; dropIfExists is otherwise indifferent.
        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');
    }

    public function down()
    {
        //
    }
}
