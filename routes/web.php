<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Profile;
use App\Http\Livewire\Admin\Users\ListUsers;
use App\Http\Livewire\Events\Events;
use App\Http\Livewire\Groups\CreateGroupForm;
use App\Http\Livewire\Groups\ListGroups;
use App\Http\Livewire\Groups\UpdateGroupForm;
use App\Http\Livewire\Home;
// use Illuminate\Auth\Notifications\VerifyEmail;
// use Illuminate\Support\Facades\Mail;
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

// Route::get('/tesztmail', function () {
//     $to_name = 'Dávid';
//     $to_email = 'molnar.david@gmail.com';
//     $data = array('name'=>'Dávid', 'body' => 'Ez egy próba levél');
//     $res = Mail::send('auth.email.registered', $data, 
//         function($message) use ($to_name, $to_email) {
//             $message->to($to_email, $to_name)->subject('Laravel próba levél');
//             $message->from('info@koz.teruletek.hu','Próba tárgy');
//         });

//         Mail::send('Html.view', $data, function ($message) {
//             $message->from('john@johndoe.com', 'John Doe');
//             $message->sender('john@johndoe.com', 'John Doe');
//             $message->to('john@johndoe.com', 'John Doe');
//             $message->cc('john@johndoe.com', 'John Doe');
//             $message->bcc('john@johndoe.com', 'John Doe');
//             $message->replyTo('john@johndoe.com', 'John Doe');
//             $message->subject('Subject');
//             $message->priority(3);
//             $message->attach('pathToFile');
//         });
//     echo "Levél elküldve!";
//     dd($res);
// });

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

//verify email
// Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
//     $result = $request->fulfill();
//     return redirect('/home');
// })->middleware(['auth', 'signed'])->name('verification.verify');

//Logged in users
Route::middleware(['auth'])->group(function () {
    Route::get('/home', Home::class)->name('home.home');
    Route::get('/contact', function () {
        return 'contact page';
    })->name('contact');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $result = $request->fulfill();
        return redirect('/home');
    })->name('verification.verify');

    //Only for verified users
    Route::middleware(['verified'])->group(function () {
        Route::get('user/profile', Profile::class)->name('user.profile');
        Route::middleware(['profileFull'])->group(function () {
            Route::get('/groups', ListGroups::class)->name('groups');
            Route::get('/calendar/{year?}/{month?}', Events::class)->name('calendar');

            //For special roles
            Route::get('/groups/create', CreateGroupForm::class)->name('groups.create')->middleware(['can:is-groupcreator']);
            Route::get('/groups/{group}/edit', UpdateGroupForm::class)->name('groups.edit')->middleware(['groupAdmin']);
            Route::get('/admin/users', ListUsers::class)->name('admin.users')->middleware('can:is-admin');
        });
    });
});

//Kezdőoldal
// Route::get('/home', Home::class)->name('home.home')->middleware('auth');
// Route::get('/contact', function () {
//     return 'contact page';
// })->name('contact')->middleware('auth');

/**
 * Csak megerősített felhasználók láthatják
 */
// Route::prefix('user')->middleware(['auth', 'verified'])->name('user.')->group(function() {
//     Route::get('profile', Profile::class)->name('profile');
// });

//Csoportok menü
// Route::get('/groups', ListGroups::class)->name('groups')->middleware(['auth', 'verified']);
//Csoport készítés
// Route::get('/groups/create', CreateGroupForm::class)->name('groups.create')->middleware(['auth', 'verified', 'can:is-groupcreator']);
// Route::get('/groups/{group}/edit', UpdateGroupForm::class)->name('groups.edit')->middleware(['auth', 'verified', 'groupAdmin']);

//Naptár menü
// Route::get('/calendar/{year?}/{month?}', Events::class)->name('calendar')->middleware(['auth', 'verified']);

// Route::post('/getevents', [GetEvents::class, 'index'])->name('getevents')->middleware(['auth', 'verified']);

/**
 * Adminisztrátorok
 */
// Admin / Felhasználók
// Route::get('/admin/users', ListUsers::class)->name('admin.users')->middleware('can:is-admin');
