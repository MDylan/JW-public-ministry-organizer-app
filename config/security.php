<?php

/*
|--------------------------------------------------------------------------
| Biztonsági kapcsolók
|--------------------------------------------------------------------------
|
| Ez a két kapcsoló KORÁBBAN futásidejű env() hívásokból jött - a
| HttpsProtocol middleware-ből, a CheckRecaptcha middleware-ből és hat
| Blade nézetből. A Laravel a .env fájlt csak akkor tölti be, ha nincs
| gyorsítótárazott konfiguráció, tehát egy `php artisan config:cache`
| (és vele az `artisan optimize`) után MINDKÉT kapcsoló némán hamisra
| váltott volna: nincs HTTPS-átirányítás, nincs reCAPTCHA-ellenőrzés,
| és a bejelentkezési űrlap ki sem teszi a captcha mezőt. Semmi nem
| jelzett volna hibát.
|
| A `use_recaptcha` kulcs eddig a config/events.php-ban ült - egy naptár
| konfigurációs fájlban -, ahol viszont soha semmi nem olvasta. Onnan ide
| költözött, hogy a két kapcsolónak egy otthona legyen.
|
| A filter_var() a "1", "true", "on" és "yes" alakokat egységesen kezeli.
| A .env-ben mindkét szokás előfordul: az admin felület "true"/"false"
| szót ír ki, a kézzel szerkesztett fájlokban viszont az 1/0 a gyakori.
|
*/

return [

    /*
    | Kényszerítse-e a middleware a HTTPS-re irányítást. Csak `production`
    | környezetben van hatása - lásd App\Http\Middleware\HttpsProtocol.
    */
    'use_https' => filter_var(env('USE_HTTPS', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Kérjen-e a bejelentkezés, regisztráció és jelszó-emlékeztető
    | reCAPTCHA-ellenőrzést. A kulcspár a config/services.php `recaptcha`
    | szakaszában él.
    */
    'use_recaptcha' => filter_var(env('USE_RECAPTCHA', false), FILTER_VALIDATE_BOOLEAN),

];
