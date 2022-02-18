<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupPostersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_posters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->text('info');
            $table->date('show_date');
            $table->date('hide_date')->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups');

            $table->index(['group_id', 'show_date', 'hide_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_posters');
    }
}
