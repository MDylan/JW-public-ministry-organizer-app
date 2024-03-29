<?php

namespace App\Observers;

use App\Jobs\CalulcateUserNameIndexProcess;
use App\Models\User;
use App\Notifications\NewAdminNotification;
use App\Notifications\UserRegisteredNotification;
use App\Notifications\UserRoleIsGroupCreatorNotification;
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
        CalulcateUserNameIndexProcess::dispatch();

    }

    /**
     * Handle the User "updated" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function updated(User $user)
    {
        //If he get "mainAdmin" privilege, notify other admins
        if($user->wasChanged('role')) {
            if($user->getOriginal('name') !== 'mainAdmin' && $user->role === 'mainAdmin') {
                $this->adminAdded($user);
            }
            if($user->getOriginal('name') !== 'groupCreator' && $user->role === 'groupCreator') {
                $user->notify(
                    new UserRoleIsGroupCreatorNotification()
                );
            }
        }
        if($user->wasChanged('name')) {
            CalulcateUserNameIndexProcess::dispatch();
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
        CalulcateUserNameIndexProcess::dispatch();
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
        CalulcateUserNameIndexProcess::dispatch();
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
