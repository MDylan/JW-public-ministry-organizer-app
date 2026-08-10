<?php

/*
|--------------------------------------------------------------------------
| Security switches
|--------------------------------------------------------------------------
|
| These two switches PREVIOUSLY came from runtime env() calls - from the
| HttpsProtocol middleware, the CheckRecaptcha middleware and six Blade
| views. Laravel only loads the .env file when there is no cached
| configuration, so after a `php artisan config:cache` (and with it
| `artisan optimize`) BOTH switches would have silently flipped to false:
| no HTTPS redirect, no reCAPTCHA check, and the login form does not even
| render the captcha field. Nothing would have signalled an error.
|
| The `use_recaptcha` key used to sit in config/events.php - a calendar
| configuration file - where nothing ever read it. It moved here so both
| switches have one home.
|
| filter_var() treats "1", "true", "on" and "yes" uniformly. Both
| conventions occur in .env: the admin UI writes out the "true"/"false"
| word, while hand-edited files commonly use 1/0.
|
*/

return [

    /*
    | Whether the middleware should force an HTTPS redirect. Only has an
    | effect in the `production` environment - see App\Http\Middleware\HttpsProtocol.
    */
    'use_https' => filter_var(env('USE_HTTPS', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Whether login, registration and the password reminder should require
    | a reCAPTCHA check. The key pair lives in config/services.php's
    | `recaptcha` section.
    */
    'use_recaptcha' => filter_var(env('USE_RECAPTCHA', false), FILTER_VALIDATE_BOOLEAN),

];
