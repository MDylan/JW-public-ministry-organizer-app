<?php

use App\Http\Controllers\Admin\LoginToUserController;
use App\Http\Controllers\deletePersonalDataController;
use App\Http\Controllers\FinishRegistration;
use App\Http\Controllers\GroupDelete;
use App\Http\Controllers\GroupLogout;
use App\Http\Controllers\GroupNewsDelete;
use App\Http\Controllers\GroupNewsFileDownloadController;
use App\Http\Controllers\jumpToCalendarController;
use App\Http\Controllers\LogoutOtherDevicesController;
use App\Http\Controllers\Setup\AccountController;
use App\Http\Controllers\Setup\BasicsController;
use App\Http\Controllers\Setup\DatabaseController;
use App\Http\Controllers\Setup\MailController;
use App\Http\Controllers\Setup\MetaController;
use App\Http\Controllers\Setup\RequirementsController;
use App\Http\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Profile;
use App\Http\Controllers\User\TwoFactorSettings;
use App\Http\Livewire\Admin\AdminNewsletters;
use App\Http\Livewire\Admin\NewsletterEdit;
use App\Http\Livewire\Admin\Settings;
use App\Http\Livewire\Admin\StaticPageEdit;
use App\Http\Livewire\Admin\StaticPages;
use App\Http\Livewire\Admin\Statistics as AdminStatistics;
use App\Http\Livewire\Admin\Translation;
use App\Http\Livewire\Admin\Users\ListUsers;
use App\Http\Livewire\Events\Events;
use App\Http\Livewire\Events\LastEvents;
use App\Http\Livewire\Groups\DeleteGroup;
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
use Illuminate\Support\Facades\Storage;

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

//installer available only if file not exists
if (!Storage::exists('installed.txt')) {
    // Setup routes
    Route::prefix('setup')->group(function () {
        Route::get('/start', [MetaController::class, 'welcome'])
            ->name('setup.welcome');
        Route::get('/requirements', [RequirementsController::class, 'index'])
            ->name('setup.requirements');

        Route::get('/basics', [BasicsController::class, 'index'])
            ->name('setup.basics');
        Route::post('/basics', [BasicsController::class, 'configure'])
            ->name('setup.save-basics');

        Route::get('/database', [DatabaseController::class, 'index'])
            ->name('setup.database');
        Route::post('/database', [DatabaseController::class, 'configure'])
            ->name('setup.save-database');

        Route::get('/mail', [MailController::class, 'index'])
            ->name('setup.mail');
        Route::post('/mail', [MailController::class, 'configure'])
            ->name('setup.save-mail');

        Route::get('/account', [AccountController::class, 'index'])
            ->name('setup.account');
        Route::post('/account', [AccountController::class, 'register'])
            ->name('setup.save-account');

        Route::get('/complete', [MetaController::class, 'complete'])
            ->name('setup.complete');
    });
}

//Logged in users
Route::middleware(['auth'])->group(function () {
    Route::get('/home', Home::class)->name('home.home');

    //only for admin login back
    Route::get('/loginback/{id}', [LoginToUserController::class, 'loginback'])
            ->name('admin.loginback')->middleware(['signed']);

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

            Route::get('user/twofactorsettings', TwoFactorSettings::class)
                        ->name('user.twofactorsettings')->middleware(['password.confirm']);
            Route::post('/user/2fa-confirm', [TwoFactorSettings::class, 'confirm'])->name('two-factor.confirm');

            Route::get('/user/asktodelete', [deletePersonalDataController::class, 'asktodelete'])
                                    ->name('user.askToDelete')->middleware(['password.confirm']);
            Route::get('/user/deletepersonaldata/{id}', [deletePersonalDataController::class, 'deletePersonalData'])
                                    ->name('user.deletepersonaldata')->middleware(['signed']);
            Route::post('/user/logout_other_devices', [LogoutOtherDevicesController::class, 'logout'])->name('user.logout_other_devices');
            
            Route::get('/lastevents', LastEvents::class)->name('lastevents');
            Route::get('/calendar/{year?}/{month?}', Events::class)->name('calendar');
            Route::get('/jtc/{group}/{year}/{month}', [jumpToCalendarController::class, 'jump'])->name('jumpToCalendar');
            Route::get('/groups', ListGroups::class)->name('groups'); 
            Route::get('/newsletters', AdminNewsletters::class)->name('newsletters')->middleware('can:is-groupservant');

            //For special roles
            Route::middleware(['can:is-admin', 'password.confirm'])->group(function () {
                Route::get('/admin/users', ListUsers::class)->name('admin.users');
                Route::get('/admin/users/login/{user}', [LoginToUserController::class, 'login'])->name('admin.users.login');
                Route::get('/admin/settings', Settings::class)->name('admin.settings');
                Route::get('/admin/staticpages', StaticPages::class)->name('admin.staticpages');
                Route::get('/admin/staticpages/create', StaticPageEdit::class)->name('admin.staticpages_create');
                Route::get('/admin/staticpages/edit/{staticPage}', StaticPageEdit::class)->name('admin.staticpages_edit');
                Route::get('/admin/newsletter_edit/{id?}', NewsletterEdit::class)->name('admin.newsletter_edit');
            });

            Route::middleware(['can:is-admin'])->group(function () {
                Route::get('/admin/statistics', AdminStatistics::class)->name('admin.statistics');
            });

            Route::middleware(['groupMember'])->group(function () {                
                Route::get('/groups/{group}/users', GroupsListUsers::class)->name('groups.users');
                Route::get('/groups/{group}/news', NewsList::class)->name('groups.news');
                Route::get('/news_file/{group}/{file}', [GroupNewsFileDownloadController::class, 'download'])->name('groups.news.filedownload');
                Route::get('/groups/{group}/logout', [GroupLogout::class, 'index'])->name('groups.logout')->middleware(['password.confirm']);
            });

            Route::middleware(['groupAdmin'])->group(function () {
                Route::get('/groups/{group}/edit', UpdateGroupForm::class)->name('groups.edit')->middleware(['password.confirm']);
                // Route::get('/groups/{group}/delete', [GroupDelete::class, 'index'])->name('groups.delete')->middleware(['password.confirm']);
                Route::get('/groups/{group}/delete', DeleteGroup::class)->name('groups.delete')->middleware(['password.confirm']);
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
