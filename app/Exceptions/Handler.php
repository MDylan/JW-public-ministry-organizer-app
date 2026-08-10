<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (\Illuminate\Encryption\MissingAppKeyException $e) {
            $target = base_path() . "/.env";
            $from = base_path() . "/.env.example";
            if(!file_exists($target) && file_exists($from)) {
                copy($from, $target);
                Artisan::call("key:generate");
                return redirect()->route('setup.welcome');
            }

            // Otherwise we fall back to the built-in handler: once null is returned,
            // the framework renders the errors/500 view. Previously
            // there was a dd() here, which printed a raw message regardless of
            // APP_DEBUG and terminated with exit.
            return null;
        });

        $this->renderable(function (\Illuminate\Database\QueryException $e) {
            if (!Storage::exists('installed.txt')) {
                return redirect()->route('setup.welcome');
            }

            // On an installed site, the normal error handling is the correct response:
            // report() has already run (Pipeline::handleException calls it before render()),
            // so the exception is logged, and the user
            // gets an error page instead of the raw database error message.
            return null;
        });
    }
}
