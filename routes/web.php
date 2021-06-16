<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Profile;
use App\Http\Livewire\Admin\Users\ListUsers;
use App\Http\Livewire\Events\Events;
use App\Http\Livewire\Groups\CreateGroupForm;
use App\Http\Livewire\Groups\ListGroups;
use App\Http\Livewire\Groups\ListUsers as GroupsListUsers;
use App\Http\Livewire\Groups\UpdateGroupForm;
use App\Http\Livewire\Home;
// use Illuminate\Auth\Notifications\VerifyEmail;
// use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Artisan;

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


//Felhasználási feltételek, bárki láthatja
Route::get('/terms', function () {
    return 'terms page';
})->name('terms');

/**
 * Run scheduled queue 
 */
// Route::get('/run-jobs', function() {
//     $exitCode = Artisan::call('schedule:run');

//     // $exitCode = Artisan::call('schedule:list');
//     // var_dump($exitCode);
//     return 'ok: '.$exitCode;
// });

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
            Route::get('/groups/{group}/users', GroupsListUsers::class)->name('groups.users')->middleware(['groupMember']);
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
