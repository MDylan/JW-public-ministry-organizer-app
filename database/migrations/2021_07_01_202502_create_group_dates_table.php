<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupDatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->index();
            $table->date('date')->index();
            $table->dateTime('date_start');
            $table->dateTime('date_end');
            $table->tinyInteger('date_status')->default(1);
            $table->string('note')->nullable();
            $table->tinyInteger('date_min_publishers')->nullable();
            $table->tinyInteger('date_max_publishers')->nullable();
            $table->smallInteger('date_min_time')->nullable();
            $table->smallInteger('date_max_time')->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->unique(['group_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_dates');
    }
}
