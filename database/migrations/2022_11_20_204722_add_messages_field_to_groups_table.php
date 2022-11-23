<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMessagesFieldToGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedSmallInteger('messages_on')->default(0);
            $table->unsignedSmallInteger('messages_write')->default(0);
            $table->unsignedSmallInteger('messages_priority')->default(0);
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
            $table->dropColumn('messages_on');
            $table->dropColumn('messages_write');
            $table->dropColumn('messages_priority');
        });
    }
}
