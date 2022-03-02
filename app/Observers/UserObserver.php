<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\NewAdminNotification;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;


class UserObserver
{
    /**
     * Handle the User "created" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function created(User $user)
    {
        //ha admin jogot kap, megy az email
        if($user->role === 'mainAdmin') {
            $this->adminAdded($user);
        }

        // $user->notify(new UserRegisteredNotification());

        // event(new UserRegisteredNotification($user));
    }

    /**
     * Handle the User "updated" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function updated(User $user)
    {
        //ha frissítés után admin jogot kapott, értesítem a többi admint
        if($user->wasChanged('role')) {
            if($user->getOriginal('name') !== 'mainAdmin' && $user->role === 'mainAdmin') {
                $this->adminAdded($user);
            }
        }
    }

    /**
     * Handle the User "deleted" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function deleted(User $user)
    {
        //
    }

    /**
     * Handle the User "restored" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function restored(User $user)
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function forceDeleted(User $user)
    {
        //
    }

    /**
     * Értesítem a többi admint, hogy létrejött egy új admin
    */
    public function adminAdded(User $user) {

        $otherAdmins = User::where('role', '=', 'mainAdmin')->where('id', '<>', $user->id)->get();
        $cc = [];
        if(count($otherAdmins) > 0) {
            $data = [
                'newAdmin'=> $user->name, 
                'adminBy' => auth()->user()->name, 
            ];
            foreach($otherAdmins as $admin) {
                $admin->notify(new NewAdminNotification($data));
            }
        }        
    }
}
