<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

/**
 * Laravel 13 renamed the framework's CSRF middleware to PreventRequestForgery
 * and kept VerifyCsrfToken as an empty, deprecated subclass of it, so this
 * class extends the new name directly; the behaviour is the same. Since
 * Laravel 13 a request whose Sec-Fetch-Site header says `same-origin` passes
 * without a token; every other request still needs one (roadmap TODO 67).
 */
class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        //add some route to disable page expired message
    ];
}
