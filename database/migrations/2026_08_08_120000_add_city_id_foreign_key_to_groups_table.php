<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A groups.city_id idegen kulcsa a weather_cities táblára.
 *
 * MIÉRT KELL KÜLÖN MIGRÁCIÓ
 *
 * A 2024_12_04_194500 migráció így írta le a mezőt:
 *
 *     $table->unsignedBigInteger('city_id')->nullable()
 *           ->constrained('weather_cities')->onDelete('set null');
 *
 * A `constrained()` viszont a `foreignId()` metódus párja - `unsignedBigInteger()`
 * után NÉMA no-op. Idegen kulcs tehát soha nem jött létre: egy törölt
 * weather_cities sor árva city_id-t hagyott maga után, amit a naptár
 * dereferálása fatallal talált meg.
 *
 * Az eredeti migrációt szándékosan NEM írjuk át: telepített helyeken már
 * lefutott, tehát nem futna újra.
 */
class AddCityIdForeignKeyToGroupsTable extends Migration
{
    public function up()
    {
        // Az árva hivatkozásokat előbb ki kell takarítani, különben a
        // kényszer felvétele elhasal a meglévő adatokon.
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
