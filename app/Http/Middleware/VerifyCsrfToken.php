<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        //add some route to disable page expired message
        //based on this: https://vemto.app/blog/laravel-livewire-how-to-disable-csrf-token-to-embed-a-component-on-iframe
        'livewire/message/home',
        'livewire/message/events.events'
    ];
}
