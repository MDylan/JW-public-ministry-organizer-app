<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalAndColorsFieldsToGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedSmallInteger('need_approval')->default(0);
            $table->string('color_default')->nullable();
            $table->string('color_empty')->nullable();
            $table->string('color_someone')->nullable();
            $table->string('color_minimum')->nullable();
            $table->string('color_maximum')->nullable();
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
            $table->dropColumn('need_approval');
            $table->dropColumn('color_empty');
            $table->dropColumn('color_someone');
            $table->dropColumn('color_minimum');
            $table->dropColumn('color_maximum');
        });
    }
}
