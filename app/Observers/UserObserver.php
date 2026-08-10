<?php

namespace App\Observers;

use App\Jobs\CalulcateUserNameIndexProcess;
use App\Models\User;
use App\Notifications\NewAdminNotification;
use App\Notifications\UserRegisteredNotification;
use App\Notifications\UserRoleIsGroupCreatorNotification;
use App\Support\Concerns\ResolvesCauser;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;


class UserObserver
{
    use ResolvesCauser;
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
        // TODO 33.5, defect 3: the pending_user_emails row is attached through
        // `morphs()`, so there is NO foreign key - a deleted user's pending
        // address stayed orphaned in the table indefinitely. And that row holds
        // a REAL, never-confirmed e-mail address of that user.
        //
        // User has no SoftDeletes (every `deleted_at` reference in the model is
        // a pivot column), so this event means an actual delete and no separate
        // forceDeleted branch is needed.
        $user->clearPendingEmail();

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
     * Notify the other admins that a new admin has been created
    */
    public function adminAdded(User $user) {

        $otherAdmins = User::where('role', '=', 'mainAdmin')->where('id', '<>', $user->id)->get();
        $cc = [];
        if(count($otherAdmins) > 0) {
            $data = [
                'newAdmin'=> $user->name,
                'adminBy' => $this->causerName(),
            ];
            foreach($otherAdmins as $admin) {
                $admin->notify(new NewAdminNotification($data));
            }
        }        
    }
}
