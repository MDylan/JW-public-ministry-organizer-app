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

            // Egyébként a beépített kezelőre esünk vissza: a null visszatérés
            // után a keretrendszer rendereli az errors/500 nézetet. Korábban
            // itt dd() állt, ami az APP_DEBUG-tól függetlenül nyers üzenetet
            // írt ki, és exit-tel zárt.
            return null;
        });

        $this->renderable(function (\Illuminate\Database\QueryException $e) {
            if (!Storage::exists('installed.txt')) {
                return redirect()->route('setup.welcome');
            }

            // Telepített oldalon a normál hibakezelés a helyes válasz: a
            // report() már lefutott (Pipeline::handleException hívja a render()
            // előtt), tehát a kivétel naplózva van, a felhasználó pedig
            // hibaoldalt kap a nyers adatbázis-üzenet helyett.
            return null;
        });
    }
}
