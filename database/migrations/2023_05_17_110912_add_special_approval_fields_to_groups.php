<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSpecialApprovalFieldsToGroups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedSmallInteger('auto_approval')->default(0)->comment('If need approval, system accept the events when reach the minimum of publishers');
            $table->unsignedSmallInteger('auto_back')->default(0)->comment('If need approval, and publishers number goes below minimum, set event status to waiting to approval');
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
            $table->dropColumn('auto_approval');
            $table->dropColumn('auto_back');
        });
    }
}
