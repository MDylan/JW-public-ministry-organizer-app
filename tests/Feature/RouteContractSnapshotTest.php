<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * TODO 01: a nevesített route-ok szerződésének pillanatképe.
 * TODO 14: a pillanatkép megerősítése.
 *
 * Az eredeti teszt NÉV SZERINT aggregál: egy névhez tartozó összes definíció
 * metódusait és middleware-eit unióba olvasztja. Emiatt két dolog láthatatlan,
 * és a TODO 14 pontosan ezt a két vakfoltot zárja be:
 *
 * 1. A DUPLIKÁLT NEVEK. A `password.confirm` egyetlen sorként jelenik meg, a
 *    `verification.verify` pedig úgy néz ki, mintha egyetlen definíció lenne -
 *    holott a forrásban kettő van, és az egyik árnyékban marad. A TODO 26 majd
 *    törli a halott definíciót; az itteni tesztek teszik azt a törlést
 *    szándékos, olvasható diffé egy csendes viselkedésváltozás helyett.
 * 2. A VENDOR ROUTE-OK, amiket a prefix-szűrő kihagy - így a Phase 6
 *    (Livewire 2 -> 3) routing-változása néma maradna. TODO 33.4 óta a
 *    laraupdater három végpontja is ide tartozik: v1-ben NÉVTELENEK voltak,
 *    ezért kiestek minden pillanatképből, és kettő közülük semmilyen
 *    middleware-t nem viselt.
 */
