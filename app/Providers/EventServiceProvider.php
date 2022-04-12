<?php

namespace App\Providers;

use App\Listeners\LoginListener;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupLiterature;
use App\Models\GroupNews;
use App\Models\GroupNewsTranslation;
use App\Models\GroupUser;
use App\Models\User;
use App\Observers\EventObserver;
use App\Observers\GroupLiteratureObserver;
use App\Observers\GroupNewsObserver;
use App\Observers\GroupNewsTranslationObserver;
use App\Observers\GroupObserver;
use App\Observers\GroupUserObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Login;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Illuminate\Auth\Events\Verified' => [
            'App\Listeners\UserVerified',
        ],
        Login::class => [
            LoginListener::class
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        User::observe(UserObserver::class);
        Event::observe(EventObserver::class);
        Group::observe(GroupObserver::class);
        GroupUser::observe(GroupUserObserver::class);
        GroupLiterature::observe(GroupLiteratureObserver::class);
        GroupNews::observe(GroupNewsObserver::class);
        GroupNewsTranslation::observe(GroupNewsTranslationObserver::class);
    }
}
