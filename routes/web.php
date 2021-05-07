<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\User\Profile;
use App\Http\Livewire\Admin\Users\ListUsers;
use App\Http\Livewire\Groups\CreateGroupForm;
use App\Http\Livewire\Groups\ListGroups;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
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

//Felhasználási feltételek, bárki láthatja
Route::get('/terms', function () {
    return 'terms page';
})->name('terms');

/**
 * Belépett felhasználók linkjei
 */

//emailcím megerősítéséről üzenet megy
// Route::get('/email/verify', function () {
//     return view('auth.email.verify-email');
// })->middleware('auth')->name('verification.notice');

Route::get('/email/verify', 'App\Http\Controllers\Admin\DashboardController@verify')->name('verification.notice');

//email megerősítése
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $result = $request->fulfill();
    return redirect('/home');
})->middleware(['auth', 'signed'])->name('verification.verify');

//Kezdőoldal
Route::get('/home', DashboardController::class)->name('home.home')->middleware('auth');
Route::get('/contact', function () {
    return 'contact page';
})->name('contact')->middleware('auth');

/**
 * Csak megerősített felhasználók láthatják
 */
Route::prefix('user')->middleware(['auth', 'verified'])->name('user.')->group(function() {
    Route::get('profile', Profile::class)->name('profile');
});

//Csoportok menü
Route::get('/groups', ListGroups::class)->name('groups')->middleware(['auth', 'verified']);
//Csoport készítés
Route::get('/groups/create', CreateGroupForm::class)->name('groups.create')->middleware(['auth', 'verified']);

/**
 * Adminisztrátorok
 */
// Admin / Felhasználók
Route::get('/admin/users', ListUsers::class)->name('admin.users')->middleware('can:is-admin');
