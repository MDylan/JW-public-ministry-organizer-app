<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Livewire\Admin\Users\ListUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



if(Auth::guest()) {

    Route::get('/', function () {
        return view('main');
    });
    Route::get('/tesztmail', function () {
        $to_name = 'Dávid';
        $to_email = 'molnar.david@gmail.com';
        $data = array('name'=>'Dávid', 'body' => 'Ez egy próba levél');
        $res = Mail::send('auth.email.registered', $data, function($message) use ($to_name, $to_email) {
            $message->to($to_email, $to_name)->subject('Laravel próba levél');
            $message->from('info@koz.teruletek.hu','Próba tárgy');
        });
        echo "Levél elküldve!";
        dd($res);
    });

} else {
    //ezeket csak belépett Felhasználók láthatják
    Route::get('/', function () {
        redirect("/home");
    });

    Route::get('/home', DashboardController::class)->name('home.home')->middleware('auth');

    Route::get('/admin/users', ListUsers::class)->name('admin.users')->middleware('auth');

}