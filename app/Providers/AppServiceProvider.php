<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupLiterature;
use App\Models\GroupNews;
use App\Models\GroupNewsTranslation;
use App\Models\GroupUser;
use App\Models\Settings;
use App\Models\StaticPage;
use App\Models\StaticPageTranslation;
use App\Models\User;
use App\Observers\EventObserver;
use App\Observers\GroupLiteratureObserver;
use App\Observers\GroupNewsObserver;
use App\Observers\GroupNewsTranslationObserver;
use App\Observers\GroupObserver;
use App\Observers\GroupUserObserver;
use App\Observers\SettingsObserver;
use App\Observers\StaticPageObserver;
use App\Observers\UserObserver;
use App\Support\Settings\ApplicationSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // TODO 31: the one and only reader of the database-backed settings.
        // Bound as a singleton, because the in-request memo and the flush
        // performed by SettingsObserver have to meet on the same instance.
        $this->app->singleton(ApplicationSettings::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        /** @var ApplicationSettings $settings */
        $settings = $this->app->make(ApplicationSettings::class);

        // Publishes the language and presentation settings into the Config.
        // The old catch-everything try/catch is gone from here: handling a
        // database failure - and logging it - belongs to
        // ApplicationSettings::rows(), and applyToConfig() itself cannot throw.
        $settings->applyToConfig();

        // TODO 25: the Debugbar provider is no longer hand-registered in
        // config/app.php, so on a `composer install --no-dev` host the package
        // is simply absent. Ask the container, not the alias: a missing class
        // makes PHP throw an `Error`, not an `Exception`.
        //
        // TODO 31: this branch deliberately STAYED here instead of moving into
        // ApplicationSettings. The repository reads, the provider applies - and
        // this way the line sits outside the repository's \Throwable catch, so
        // a missing class still fails loudly rather than silently.
        if ($settings->get('debugbar') == 1 && $this->app->bound('debugbar')) {
            $this->app['debugbar']->enable();
        }

        $this->registerObservers();

        // The `api` group's limiter (`throttle:api`). It lived in
        // App\Providers\RouteServiceProvider until TODO 56 deleted that.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }

    /**
     * The model observers. They lived in App\Providers\EventServiceProvider
     * until TODO 56: Laravel 11 always registers the framework's own event
     * provider, which discovers the listeners in app/Listeners, so keeping
     * the application's one as well would have run every listener twice.
     * EventListenerContractTest pins the resulting map.
     *
     * GroupDayObserver exists but is deliberately NOT registered - see
     * ObserverCauserTest.
     */
    private function registerObservers(): void
    {
        User::observe(UserObserver::class);
        Event::observe(EventObserver::class);
        Group::observe(GroupObserver::class);
        GroupUser::observe(GroupUserObserver::class);
        GroupLiterature::observe(GroupLiteratureObserver::class);
        GroupNews::observe(GroupNewsObserver::class);
        GroupNewsTranslation::observe(GroupNewsTranslationObserver::class);

        // The side menu cache has no expiry (SetLocale
        // Cache::rememberForever). We also watch for translations, because the menu
        // shows the titles, and those live in a separate table - see the observer
        // class's explanation.
        StaticPage::observe(StaticPageObserver::class);
        StaticPageTranslation::observe(StaticPageObserver::class);

        // TODO 31: the application settings cache has no expiry either
        // (ApplicationSettings::rows). Every write from the admin screen is an
        // updateOrCreate, so this single observer covers all of them.
        Settings::observe(SettingsObserver::class);
    }
}
