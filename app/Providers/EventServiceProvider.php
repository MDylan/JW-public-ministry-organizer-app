<?php

namespace App\Providers;

use App\Listeners\LoginListener;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDay;
use App\Models\GroupLiterature;
use App\Models\GroupNews;
use App\Models\GroupNewsTranslation;
use App\Models\GroupUser;
// use App\Models\GroupUser;
use App\Models\User;
use App\Observers\EventObserver;
use App\Observers\GroupDayObserver;
use App\Observers\GroupLiteratureObserver;
use App\Observers\GroupNewsObserver;
use App\Observers\GroupNewsTranslationObserver;
use App\Observers\GroupObserver;
use App\Observers\GroupUserObserver;
// use App\Observers\GroupUserAdded;
// use Illuminate\Auth\Events\Registered;
// use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
// use Illuminate\Support\Facades\Event;
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
        // Registered::class => [
        //     SendEmailVerificationNotification::class,
        // ],
        'Illuminate\Auth\Events\Verified' => [
            'App\Listeners\UserVerified',
        ],
        Login::class => [
            LoginListener::class
        ]
        // 'App\Events\GroupUserAddedEvent' => [
        //     'App\Listeners\GroupUserAddedListener'
        // ]
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
        GroupDay::observe(GroupDayObserver::class);
        GroupLiterature::observe(GroupLiteratureObserver::class);
        GroupNews::observe(GroupNewsObserver::class);
        GroupNewsTranslation::observe(GroupNewsTranslationObserver::class);
    }
}
