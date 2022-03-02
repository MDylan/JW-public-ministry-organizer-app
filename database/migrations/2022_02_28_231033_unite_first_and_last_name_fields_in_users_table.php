<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;

class UniteFirstAndLastNameFieldsInUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('users', function (Blueprint $table) {
            $table->text('name')->nullable(true);
            $table->text('phone_number')->nullable(true);
        });

        //encrypt name and phone data
        $users = App\Models\User::all();
        foreach ($users as $user) {
            if(($user->last_name !== null || $user->first_name !== null)) {
                $user->name = trim($user->last_name)." ".trim($user->first_name);
            }
            if($user->phone !== null)
                $user->phone_number = $user->phone;
            $user->save();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->dropColumn('first_name');
            $table->dropColumn('last_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(true);
        });

        $users = App\Models\User::all();
        foreach ($users as $user) {
            $user->phone = $user->phone_number;
            $user->save();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('phone_number');
        });
    }
}
