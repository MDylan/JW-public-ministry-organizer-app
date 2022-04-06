<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class AddNameIndexToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('name_index')->default(0)->after('id');
        });

        $users = App\Models\User::orderBy('name')->get(); //->sortBy('name', SORT_STRING);
        $i = 1;
        foreach ($users as $user) {
            $user->name_index = $i;
            $user->name = Crypt::encryptString($user->name);
            $user->save();
            $i++;
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name_index');
        });
    }
}
