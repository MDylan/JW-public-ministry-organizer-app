<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWeatherCitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('weather_cities', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->string('country')->length(5);
            $table->json('current_weather')->nullable();
            $table->json('forecast_weather')->nullable();
            $table->dateTime('last_try')->nullable();
            $table->timestamps();

            $table->unique(['city', 'country']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('weather_cities');
    }
}
