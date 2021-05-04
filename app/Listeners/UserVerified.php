<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Auth\Events\Verified;

class UserVerified
{
    public $user;
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
     * Átállítja aktiváltra a felhasználót, amikor az email címét megerősíti
     *
     * @param  Verified  $event
     * @return void
     */
    public function handle(Verified $event)
    {

        User::where('role', '=', 'registered')
            ->where('id', $event->user->id)
            ->update(['role' => 'activated']);
        
    }
}
