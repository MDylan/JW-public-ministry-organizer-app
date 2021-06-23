<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStaticPagesTranslationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('static_page_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('static_page_id')->unsigned();
            $table->string('locale')->index();
            $table->string('title');
            $table->text('content');

            $table->unique(['static_page_id', 'locale']);
            $table->foreign('static_page_id')->references('id')->on('static_pages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('static_page_translations');
    }
}
