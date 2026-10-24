<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;

/**
 * The action a password confirmation interrupted, offered once more.
 *
 * RequirePasswordBeforeAction cannot replay a POST/DELETE after the
 * confirmation (the redirect is a GET), so it returns the user to the page
 * the action belongs to. Until the developer tested 1.2.1 (roadmap TODO
 * 70.1) that page simply reappeared, and nothing said the exit or deletion
 * had not happened yet. Now the middleware remembers the action here, and
 * the page takes it back - once - to reopen its own confirmation dialog.
 *
 * It is only a hint for the interface: the action itself still goes through
 * the route and its middleware again, so a stale or forged entry can at most
 * open a dialog.
 */
final class ResumedAction
{
    private const KEY = 'password_confirm.resume';

    /** Seconds; long enough to type a password, short enough not to surprise. */
    private const LIFETIME = 600;

    public static function remember(Request $request): void
    {
        $route = $request->route();
        if ($route === null || $route->getName() === null) {
            return;
        }

        $parameters = [];
        foreach ($route->parameters() as $name => $value) {
            $parameters[$name] = is_object($value) && method_exists($value, 'getKey') ? $value->getKey() : $value;
        }

        $request->session()->put(self::KEY, [
            'route' => $route->getName(),
            'parameters' => $parameters,
            'at' => time(),
        ]);
    }

    /**
     * The interrupted action's route parameters when it was $routeName, the
     * password is now confirmed and the entry is fresh; null otherwise. The
     * entry is consumed either way once its route is asked for.
     *
     * @return array<string, mixed>|null
     */
    public static function take(string $routeName): ?array
    {
        $entry = session(self::KEY);
        if (! is_array($entry) || ($entry['route'] ?? null) !== $routeName) {
            return null;
        }

        session()->forget(self::KEY);

        $fresh = time() - (int) ($entry['at'] ?? 0) <= self::LIFETIME;
        $confirmed = time() - (int) session('auth.password_confirmed_at', 0) <= config('auth.password_timeout', 10800);

        return $fresh && $confirmed ? (array) ($entry['parameters'] ?? []) : null;
    }
}
