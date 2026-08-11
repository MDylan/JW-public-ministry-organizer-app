<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * TODO 01: a snapshot of named routes' contract.
 * TODO 14: hardening the snapshot.
 *
 * The original test aggregates BY NAME: it merges all definitions sharing a
 * name into a union of their methods and middleware. This makes two things
 * invisible, and TODO 14 closes exactly these two blind spots:
 *
 * 1. DUPLICATE NAMES. `password.confirm` showed up as a single row, and
 *    `verification.verify` looked like it was a single definition - when in
 *    fact there were two in the source, and one stayed in the shadow. The
 *    v1-patch resolved both (A4 and A7), and the tests here turned that into
 *    a deliberate, readable diff instead of a silent behavior change. The
 *    duplication was not just untidiness: a single repeated name crashes
 *    `route:cache` with a LogicException, meaning `artisan optimize` never
 *    once ran successfully on this codebase.
 * 2. VENDOR ROUTES, which the prefix filter skips - so Phase 6's
 *    (Livewire 2 -> 3) routing change would have stayed silent. Since
 *    TODO 33.4, laraupdater's three endpoints also belong here: in v1 they
 *    were UNNAMED, so they fell out of every snapshot, and two of them
 *    carried no middleware at all.
 */
class RouteContractSnapshotTest extends TestCase
{
    /**
     * The route files in which the application's own `->name()` calls live.
     */
    private const ROUTE_FILES = [
        'routes/web.php',
        'routes/fortify.php',
        'routes/api.php',
    ];

    /**
     * Name prefixes of `require-dev` packages, excluded from every snapshot.
     *
     * These routes exist in the test tree and NOT on a `composer install
     * --no-dev` host, so snapshotting their contract would freeze dev-only
     * state into a fixture the production route table can never satisfy. That
     * is the same reasoning the Livewire test below gives for keeping Debugbar
     * out, applied to the second package of the same kind.
     *
     * `ignition.` joined the list in TODO 34, and the mechanism is worth
     * recording because the routes did not appear out of nowhere:
     * `facade/ignition` registered FIVE `ignition.*` routes as well, but
     * behind `if ($this->app->runningInConsole()) { return; }` - and PHPUnit
     * runs in console, so they were never in the table during a test run. Its
     * replacement, `spatie/laravel-ignition`, calls registerRoutes() from
     * boot() unconditionally, so three of them (healthCheck, executeSolution,
     * updateConfig) now are. Nothing about the application changed; what
     * changed is when the vendor package registers.
     *
     * test_every_named_route_is_accounted_for is what caught this, which is
     * exactly the job it was written for - so this entry is a reviewed
     * exclusion, not a silenced failure.
     */
    private const DEV_ONLY_ROUTE_PREFIXES = [
        'debugbar.',
        'ignition.',
    ];

    public function test_named_route_contracts_match_the_snapshot(): void
    {
        $expected = $this->fixture('route-contracts.json');

        $this->assertIsArray($expected);
        $this->assertSame(
            $expected,
            $this->currentRouteContracts(),
            'Route contracts changed. Regenerate tests/Fixtures/route-contracts.json when route updates are intentional.'
        );
    }

    public function test_the_livewire_vendor_routes_match_the_snapshot(): void
    {
        // Livewire 3 renames the message endpoint (livewire.update) and
        // reworks uploads, so this snapshot WILL CERTAINLY fail in Phase 6 -
        // that is exactly why it is here: let the routing change be an
        // explicit diff.
        //
        // Debugbar deliberately stays out: it is a dev-only package that
        // TODO 25 removes from config/app.php anyway, so it would bring a
        // false coupling here.
        $expected = $this->fixtureSubset('vendor-route-contracts.json', 'livewire.');

        $this->assertNotEmpty($expected);
        $this->assertSame(
            $expected,
            $this->currentRouteContracts('livewire.'),
            'Livewire route contracts changed. Regenerate tests/Fixtures/vendor-route-contracts.json when the change is intentional.'
        );
    }

