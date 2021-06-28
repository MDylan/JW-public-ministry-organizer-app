<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupNewsTranslationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_news_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_news_id')->unsigned();
            $table->string('locale')->index();
            $table->string('title');
            $table->text('content');

            $table->unique(['group_news_id', 'locale']);
            $table->foreign('group_news_id')->references('id')->on('group_news')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_news_translations');
    }
}
