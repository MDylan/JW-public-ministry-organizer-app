<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventServiceReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_service_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('group_literature_id')->index();
            $table->smallInteger('placements')->default(0);
            $table->smallInteger('videos')->default(0);
            $table->smallInteger('return_visits')->default(0);
            $table->smallInteger('bible_studies')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'group_literature_id']);
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('group_literature_id')->references('id')->on('group_literatures')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('event_service_reports');
    }
}