    public function test_the_laraupdater_vendor_routes_match_the_snapshot(): void
    {
        // TODO 33.4: mdylan/laraupdater v2 puts all THREE endpoints behind
        // config('laraupdater.middleware') and names them. In v1,
        // updater.check and updater.currentVersion were UNNAMED and received
        // no middleware at all - not even `web` - so anyone could read out
        // the installed version. Being unnamed, they also fell out of this
        // snapshot; this test closes that blind spot.
        $expected = $this->fixtureSubset('vendor-route-contracts.json', 'laraupdater.');

        $this->assertNotEmpty($expected);
        $this->assertSame(
            $expected,
            $this->currentRouteContracts('laraupdater.'),
            'LaraUpdater route contracts changed. Regenerate tests/Fixtures/vendor-route-contracts.json when the change is intentional.'
        );
    }

    public function test_every_named_route_is_accounted_for(): void
    {
        // The two fixtures plus the dev-only prefixes together cover ALL named
        // routes. This catches it if a package update sneaks in a new named
        // route: it cannot disappear silently behind the prefix filter. It
        // earned its keep in TODO 34 - see DEV_ONLY_ROUTE_PREFIXES.
        $known = array_merge(
            array_keys($this->fixture('route-contracts.json')),
            array_keys($this->fixture('vendor-route-contracts.json'))
        );

        $unaccounted = [];

        foreach (app('router')->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || $this->isDevOnlyRoute($name)) {
                continue;
            }

            if (! in_array($name, $known, true)) {
                $unaccounted[] = $name;
            }
        }

