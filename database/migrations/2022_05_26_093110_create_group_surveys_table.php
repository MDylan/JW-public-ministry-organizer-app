<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupSurveysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained();
            $table->text('question');
            $table->date('start_at')->nullable(true);
            $table->date('end_at')->nullable(true);
            $table->timestamps();

            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_surveys');
    }
}
