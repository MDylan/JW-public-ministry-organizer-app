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
     * exactly the job it was written for - so this entry was a reviewed
     * exclusion, not a silenced failure.
     *
     * `ignition.` left the list again at TODO 55.2, together with the
     * package: Laravel 11 renders the debug page itself. Keeping a dead
     * prefix would let a returning ignition add routes unnoticed.
     */
    private const DEV_ONLY_ROUTE_PREFIXES = [
        'debugbar.',
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
        // This snapshot was written to fail in Phase 6, and it did: TODO 51.1
        // re-pinned it to Livewire 3. livewire.message and
        // livewire.message-localized are gone - version 3 has ONE endpoint,
        // and carries the locale in the component's memo instead of the URL
        // (SupportLocales). The endpoint is named default.livewire.update,
        // outside the `livewire.` prefix; see isLivewireRoute(). The upload
        // routes kept their names, and upload-file kept its throttle:60,1.
        //
        // Debugbar deliberately stays out: it is a dev-only package that
        // TODO 25 removes from config/app.php anyway, so it would bring a
        // false coupling here.
        $expected = array_filter(
            $this->fixture('vendor-route-contracts.json'),
            fn (string $name) => $this->isLivewireRoute($name),
            ARRAY_FILTER_USE_KEY
        );

        $this->assertSame(['default.livewire.update', 'livewire.preview-file', 'livewire.upload-file'], array_keys($expected));
        $this->assertSame(
            $expected,
            $this->currentRouteContracts(fn (string $name) => $this->isLivewireRoute($name)),
            'Livewire route contracts changed. Regenerate tests/Fixtures/vendor-route-contracts.json when the change is intentional.'
        );
    }

    public function test_the_unnamed_livewire_routes_are_the_two_script_routes(): void
    {
        // TODO 14 recorded that Livewire's script routes are unnamed, and so
        // outside every name-keyed snapshot here. The roadmap expected
        // Livewire 3 to name them; 3.8.8 does not (FrontendAssets::boot()),
        // so this test pins them by URI instead.
        //
        // The script's URI depends on app.debug AT BOOT: livewire.js with
        // debug on, livewire.min.js without - so a production host serves the
        // second. Neither carries any middleware, not even `web`: they are
        // static files, and a page must be able to load them before a session
        // exists.
        $script = config('app.debug') ? 'livewire/livewire.js' : 'livewire/livewire.min.js';
        $unnamed = [];

        foreach (app('router')->getRoutes() as $route) {
            if ($route->getName() === null && str_starts_with($route->uri(), 'livewire/')) {
                $unnamed[$route->uri()] = [
                    'methods' => $route->methods(),
                    'middleware' => $this->sortedMiddleware($route),
                ];
            }
        }

        ksort($unnamed);

        $this->assertSame([
            $script => ['methods' => ['GET', 'HEAD'], 'middleware' => []],
            'livewire/livewire.min.js.map' => ['methods' => ['GET', 'HEAD'], 'middleware' => []],
        ], $unnamed);
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
            $this->currentRouteContracts(fn (string $name) => str_starts_with($name, 'laraupdater.')),
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
        // middleware alias (bootstrap/app.php) redirected to a POST
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

    public function test_routes_web_is_loaded_before_routes_fortify(): void
    {
        // THIS IS THE REAL UPGRADE VALUE OF TODO 14. When two route files
        // define the same method + URI, the one loaded LAST wins the URL, so
        // the load order of routes/web.php and routes/fortify.php is part of
        // the contract. It used to be asserted through the provider order in
        // config('app.providers') (RouteServiceProvider before
        // FortifyServiceProvider); TODO 56 deletes the application's
        // RouteServiceProvider, and the framework's one boots LAST, after
        // every provider - which would have loaded web.php after Fortify.
        //
        // So the order is now asserted where it shows: in the route
        // collection itself, by one route from each file.
        $order = array_map(
            fn ($route) => $route->getName(),
            app('router')->getRoutes()->getRoutes()
        );

        $web = array_search('verification.notice', $order, true);      // routes/web.php
        $fortify = array_search('verification.verify', $order, true);  // routes/fortify.php

        $this->assertIsInt($web);
        $this->assertIsInt($fortify);
        $this->assertLessThan(
            $fortify,
            $web,
            'routes/fortify.php must load AFTER routes/web.php, or a shared method + URI would be won by the other file.'
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
        // `if (!Storage::exists('installed.txt'))` in routes/web.php:104, so
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

    public function test_the_registered_routes_contain_no_duplicate_names(): void
    {
        // TODO 62: Laravel 12 resolves a shared name to the FIRST route
        // registered with it, where uncached Laravel 11 took the last. The
        // source scan above only reads this application's route files; the
        // packages' routes (Livewire, Debugbar, the updater) are registered
        // too, and a name shared with one of them would now change which URL
        // route() builds. So the registered collection is checked as well.
        $names = array_filter(array_map(
            fn (Route $route) => $route->getName(),
            app('router')->getRoutes()->getRoutes()
        ));

        $this->assertNotEmpty($names);
        $this->assertSame(
            [],
            array_keys(array_filter(array_count_values($names), fn (int $count): bool => $count > 1))
        );
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
     * @param  (callable(string): bool)|null  $only  if given, only the names it
     *                                               accepts; if null, then the
     *                                               application's own routes
     *                                               (without vendor).
     */
    private function currentRouteContracts(?callable $only = null): array
    {
        $contracts = [];

        foreach (app('router')->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null) {
                continue;
            }

            if ($only === null) {
                if ($this->isDevOnlyRoute($name)
                    || $this->isLivewireRoute($name)
                    || str_starts_with($name, 'laraupdater.')) {
                    continue;
                }
            } elseif (! $only($name)) {
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
     * Is this one of Livewire's own routes?
     *
     * Livewire 3 registers its endpoint as `default.livewire.update` and finds
     * it again by the SUFFIX `livewire.update` (HandleRequests, where a
     * custom update route may carry any prefix). The upload routes keep the
     * `livewire.` prefix. Both rules are needed; neither alone covers all
     * three names.
     */
    private function isLivewireRoute(string $name): bool
    {
        return str_starts_with($name, 'livewire.') || str_ends_with($name, 'livewire.update');
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