        $this->assertSame([], array_values(array_unique($unaccounted)));
    }

    public function test_the_fortify_definition_wins_the_verification_verify_name(): void
    {
        // `verification.verify` is defined twice with the same URI
        // (routes/web.php:119 closure and routes/fortify.php:87 controller).
        // RouteCollection::addToCollections() writes on a
        // `method + domain + uri` key, so the one registered LATER overwrites
        // the earlier one - and Fortify is the later one.
        $routes = $this->routesNamed('verification.verify');

        $this->assertCount(1, $routes, 'Csak egy definíció juthat be a routing táblába.');
        $this->assertSame(
            'Laravel\Fortify\Http\Controllers\VerifyEmailController@__invoke',
            $routes[0]->getActionName()
        );
        $this->assertSame(
            ['auth:web', 'signed', 'throttle:6,1', 'web'],
            $this->sortedMiddleware($routes[0])
        );
    }

    public function test_the_web_php_verification_verify_closure_is_gone(): void
    {
        // REVERSED by the v1-patch A4 fix (TODO 26).
        //
        // Previously there were TWO definitions in the source, one in the
        // routing table - the difference was itself the dead code. The
        // closure in routes/web.php is now deleted, so the source and the
        // runtime table finally agree.
        $this->assertSame(1, $this->sourceRouteNameCounts()['verification.verify']);
        $this->assertCount(1, $this->routesNamed('verification.verify'));

        foreach ($this->routesNamed('verification.verify') as $route) {
            $this->assertNotSame('Closure', $route->getActionName());
        }

        // The deletion meant zero runtime change: what remained is exactly
        // what had already been winning.
        $this->assertStringNotContainsString(
            "name('verification.verify')",
            file_get_contents(base_path('routes/web.php'))
        );
    }

    public function test_the_two_confirm_password_definitions_carry_different_names(): void
    {
        // REVERSED by the v1-patch A7 fix (TODO 26). Previously both
        // definitions carried the `password.confirm` name, and this test
        // recorded that BOTH are alive - which was true, but at the same
        // time made the route table uncacheable, see
        // test_the_route_table_survives_route_cache.
        //
        // The two definitions still live on, with unchanged URI and
        // middleware, just under separate names now. The union-based
        // snapshot used to merge them into one row and hid the fact that
        // throttle is ONLY on the POST.
        $get = $this->routesNamed('password.confirm');
        $post = $this->routesNamed('password.confirm.store');

        $this->assertCount(1, $get);
        $this->assertCount(1, $post);

        $this->assertSame(['auth', 'web'], $this->sortedMiddleware($get[0]));
        $this->assertSame(['auth', 'throttle:6,1', 'web'], $this->sortedMiddleware($post[0]));

        // The URI stays identically the same, so the fix does not move a single URL.
        $this->assertSame('confirm-password', $get[0]->uri());
        $this->assertSame('confirm-password', $post[0]->uri());
        $this->assertContains('GET', $get[0]->methods());
        $this->assertContains('POST', $post[0]->methods());
    }

    public function test_url_generation_resolves_password_confirm_to_the_get_definition(): void
    {
        // REVERSED by the v1-patch A7 fix. Previously the LAST definition in
        // the nameList won - i.e. the POST one - and the `password.confirm`
        // middleware alias (app/Http/Kernel.php:68) redirected to a POST
        // route's name. It only worked because the two URIs were identical.
        // The name now points, per Laravel's convention, to the GET branch
        // that shows the form, so RequirePassword's redirect finally lands on
        // its own method.
        $resolved = app('router')->getRoutes()->getByName('password.confirm');

        $this->assertNotNull($resolved);
        $this->assertContains('GET', $resolved->methods());
        $this->assertNotContains('POST', $resolved->methods());

        // The generated URL, however, is letter-for-letter the same as before the fix.
        $this->assertSame('/confirm-password', route('password.confirm', [], false));
        $this->assertSame('/confirm-password', route('password.confirm.store', [], false));
    }

    public function test_the_route_table_survives_route_cache(): void
    {
        // THIS IS THE REAL STAKE OF THE FIX. `artisan optimize` (and
        // `route:cache`) converts the whole table into a Symfony route
        // collection, where the name is a UNIQUE KEY: a second route with the
        // same name throws a LogicException, and the entire command fails.
        // So because of the duplicated `password.confirm`, the route cache
        // NEVER ran successfully on this codebase - not even on v1.
        //
        // We walk the same code path as the command, just without writing a
        // file: it is AbstractRouteCollection::toSymfonyRouteCollection()
        // that throws.
        $symfony = app('router')->getRoutes()->toSymfonyRouteCollection();

        $this->assertNotEmpty($symfony->all());
        $this->assertNotNull($symfony->get('password.confirm'));
        $this->assertNotNull($symfony->get('password.confirm.store'));
    }

    public function test_the_winner_depends_on_the_service_provider_boot_order(): void
    {
        // THIS IS THE REAL UPGRADE VALUE OF TODO 14. The winner of
        // `verification.verify` depends solely on which provider loads its
        // own route file first. The Laravel 11 skeleton migration (TODO 56)
        // moves the providers into bootstrap/providers.php - a reordering
        // there would silently tip the route back onto the dead closure.
        $providers = config('app.providers');

        $routeProvider = array_search(\App\Providers\RouteServiceProvider::class, $providers, true);
        $fortifyProvider = array_search(\App\Providers\FortifyServiceProvider::class, $providers, true);

        $this->assertIsInt($routeProvider);
        $this->assertIsInt($fortifyProvider);
        $this->assertLessThan(
            $fortifyProvider,
            $routeProvider,
            'A Fortify a routes/web.php UTÁN kell regisztráljon, különben a verification.verify a halott closure-re esik vissza.'
        );
    }

    public function test_the_route_files_contain_no_duplicate_names(): void
    {
        // REVERSED by the v1-patch A4 (deleting `verification.verify`) and A7
        // (splitting `password.confirm`) fixes, TODO 26. Both definition
        // pairs live on, just under separate names now - see
        // test_the_two_confirm_password_definitions_carry_different_names.
        //
        // WARNING: never compare the source scanner's COUNT against
        // route-contracts.json. The setup.* routes sit behind
        // `if (!Storage::exists('installed.txt'))` in routes/web.php:78, so
        // they are absent from the runtime snapshot but present in the
        // source. It can still be used for duplicate filtering regardless:
        // the setup names are unique.
        $duplicates = array_filter(
            $this->sourceRouteNameCounts(),
            fn (int $count): bool => $count > 1
        );

        // REVERSED by the v1-patch A7 fix: `password.confirm`'s second
        // definition received the name `password.confirm.store`, so there is
        // NOT A SINGLE duplicated name left in the source. From here on the
        // guard catches the appearance of any new duplicate - and since even
        // one duplicate makes the route table uncacheable, this is now a
        // deployment failure, not just untidiness.
        $this->assertSame([], $duplicates);
    }

    public function test_the_route_files_use_no_group_level_name_prefixes(): void
    {
        // The source scanner above assumes a flat namespace: every name comes
        // from a single ->name('...') call. A group-level prefix
        // (Route::name() or 'as' => in the group's array) would break this,
        // so we exclude it.
        foreach (self::ROUTE_FILES as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringNotContainsString('Route::name(', $source, $file);
            $this->assertStringNotContainsString("'as' =>", $source, $file);
        }
    }

    /**
     * The current route contract, aggregated by name.
     *
     * @param  string|null  $onlyPrefix  if given, only names starting with
     *                                   this prefix; if null, then the
     *                                   application's own routes (without vendor).
     */
    private function currentRouteContracts(?string $onlyPrefix = null): array
    {
        $contracts = [];

        foreach (app('router')->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null) {
                continue;
            }

            if ($onlyPrefix === null) {
                if ($this->isDevOnlyRoute($name)
                    || str_starts_with($name, 'livewire.')
                    || str_starts_with($name, 'laraupdater.')) {
                    continue;
                }
            } elseif (! str_starts_with($name, $onlyPrefix)) {
                continue;
            }

            if (! isset($contracts[$name])) {
                $contracts[$name] = [
                    'methods' => [],
                    'middleware' => [],
                ];
            }

            foreach ($route->methods() as $method) {
                $contracts[$name]['methods'][$method] = true;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                $contracts[$name]['middleware'][$middleware] = true;
            }
        }

        ksort($contracts);

        $normalized = [];
        foreach ($contracts as $name => $meta) {
            $methods = array_keys($meta['methods']);
            sort($methods);

            $middleware = array_keys($meta['middleware']);
            sort($middleware);

            $normalized[$name] = [
                'methods' => $methods,
                'middleware' => $middleware,
            ];
        }

        return $normalized;
    }

    /**
     * The fixture's entries whose name starts with the given prefix.
     */
    private function fixtureSubset(string $file, string $prefix): array
    {
        return array_filter(
            $this->fixture($file),
            fn (string $name) => str_starts_with($name, $prefix),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function fixture(string $file): array
    {
        return json_decode(
            file_get_contents(base_path('tests/Fixtures/'.$file)),
            true
        );
    }

    /**
     * All routes ACTUALLY registered under the given name.
     *
     * @return \Illuminate\Routing\Route[]
     */
    private function routesNamed(string $name): array
    {
        $matches = [];

        foreach (app('router')->getRoutes() as $route) {
            if ($route->getName() === $name) {
                $matches[] = $route;
            }
        }

        return $matches;
    }

    /**
     * Does this route name come from a `require-dev` package?
     */
    private function isDevOnlyRoute(string $name): bool
    {
        foreach (self::DEV_ONLY_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function sortedMiddleware(Route $route): array
    {
        $middleware = array_values(array_unique($route->gatherMiddleware()));
        sort($middleware);

        return $middleware;
    }

    /**
     * The count of `->name('...')` calls PRESENT in the route files, by name.
     *
     * Uses a tokenizer, not a regex: this naturally excludes commented-out
     * Fortify definitions (e.g. verification.notice in
     * routes/fortify.php:80-85), because the comment is a separate token.
     *
     * @return array<string, int>
     */
    private function sourceRouteNameCounts(): array
    {
        $counts = [];

        foreach (self::ROUTE_FILES as $file) {
            $tokens = token_get_all(file_get_contents(base_path($file)));
            $total = count($tokens);

            for ($i = 0; $i < $total; $i++) {
                if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_OBJECT_OPERATOR) {
                    continue;
                }

                $j = $this->nextMeaningfulToken($tokens, $i + 1);
                if ($j === null || ! is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== 'name') {
                    continue;
                }

                $j = $this->nextMeaningfulToken($tokens, $j + 1);
                if ($j === null || $tokens[$j] !== '(') {
                    continue;
                }

                $j = $this->nextMeaningfulToken($tokens, $j + 1);
                if ($j === null || ! is_array($tokens[$j]) || $tokens[$j][0] !== T_CONSTANT_ENCAPSED_STRING) {
                    continue;
                }

                $name = trim($tokens[$j][1], "'\"");
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * The next token's index, skipping whitespace and comments.
     */
    private function nextMeaningfulToken(array $tokens, int $from): ?int
    {
        $total = count($tokens);

        for ($i = $from; $i < $total; $i++) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }
}
