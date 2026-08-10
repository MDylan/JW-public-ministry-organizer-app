<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The groups.city_id foreign key to the weather_cities table.
 *
 * WHY A SEPARATE MIGRATION IS NEEDED
 *
 * The 2024_12_04_194500 migration declared the column like this:
 *
 *     $table->unsignedBigInteger('city_id')->nullable()
 *           ->constrained('weather_cities')->onDelete('set null');
 *
 * But `constrained()` is the counterpart of the `foreignId()` method - after
 * `unsignedBigInteger()` it is a SILENT no-op. The foreign key was therefore
 * never created: a deleted weather_cities row left an orphaned city_id
 * behind, which the calendar's dereference then found via a fatal error.
 *
 * The original migration is deliberately NOT rewritten: it has already run
 * on installed sites, so it would not run again.
 */
class AddCityIdForeignKeyToGroupsTable extends Migration
{
    public function up()
    {
        // Orphaned references must be cleaned up first, otherwise adding
        // the constraint fails on the existing data.
        DB::table('groups')
            ->whereNotNull('city_id')
            ->whereNotIn('city_id', function ($query) {
                $query->select('id')->from('weather_cities');
            })
            ->update(['city_id' => null]);

        Schema::table('groups', function (Blueprint $table) {
            $table->foreign('city_id')
                ->references('id')
                ->on('weather_cities')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
        });
    }
}