class RouteContractSnapshotTest extends TestCase
{
    /**
     * A route-fájlok, amelyekben az alkalmazás saját `->name()` hívásai állnak.
     */
    private const ROUTE_FILES = [
        'routes/web.php',
        'routes/fortify.php',
        'routes/api.php',
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
        // A Livewire 3 átnevezi a message végpontot (livewire.update) és
        // átalakítja a feltöltéseket, tehát ez a pillanatkép Phase 6-ban BIZTOSAN
        // elbukik - épp ezért van itt: legyen a routing-változás explicit diff.
        //
        // A Debugbar szándékosan marad kint: dev-only csomag, amit a TODO 25 amúgy
        // is kivesz a config/app.php-ból, tehát hamis csatolást hozna ide.
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
        // TODO 33.4: a mdylan/laraupdater v2 mind a HÁROM végpontot a
        // config('laraupdater.middleware') mögé teszi, és nevesíti őket. Az
        // v1-ben az updater.check és az updater.currentVersion NÉVTELEN volt és
        // semmilyen middleware-t nem kapott - még `web`-et sem -, tehát bárki
        // kiolvashatta a telepített verziót. Névtelenül ki is estek ebből a
        // pillanatképből; ez a teszt zárja be azt a vakfoltot.
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
        // A két fixture plusz a debugbar prefix együtt lefedi az ÖSSZES nevesített
        // route-ot. Ez fogja meg, ha egy csomagfrissítés új nevesített route-ot
        // csempész be: az nem tűnhet el csendben a prefix-szűrő mögött.
        $known = array_merge(
            array_keys($this->fixture('route-contracts.json')),
            array_keys($this->fixture('vendor-route-contracts.json'))
        );

        $unaccounted = [];

        foreach (app('router')->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || str_starts_with($name, 'debugbar.')) {
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
        // A `verification.verify` kétszer van definiálva azonos URI-val
        // (routes/web.php:119 closure és routes/fortify.php:87 controller). A
        // RouteCollection::addToCollections() `method + domain + uri` kulcsra ír,
        // tehát a KÉSŐBB regisztrált felülírja a korábbit - és a Fortify a későbbi.
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

    public function test_the_web_php_verification_verify_closure_never_reaches_the_routing_table(): void
    {
        // A forrásban két definíció van, a gyűjteményben egy: a különbség maga a
        // halott kód. A TODO 26 ezt a closure-t törli, nulla futásidejű hatással.
        $this->assertSame(2, $this->sourceRouteNameCounts()['verification.verify']);
        $this->assertCount(1, $this->routesNamed('verification.verify'));

        foreach ($this->routesNamed('verification.verify') as $route) {
            $this->assertNotSame('Closure', $route->getActionName());
        }
    }

    public function test_both_password_confirm_definitions_survive_with_different_middleware(): void
    {
        // Itt a két definíció HTTP metódusban tér el (routes/web.php:126 GET és
        // :130 POST), tehát külön kulcsra kerül, és mindkettő életben marad. Az
        // unió-alapú pillanatkép ezt egy sorrá mossa össze, és elrejti, hogy a
        // throttle CSAK a POST-on van.
        $routes = $this->routesNamed('password.confirm');
        $this->assertCount(2, $routes);

        $get = $this->firstRouteWithMethod($routes, 'GET');
        $post = $this->firstRouteWithMethod($routes, 'POST');

        $this->assertSame(['auth', 'web'], $this->sortedMiddleware($get));
        $this->assertSame(['auth', 'throttle:6,1', 'web'], $this->sortedMiddleware($post));

        $this->assertSame('confirm-password', $get->uri());
        $this->assertSame('confirm-password', $post->uri());
    }

    public function test_url_generation_resolves_password_confirm_to_the_post_definition(): void
    {
        // Az URL-generálás a nameList-ből dolgozik, amit a RouteServiceProvider az
        // app->booted() callbackben refreshNameLookups()-szal épít újra az
        // allRoutes beszúrási sorrendje szerint - tehát az UTOLSÓ definíció nyer.
        // A `password.confirm` middleware-alias (app/Http/Kernel.php:68) is erre a
        // névre irányít át.
        $resolved = app('router')->getRoutes()->getByName('password.confirm');

        $this->assertNotNull($resolved);
        $this->assertContains('POST', $resolved->methods());
        $this->assertNotContains('GET', $resolved->methods());
    }

    public function test_the_winner_depends_on_the_service_provider_boot_order(): void
    {
        // EZ A TODO 14 VALÓDI UPGRADE-ÉRTÉKE. A `verification.verify` győztese
        // kizárólag attól függ, hogy melyik provider tölti be előbb a maga
        // route-fájlját. A Laravel 11 skeleton-migráció (TODO 56) a providereket a
        // bootstrap/providers.php-ba mozgatja - egy ottani sorrendcsere némán
        // átbillentené a route-ot a halott closure-re.
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

    public function test_the_route_files_contain_exactly_the_known_duplicate_names(): void
    {
        // Ez az őr bukik el a TODO 26-ban, amikor a duplikátumok eltűnnek - ez a
        // szándék. Új duplikátum megjelenését is elkapja.
        //
        // FIGYELEM: a forrásszkennert soha ne vessük össze DARABSZÁMRA a
        // route-contracts.json-nal. A setup.* route-ok a routes/web.php:78
        // `if (!Storage::exists('installed.txt'))` mögött állnak, tehát a
        // futásidejű pillanatképben nincsenek, a forrásban viszont ott vannak.
        // Duplikátum-szűrésre ettől függetlenül használható: a setup nevek egyediek.
        $duplicates = array_filter(
            $this->sourceRouteNameCounts(),
            fn (int $count): bool => $count > 1
        );

        $this->assertSame(
            [
                'password.confirm' => 2,
                'verification.verify' => 2,
            ],
            $duplicates
        );
    }

    public function test_the_route_files_use_no_group_level_name_prefixes(): void
    {
        // A fenti forrásszkenner lapos névteret feltételez: minden név egyetlen
        // ->name('...') hívásból áll elő. Csoportszintű prefix (Route::name() vagy
        // 'as' => a csoport tömbjében) ezt elrontaná, ezért kizárjuk.
        foreach (self::ROUTE_FILES as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringNotContainsString('Route::name(', $source, $file);
            $this->assertStringNotContainsString("'as' =>", $source, $file);
        }
    }

    /**
     * A hatályos, név szerint aggregált route-szerződés.
     *
     * @param  string|null  $onlyPrefix  ha megadott, csak az ezzel a prefixszel
     *                                   kezdődő nevek; ha null, akkor az
     *                                   alkalmazás saját route-jai (vendor nélkül).
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
                if (str_starts_with($name, 'debugbar.')
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
     * A fixture azon bejegyzései, amelyek neve az adott prefixszel kezdődik.
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
     * Az adott néven TÉNYLEGESEN regisztrált összes route.
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
     * @param  \Illuminate\Routing\Route[]  $routes
     */
    private function firstRouteWithMethod(array $routes, string $method): Route
    {
        foreach ($routes as $route) {
            if (in_array($method, $route->methods(), true)) {
                return $route;
            }
        }

        $this->fail('Nincs '.$method.' definíció a kapott route-ok között.');
    }

    private function sortedMiddleware(Route $route): array
    {
        $middleware = array_values(array_unique($route->gatherMiddleware()));
        sort($middleware);

        return $middleware;
    }

    /**
     * A route-fájlokban SZEREPLŐ `->name('...')` hívások száma névenként.
     *
     * Tokenizálót használ, nem regexet: a kikommentelt Fortify definíciók
     * (pl. a verification.notice a routes/fortify.php:80-85-ben) így természetes
     * módon kimaradnak, mert a komment külön token.
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
     * A következő token indexe, whitespace-t és kommentet átugorva.
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
