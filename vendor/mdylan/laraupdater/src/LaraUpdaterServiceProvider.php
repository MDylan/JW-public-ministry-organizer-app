<?php
/*
* @author: Pietro Cinaglia
* 	.website: http://linkedin.com/in/pietrocinaglia
*
* Fork maintained by David Molnar (https://github.com/MDylan/laraupdater).
*/

namespace MDylan\LaraUpdater;

use Illuminate\Support\ServiceProvider;

class LaraUpdaterServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->publishes([__DIR__ . '/../Config/laraupdater.php' => config_path('laraupdater.php')], 'laraupdater');
        $this->publishes([__DIR__ . '/../lang' => $this->langPublishPath()], 'laraupdater');
        $this->publishes([__DIR__ . '/../views' => resource_path('views/vendor/laraupdater')], 'laraupdater');

        // loadRoutesFrom() already skips the file when the application's routes
        // are cached, so no extra guard is needed here.
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');

        // The lang directory sits next to src/, not inside it. The previous
        // __DIR__.'/lang' pointed at src/lang, which does not exist - so the
        // `laraupdater::` namespace resolved to nothing and only the published
        // copies under the app's lang path ever worked.
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'laraupdater');
    }

    public function register()
    {
        //
    }

    /*
    * Laravel 9 moved resources/lang to lang/ at the project root and added the
    * lang_path() helper. Publish to whichever the host application uses.
    */
    private function langPublishPath()
    {
        return function_exists('lang_path') ? lang_path() : resource_path('lang');
    }
}
