<?php

namespace Tests\Feature\Events;

use Tests\TestCase;

/**
 * Roadmap TODO 56: the event and observer map, pinned before the skeleton move.
 *
 * Until TODO 56 every listener and observer was registered by
 * App\Providers\EventServiceProvider. Laravel 11's Application::configure()
 * always registers the framework's own EventServiceProvider as well, and that
 * one discovers app/Listeners - so keeping the application's provider next to
 * it would run each listener twice, and leaving a listener untyped would let
 * discovery miss it. Both failures are silent: an event with two listeners or
 * none still dispatches without complaint.
 *
 * So the map is written down here, exactly, and the move has to leave it as
 * it is. Each auth event has precisely one listener; the observers are listed
 * one row per `eloquent.*` registration, which makes a missing observer, a
 * doubled one, and GroupDayObserver coming back (ObserverCauserTest says why
 * it must not) all fail the same assertion.
 */
class EventListenerContractTest extends TestCase
{
    public function test_each_auth_event_has_exactly_its_one_listener(): void
    {
        $this->assertSame(['App\Listeners\LoginListener'], $this->listenersOf('Illuminate\Auth\Events\Login'));
        $this->assertSame(['App\Listeners\UserVerified'], $this->listenersOf('Illuminate\Auth\Events\Verified'));

        // TODO 55.3: the framework's listener is the one sender of the
        // registration mail - see RegistrationVerificationMailTest.
        $this->assertSame(
            ['Illuminate\Auth\Listeners\SendEmailVerificationNotification'],
            $this->listenersOf('Illuminate\Auth\Events\Registered')
        );
    }

    public function test_the_observer_map_is_exactly_the_registered_one(): void
    {
        $rows = [];

        foreach ($this->app['events']->getRawListeners() as $event => $listeners) {
            if (! str_starts_with($event, 'eloquent.')) {
                continue;
            }

            foreach ($listeners as $listener) {
                // Closures are the packages' own (activitylog's LogsActivity);
                // the application registers observers by class name only.
                if (is_string($listener)) {
                    $rows[] = $event.' => '.$listener;
                }
            }
        }

        sort($rows);

        $this->assertSame(self::OBSERVER_MAP, $rows);
    }

    private function listenersOf(string $event): array
    {
        return array_values(array_filter(
            $this->app['events']->getRawListeners()[$event] ?? [],
            'is_string'
        ));
    }

    /** Sorted; read from `event:list` on 2026-10-23, before TODO 56. */
    private const OBSERVER_MAP = [
        'eloquent.created: App\Models\Event => App\Observers\EventObserver@created',
        'eloquent.created: App\Models\GroupLiterature => App\Observers\GroupLiteratureObserver@created',
        'eloquent.created: App\Models\GroupNews => App\Observers\GroupNewsObserver@created',
        'eloquent.created: App\Models\GroupNewsTranslation => App\Observers\GroupNewsTranslationObserver@created',
        'eloquent.created: App\Models\GroupUser => App\Observers\GroupUserObserver@created',
        'eloquent.created: App\Models\User => App\Observers\UserObserver@created',
        'eloquent.deleted: App\Models\Event => App\Observers\EventObserver@deleted',
        'eloquent.deleted: App\Models\Group => App\Observers\GroupObserver@deleted',
        'eloquent.deleted: App\Models\GroupLiterature => App\Observers\GroupLiteratureObserver@deleted',
        'eloquent.deleted: App\Models\GroupNews => App\Observers\GroupNewsObserver@deleted',
        'eloquent.deleted: App\Models\GroupNewsTranslation => App\Observers\GroupNewsTranslationObserver@deleted',
        'eloquent.deleted: App\Models\GroupUser => App\Observers\GroupUserObserver@deleted',
        'eloquent.deleted: App\Models\Settings => App\Observers\SettingsObserver@deleted',
        'eloquent.deleted: App\Models\StaticPage => App\Observers\StaticPageObserver@deleted',
        'eloquent.deleted: App\Models\StaticPageTranslation => App\Observers\StaticPageObserver@deleted',
        'eloquent.deleted: App\Models\User => App\Observers\UserObserver@deleted',
        'eloquent.forceDeleted: App\Models\GroupLiterature => App\Observers\GroupLiteratureObserver@forceDeleted',
        'eloquent.forceDeleted: App\Models\GroupNews => App\Observers\GroupNewsObserver@forceDeleted',
        'eloquent.forceDeleted: App\Models\GroupNewsTranslation => App\Observers\GroupNewsTranslationObserver@forceDeleted',
        'eloquent.forceDeleted: App\Models\GroupUser => App\Observers\GroupUserObserver@forceDeleted',
        'eloquent.forceDeleted: App\Models\User => App\Observers\UserObserver@forceDeleted',
        'eloquent.restored: App\Models\GroupLiterature => App\Observers\GroupLiteratureObserver@restored',
        'eloquent.restored: App\Models\GroupNews => App\Observers\GroupNewsObserver@restored',
        'eloquent.restored: App\Models\GroupNewsTranslation => App\Observers\GroupNewsTranslationObserver@restored',
        'eloquent.restored: App\Models\GroupUser => App\Observers\GroupUserObserver@restored',
        'eloquent.restored: App\Models\User => App\Observers\UserObserver@restored',
        'eloquent.saved: App\Models\Settings => App\Observers\SettingsObserver@saved',
        'eloquent.saved: App\Models\StaticPage => App\Observers\StaticPageObserver@saved',
        'eloquent.saved: App\Models\StaticPageTranslation => App\Observers\StaticPageObserver@saved',
        'eloquent.updated: App\Models\Event => App\Observers\EventObserver@updated',
        'eloquent.updated: App\Models\Group => App\Observers\GroupObserver@updated',
        'eloquent.updated: App\Models\GroupLiterature => App\Observers\GroupLiteratureObserver@updated',
        'eloquent.updated: App\Models\GroupNews => App\Observers\GroupNewsObserver@updated',
        'eloquent.updated: App\Models\GroupNewsTranslation => App\Observers\GroupNewsTranslationObserver@updated',
        'eloquent.updated: App\Models\GroupUser => App\Observers\GroupUserObserver@updated',
        'eloquent.updated: App\Models\User => App\Observers\UserObserver@updated',
    ];
}
