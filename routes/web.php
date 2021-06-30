<?php

use App\Http\Controllers\GroupDelete;
use App\Http\Controllers\GroupLogout;
use App\Http\Controllers\GroupNewsFileDownloadController;
use App\Http\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Profile;
use App\Http\Livewire\Admin\Settings;
use App\Http\Livewire\Admin\StaticPageEdit;
use App\Http\Livewire\Admin\StaticPages;
use App\Http\Livewire\Admin\Users\ListUsers;
use App\Http\Livewire\Events\Events;
use App\Http\Livewire\Events\LastEvents;
use App\Http\Livewire\Groups\CreateGroupForm;
use App\Http\Livewire\Groups\ListGroups;
use App\Http\Livewire\Groups\ListUsers as GroupsListUsers;
use App\Http\Livewire\Groups\NewsEdit;
use App\Http\Livewire\Groups\NewsList;
use App\Http\Livewire\Groups\Statistics;
use App\Http\Livewire\Groups\UpdateGroupForm;
use App\Http\Livewire\Home;
// use App\Models\GroupNews;
// use App\Models\StaticPage;
// use Illuminate\Auth\Notifications\VerifyEmail;
// use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
// use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;

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
    // return view('main');
    return StaticPageController::render('home');
})->middleware(['guest']);


Route::get('/page/{slug}', [StaticPageController::class, 'render'])->name('static_page');


/**
 * Run scheduled queue 
 */
// Route::get('/run-jobs', function() {
//     $exitCode = Artisan::call('schedule:run');

//     // $exitCode = Artisan::call('schedule:list');
//     // var_dump($exitCode);
//     return 'ok: '.$exitCode;
// });


Route::get('/email/verify', 'App\Http\Controllers\Admin\DashboardController@verify')->name('verification.notice');


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

    Route::get('/confirm-password', function () {
        return view('auth.confirm-password');
    })->name('password.confirm');

    Route::post('/confirm-password', function (Request $request) {
        if (! Hash::check($request->password, $request->user()->password)) {
            return back()->withErrors([
                'password' => [__('app.authentication_error')]
            ]);
        }    
        $request->session()->passwordConfirmed();    
        return redirect()->intended();
    })->middleware(['throttle:6,1'])->name('password.confirm');

    //Only for verified users
    Route::middleware(['verified'])->group(function () {
        Route::get('user/profile', Profile::class)->name('user.profile');

        Route::middleware(['profileFull'])->group(function () {
            
            Route::get('/lastevents', LastEvents::class)->name('lastevents');
            Route::get('/calendar/{year?}/{month?}', Events::class)->name('calendar');
            Route::get('/groups', ListGroups::class)->name('groups');            

            //For special roles
            Route::get('/groups/create', CreateGroupForm::class)->name('groups.create')->middleware(['can:is-groupcreator']);

            Route::middleware(['can:is-admin', 'password.confirm'])->group(function () {
                Route::get('/admin/users', ListUsers::class)->name('admin.users');
                Route::get('/admin/settings', Settings::class)->name('admin.settings');
                Route::get('/admin/staticpages', StaticPages::class)->name('admin.staticpages');
                Route::get('/admin/staticpages/create', StaticPageEdit::class)->name('admin.staticpages_create');
                Route::get('/admin/staticpages/edit/{staticPage}', StaticPageEdit::class)->name('admin.staticpages_edit');
            });

            Route::middleware(['groupMember'])->group(function () {                
                Route::get('/groups/{group}/users', GroupsListUsers::class)->name('groups.users');
                Route::get('/groups/{group}/news', NewsList::class)->name('groups.news');
                Route::get('/news_file/{group}/{file}', [GroupNewsFileDownloadController::class, 'download'])->name('groups.news.filedownload');
                Route::get('/groups/{group}/logout', [GroupLogout::class, 'index'])->name('groups.logout')->middleware(['password.confirm']);
            });

            Route::middleware(['groupAdmin'])->group(function () {
                Route::get('/groups/{group}/edit', UpdateGroupForm::class)->name('groups.edit')->middleware(['password.confirm']);
                Route::get('/groups/{group}/delete', [GroupDelete::class, 'index'])->name('groups.delete')->middleware(['password.confirm']);
                Route::get('/groups/{group}/news/create', NewsEdit::class)->name('groups.news_create');
                Route::get('/groups/{group}/news/edit/{new}', NewsEdit::class)->name('groups.news_edit');
                Route::get('/groups/{group}/statistics', Statistics::class)->name('groups.statistics');
            });
        });
    });
});
