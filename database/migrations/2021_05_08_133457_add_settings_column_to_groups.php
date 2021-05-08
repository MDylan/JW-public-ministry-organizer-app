<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSettingsColumnToGroups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->text('days')->nullable();
            $table->unsignedSmallInteger('min_publishers')->default(2);
            $table->unsignedSmallInteger('max_publishers')->default(2);
            $table->unsignedSmallInteger('min_time')->default(60);
            $table->unsignedSmallInteger('max_time')->default(360);
            $table->unsignedSmallInteger('max_extend_days')->default(60);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('days');
            $table->dropColumn('min_publishers');
            $table->dropColumn('max_publishers');
            $table->dropColumn('min_time');
            $table->dropColumn('max_extend_days');
        });
    }
}
