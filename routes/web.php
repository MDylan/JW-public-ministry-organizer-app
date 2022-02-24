<?php

use App\Http\Controllers\deletePersonalDataController;
use App\Http\Controllers\FinishRegistration;
use App\Http\Controllers\GroupDelete;
use App\Http\Controllers\GroupLogout;
use App\Http\Controllers\GroupNewsDelete;
use App\Http\Controllers\GroupNewsFileDownloadController;
use App\Http\Controllers\jumpToCalendarController;
use App\Http\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Profile;
use App\Http\Livewire\Admin\Settings;
use App\Http\Livewire\Admin\StaticPageEdit;
use App\Http\Livewire\Admin\StaticPages;
use App\Http\Livewire\Admin\Translation;
use App\Http\Livewire\Admin\Users\ListUsers;
use App\Http\Livewire\Events\Events;
use App\Http\Livewire\Events\LastEvents;
use App\Http\Livewire\Groups\History;
use App\Http\Livewire\Groups\ListGroups;
use App\Http\Livewire\Groups\ListUsers as GroupsListUsers;
use App\Http\Livewire\Groups\NewsEdit;
use App\Http\Livewire\Groups\NewsList;
use App\Http\Livewire\Groups\Statistics;
use App\Http\Livewire\Groups\UpdateGroupForm;
use App\Http\Livewire\Home;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
    return StaticPageController::render('home');
})->middleware(['guest']);


Route::get('/page/{slug}', [StaticPageController::class, 'render'])->name('static_page');
Route::middleware(['signed'])->group(function () {
    Route::get('/finish-registration/{id}', [FinishRegistration::class, 'index'])
        ->name('finish_registration')
        ->middleware('setGuestLanguage');
    Route::post('/finish-registration/{id}', [FinishRegistration::class, 'register'])
        ->name('finish_registration_register');
    Route::get('/finish-registration/{id}/cancel', [FinishRegistration::class, 'cancel'])
        ->name('finish_registration_cancel');
});


Route::get('/email/verify', 'App\Http\Controllers\Admin\DashboardController@verify')->name('verification.notice');


//Logged in users
Route::middleware(['auth'])->group(function () {
    Route::get('/home', Home::class)->name('home.home');

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

            Route::get('/user/asktodelete', [deletePersonalDataController::class, 'asktodelete'])
                                    ->name('user.askToDelete')->middleware(['password.confirm']);
            Route::get('/user/deletepersonaldata/{id}', [deletePersonalDataController::class, 'deletePersonalData'])
                                    ->name('user.deletepersonaldata')->middleware(['signed']);
            
            Route::get('/lastevents', LastEvents::class)->name('lastevents');
            Route::get('/calendar/{year?}/{month?}', Events::class)->name('calendar');
            Route::get('/jtc/{group}/{year}/{month}', [jumpToCalendarController::class, 'jump'])->name('jumpToCalendar');
            Route::get('/groups', ListGroups::class)->name('groups');            

            //For special roles
            Route::middleware(['can:is-admin', 'password.confirm'])->group(function () {
                Route::get('/admin/users', ListUsers::class)->name('admin.users');
                Route::get('/admin/settings', Settings::class)->name('admin.settings');
                Route::get('/admin/staticpages', StaticPages::class)->name('admin.staticpages');
                Route::get('/admin/staticpages/create', StaticPageEdit::class)->name('admin.staticpages_create');
                Route::get('/admin/staticpages/edit/{staticPage}', StaticPageEdit::class)->name('admin.staticpages_edit');

                Route::get('/admin/update', function (\Codedge\Updater\UpdaterManager $updater) {

                    // Check if new version is available
                    if($updater->source()->isNewVersionAvailable()) {
                
                        // Get the current installed version
                        echo $updater->source()->getVersionInstalled();
                
                        // // Get the new version available
                        // $versionAvailable = $updater->source()->getVersionAvailable();
                
                        // // Create a release
                        // $release = $updater->source()->fetch($versionAvailable);
                
                        // // Run the update process
                        // $updater->source()->update($release);
                        
                    } else {
                        echo "No new version available.";
                    }
                
                });
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
                Route::get('/groups/{group}/news/delete/{new}', [GroupNewsDelete::class, 'delete'])->name('groups.news_delete')->middleware(['password.confirm']);
                Route::get('/groups/{group}/statistics', Statistics::class)->name('groups.statistics');
                Route::get('/groups/{group}/history', History::class)->name('groups.history');
            });

            Route::middleware(['can:is-translator', 'password.confirm'])->group(function () {
                Route::get('/admin/translate', Translation::class)->name('admin.translate');
            });
        });
    });
});
