<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminNewslettersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admin_newsletters', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->smallInteger('status')->default(0);
            $table->smallInteger('send_newsletter')->default(0);
            $table->string('send_to');
            $table->dateTime('sent_time')->nullable(true);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admin_newsletters');
    }
}
