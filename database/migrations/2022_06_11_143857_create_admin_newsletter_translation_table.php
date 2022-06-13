<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminNewsletterTranslationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admin_newsletter_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_newsletter_id');
            $table->string('locale')->index();
            $table->string('subject');
            $table->text('content');

            $table->unique(['admin_newsletter_id', 'locale']);
            $table->foreign('admin_newsletter_id')->references('id')->on('admin_newsletters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admin_newsletter_translations');
    }
}
