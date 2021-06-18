<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupNewsFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_news_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_new_id')->index();
            $table->string('name');
            $table->string('file');
            $table->timestamps();

            $table->foreign('group_new_id')->references('id')->on('group_news')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_news_files');
    }
}
