<?php

namespace App\Support\Settings;

/**
 * Whether the admin "debugbar" setting can do anything on this host.
 *
 * Debugbar 4 runs only with APP_DEBUG on and outside the production and
 * testing environments (LaravelDebugbar::canBeEnabled(); the developer
 * accepted that rule at roadmap TODO 66). The settings form offers the
 * switch, and AppServiceProvider applies it, only where this is true
 * (roadmap TODO 72.2) - LaravelDebugbar::enable() itself does not check,
 * so applying it anywhere else booted the collectors for nothing.
 */
final class DebugbarSwitch
{
    public static function isAvailable(): bool
    {
        // The container first: the package is require-dev, so on a
        // `composer install --no-dev` host its class does not exist and
        // naming it would throw an Error.
        return app()->bound('debugbar') && app('debugbar')::canBeEnabled();
    }
}
