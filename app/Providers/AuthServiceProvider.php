<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('logged-in', function(User $user) {
            return $user;
        });

        Gate::define('is-admin', function(User $user) {
            return $user->hasRole('mainAdmin');
        });

        Gate::define('is-groupcreator', function(User $user) {
            if($user->hasRole('mainAdmin') || $user->hasRole('groupCreator') || $user->hasRole('translator'))
                return true;
            else return false;
        });

        Gate::define('is-translator', function(User $user) {
            if($user->hasRole('mainAdmin') || $user->hasRole('translator'))
                return true;
            else return false;
        });
    }
}
