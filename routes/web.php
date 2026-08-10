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
    // KORÁBBAN GET volt. Az aláírás azt igazolja, hogy a linket mi adtuk ki -
    // azt nem, hogy a felhasználó SZÁNDÉKOSAN nyitotta meg. Egy adatot törlő
    // GET-et a böngésző előtöltése, a levelezőrendszer linkellenőrzője vagy egy
    // véletlen navigáció is elsütheti. POST + CSRF mellett ez nem fordulhat elő.
    Route::post('/finish-registration/{id}/cancel', [FinishRegistration::class, 'cancel'])
        ->name('finish_registration_cancel');
});


// Az `auth` KORÁBBAN hiányzott. Jogosultságmegkerülés nem látszott belőle - a
// levélküldő POST külön védett -, de a nézet `<x-admin-layout>`-ot használ, ami
// vendégnél `auth()->user()`-t dereferál: minden kijelentkezett látogatás 500-at
// és egy stack trace-t adott a naplóba.
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
    // A csoport KORÁBBAN egyetlen jogosultsági ellenőrzést sem hordozott: se
    // auth, se gate, se aláírás. A telepítési ablakban tehát bárki, aki ismerte
    // a címet, létrehozhatott mainAdmin fiókot - ismételten -, és bárki
    // lezárhatta a telepítőt. Bejelentkezéshez kötni nem lehet, mert a
    // telepítés pontosan az a szakasz, amikor még nincs felhasználó; ezért az
    // `installer` middleware fájlrendszer-hozzáférést bizonyíttat egy tokennel.
    // Lásd App\Http\Middleware\EnsureInstallerToken.
    Route::prefix('setup')->group(function () {
        // A nyitóképernyő és a token beküldése SZÁNDÉKOSAN a kapun kívül van:
        // ide kell beírni a tokent, tehát nem lehet mögötte. Érdemi műveletet
        // egyik sem végez.
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

    // Visszalépés a megszemélyesítésből.
    //
    // KORÁBBAN `GET /loginback/{id}` volt, `signed` middleware-rel: az aláírt
    // URL 12 óráig érvényes maradt, tetszőleges felhasználói azonosítót
    // hordozott, és a controller nem kötötte a munkamenethez - vagyis a link
    // maga volt a jogosultság. Az azonosító innentől szerveroldali sessionben
    // van (App\Http\Controllers\Admin\LoginToUserController::SESSION_KEY), az
    // URL üres, a védelmet pedig a `web` csoport CSRF-tokenje adja.
    Route::post('/loginback', [LoginToUserController::class, 'loginBack'])
            ->name('admin.loginback');

    // A `verification.verify` NEVET a routes/fortify.php:87-89 regisztrálja
    // (Laravel\Fortify\Http\Controllers\VerifyEmailController@__invoke,
    // middleware: web, Authenticate:web, ValidateSignature, ThrottleRequests:6,1).
    //
    // Itt korábban egy MÁSODIK definíció állt ugyanerre a metódus+URI párra egy
    // closure-rel. A Route::get() metódus+domain+URI szerint felülír, a Fortify
    // pedig a routes/web.php UTÁN töltődik be (config/app.php provider-sorrend),
    // ezért a closure soha nem került be a routing táblába - a TODO 03 ezt
    // empirikusan igazolta. Törölve, nulla futásidejű hatással. A boot-sorrendet
    // a RouteContractSnapshotTest::test_the_winner_depends_on_the_service_provider_boot_order
    // őrzi, mert egy provider-sorrendcsere némán visszabillentené a halott ágra.

    Route::get('/profile/resend-new-email-verification', [Profile::class, 'resendNewEmailVerification'])->name('user.resendNewEmailVerification');

    // A két definíció URI-ja azonos, a nevet viszont NEM oszthatják meg: a
    // route:cache (és vele az `artisan optimize`) LogicExceptionnel elhasal a
    // második azonos nevű route-on. A `password.confirm` a Laravel konvenciója
    // szerint az űrlapot mutató GET ág - ide mutat a `password.confirm`
    // middleware-alias (app/Http/Kernel.php:68) átirányítása is.
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

                // A külön middleware közvetlenül ezt a POST végpontot is védi.
                // Lejárt megerősítésnél az intended URL az adminlista, nem ez a
                // POST-only route, így a megerősítés után nincs hibás GET-es
                // újrajátszás és 405.
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
