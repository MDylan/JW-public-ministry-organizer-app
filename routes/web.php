<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Livewire\Admin\Users\ListUsers;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

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



Route::get('/', function () {
    return view('main');
})->middleware(['guest']);

Route::get('/tesztmail', function () {
    $to_name = 'Dávid';
    $to_email = 'molnar.david@gmail.com';
    $data = array('name'=>'Dávid', 'body' => 'Ez egy próba levél');
    $res = Mail::send('auth.email.registered', $data, 
        function($message) use ($to_name, $to_email) {
            $message->to($to_email, $to_name)->subject('Laravel próba levél');
            $message->from('info@koz.teruletek.hu','Próba tárgy');
        });

        Mail::send('Html.view', $data, function ($message) {
            $message->from('john@johndoe.com', 'John Doe');
            $message->sender('john@johndoe.com', 'John Doe');
            $message->to('john@johndoe.com', 'John Doe');
            $message->cc('john@johndoe.com', 'John Doe');
            $message->bcc('john@johndoe.com', 'John Doe');
            $message->replyTo('john@johndoe.com', 'John Doe');
            $message->subject('Subject');
            $message->priority(3);
            $message->attach('pathToFile');
        });
    echo "Levél elküldve!";
    dd($res);
});
//emailcím megerősítéséről üzenet megy
Route::get('/email/verify', function () {
    return view('auth.email.verify-email');
})->middleware('auth')->name('verification.notice');
//email megerősítése
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $result = $request->fulfill();
    if($result == true) {
        //módosítom a usert aktiváltra
        $userID = $request->route('id');
        User::where('role', '=', 'registered')
            ->where('id', $userID)
            ->update(['role' => 'activated']);
    }
    return redirect('/home');
})->middleware(['auth', 'signed'])->name('verification.verify');

/**
 * Belépett felhasználók linkjei
 */

//Kezdőoldal
Route::get('/home', DashboardController::class)->name('home.home')->middleware('auth');
// Admin / Felhasználók
Route::get('/admin/users', ListUsers::class)->name('admin.users')->middleware('can:is-admin');
