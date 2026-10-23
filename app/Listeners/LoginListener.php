<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LoginListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * Typed since TODO 56: Laravel 11 discovers listeners from app/Listeners
     * by the type of handle()'s parameter, and an untyped one is skipped.
     *
     * @param  \Illuminate\Auth\Events\Login  $event
     * @return void
     */
    public function handle(Login $event)
    {
        $event->user->update([
            'last_login_time' => now(),
            'last_login_ip' => request()->getClientIp(),
        ]);
        //load user's language
        if($event->user->language) {
            session()->put('language', $event->user->language);
        }
    }
}
