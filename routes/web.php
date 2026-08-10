<?php

use App\Http\Controllers\Admin\LoginToUserController;
use App\Http\Controllers\deletePersonalDataController;
use App\Http\Controllers\FinishRegistration;
use App\Http\Controllers\GdprController;
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
use App\Http\Controllers\User\VerifyNewEmailController;
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
    // PREVIOUSLY a GET. The signature proves that we issued the link - not
    // that the user opened it DELIBERATELY. A data-deleting GET can be
    // fired by the browser's prefetch, a mail client's link scanner, or an
    // accidental navigation. With POST + CSRF this cannot happen.
    Route::post('/finish-registration/{id}/cancel', [FinishRegistration::class, 'cancel'])
        ->name('finish_registration_cancel');
});


// `auth` was PREVIOUSLY missing. No authorization bypass was visible from
// it - the mail-sending POST is separately protected - but the view uses
// `<x-admin-layout>`, which dereferences `auth()->user()` for a guest:
// every logged-out visit produced a 500 and a stack trace in the log.
Route::get('/email/verify', 'App\Http\Controllers\Admin\DashboardController@verify')
    ->name('verification.notice')->middleware(['auth']);
Route::get('/user/new-email-verified', [Profile::class, 'redirectAfterNewEmailVerification'])->name('user.new-email-verified');

// Confirmation of a pending e-mail address.
//
// TODO 33.5: until `protonemedia/laravel-verify-new-email` was replaced, this
// route came from the package's OWN route file, and it loaded only because the
// `route` key in the published `config/verify-new-email.php` was `null`. The
// name and the URI are unchanged to the letter, so links already in flight keep
// working.
//
// It DELIBERATELY carries no `auth`: the link is normally opened on a different
// device. `throttle` sat in the vendor controller's constructor - here it is on
// the route, because constructor middleware disappears with the Laravel 11
// skeleton.
Route::get('pendingEmail/verify/{token}', [VerifyNewEmailController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('pendingEmail.verify');

//installer available only if file not exists
if (!Storage::exists('installed.txt')) {
    // Setup routes
    //
    // The group PREVIOUSLY carried no authorization check at all: no auth,
    // no gate, no signature. During the install window, therefore, anyone
    // who knew the URL could create a mainAdmin account - repeatedly - and
    // anyone could lock the installer. It cannot be tied to login, because
    // installation is exactly the phase when there is no user yet; so the
    // `installer` middleware proves filesystem access with a token instead.
    // See App\Http\Middleware\EnsureInstallerToken.
    Route::prefix('setup')->group(function () {
        // The welcome screen and the token submission are DELIBERATELY
        // outside the gate: this is where the token has to be entered, so
        // it cannot sit behind it. Neither one performs any real action.
        Route::get('/start', [MetaController::class, 'welcome'])
            ->name('setup.welcome');
        Route::post('/start', [MetaController::class, 'unlock'])
            ->name('setup.unlock');

        Route::middleware('installer')->group(function () {
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
    });
}

//Logged in users
Route::middleware(['auth'])->group(function () {
    Route::get('/home', Home::class)->name('home.home');

    // Stepping back out of impersonation.
    //
    // PREVIOUSLY this was `GET /loginback/{id}` with `signed` middleware:
    // the signed URL stayed valid for 12 hours, carried an arbitrary user
    // id, and the controller did not bind it to the session - i.e. the
    // link itself was the authorization. The id now lives in a server-side
    // session (App\Http\Controllers\Admin\LoginToUserController::SESSION_KEY),
    // the URL is empty, and the CSRF token of the `web` group provides the
    // protection.
    Route::post('/loginback', [LoginToUserController::class, 'loginBack'])
            ->name('admin.loginback');

    // The `verification.verify` NAME is registered by routes/fortify.php:87-89
    // (Laravel\Fortify\Http\Controllers\VerifyEmailController@__invoke,
    // middleware: web, Authenticate:web, ValidateSignature, ThrottleRequests:6,1).
    //
    // A SECOND definition previously sat here on the same method+URI pair,
    // with a closure. Route::get() overwrites by method+domain+URI, and
    // Fortify loads AFTER routes/web.php (config/app.php provider order),
    // so the closure never made it into the routing table - TODO 03
    // verified this empirically. Removed, with zero runtime effect. The
    // boot order is guarded by
    // RouteContractSnapshotTest::test_the_winner_depends_on_the_service_provider_boot_order,
    // because a provider-order swap would silently tip it back onto the
    // dead branch.

    Route::get('/profile/resend-new-email-verification', [Profile::class, 'resendNewEmailVerification'])->name('user.resendNewEmailVerification');

    // The two definitions share the same URI, but they CANNOT share the
    // name: route:cache (and with it `artisan optimize`) fails with a
    // LogicException on the second route of the same name. By Laravel
    // convention, `password.confirm` is the GET branch that shows the
    // form - the redirect from the `password.confirm` middleware alias
    // (app/Http/Kernel.php:68) also points here.
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
    })->middleware(['throttle:6,1'])->name('password.confirm.store');

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
                Route::get('/admin/settings', Settings::class)->name('admin.settings');
                Route::get('/admin/staticpages', StaticPages::class)->name('admin.staticpages');
                Route::get('/admin/staticpages/create', StaticPageEdit::class)->name('admin.staticpages_create');
                Route::get('/admin/staticpages/edit/{staticPage}', StaticPageEdit::class)->name('admin.staticpages_edit');
                Route::get('/admin/newsletter_edit/{id?}', NewsletterEdit::class)->name('admin.newsletter_edit');
            });

            Route::middleware(['can:is-admin'])->group(function () {
                Route::get('/admin/statistics', AdminStatistics::class)->name('admin.statistics');

                // The dedicated middleware directly protects this POST
                // endpoint too. On an expired confirmation the intended URL
                // is the admin list, not this POST-only route, so there is
                // no broken GET replay and 405 after confirming.
                Route::post('/admin/users/login/{user}', [LoginToUserController::class, 'login'])
                    ->middleware('password.confirm.impersonation')
                    ->name('admin.users.login');
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


/*
|--------------------------------------------------------------------------
| GDPR data export
|--------------------------------------------------------------------------
|
| Registered here since TODO 33.2; it used to come from the removed
| dialect/laravel-gdpr-compliance service provider. The prefix and the
| middleware are read from config/gdpr.php so the route contract stays exactly
| what it was: POST gdpr/download, web + auth.
|
| The group sits OUTSIDE every other group in this file on purpose. The
| middleware list is the config's to own, and nesting it under the file's
| auth group would let that group decide instead. "web" therefore appears
| twice - routes/web.php is itself loaded inside the web group - which is
| harmless: Router::uniqueMiddleware() collapses it, and
| RouteContractSnapshotTest gathers middleware into array keys anyway.
|
| The consent routes (gdpr-terms, gdpr-terms-accepted, gdpr-terms-denied) are
| deliberately NOT reproduced - see TODO 16 and GdprController.
*/
Route::group([
    'prefix' => config('gdpr.uri'),
    'middleware' => config('gdpr.middleware'),
], function () {
    Route::post('download', [GdprController::class, 'download'])->name('gdpr-download');
});
