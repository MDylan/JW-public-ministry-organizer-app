<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $request = Request();
        
        if( $request->input('admin_email') !== null) {
            DB::table('users')->insert([
                'email' => $request->input('admin_email'),
                'password' => Hash::make($request->input('admin_password')),
                'role' => 'mainAdmin'
            ]);
        } else {
			DB::table('users')->insert([
                'email' => 'molnar.david@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'mainAdmin'
            ]);
		}
    }
}
