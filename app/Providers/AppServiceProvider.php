<?php

namespace App\Providers;

use App\Support\Settings\ApplicationSettings;
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
    }
}
