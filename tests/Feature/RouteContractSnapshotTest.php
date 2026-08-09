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
 * 1. A DUPLIKÁLT NEVEK. A `password.confirm` egyetlen sorként jelent meg, a
 *    `verification.verify` pedig úgy nézett ki, mintha egyetlen definíció lenne -
 *    holott a forrásban kettő volt, és az egyik árnyékban maradt. A v1-patch
 *    mindkettőt feloldotta (A4 és A7), és az itteni tesztek tették azt szándékos,
 *    olvasható diffé egy csendes viselkedésváltozás helyett. A duplikátum nem
 *    csak rendetlenség volt: egyetlen ismétlődő név is LogicExceptionnel
 *    megbuktatja a `route:cache`-t, tehát az `artisan optimize` ezen a
 *    kódbázison sosem futott le.
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

    public function test_the_web_php_verification_verify_closure_is_gone(): void
    {
        // MEGFORDÍTVA a v1-patch A4 javításával (TODO 26).
        //
        // Korábban a forrásban KÉT definíció volt, a routing táblában egy - a
        // különbség maga volt a halott kód. A routes/web.php-beli closure most
        // törölve, tehát a forrás és a futásidejű tábla végre egyetért.
        $this->assertSame(1, $this->sourceRouteNameCounts()['verification.verify']);
        $this->assertCount(1, $this->routesNamed('verification.verify'));

        foreach ($this->routesNamed('verification.verify') as $route) {
            $this->assertNotSame('Closure', $route->getActionName());
        }

        // A törlés nulla futásidejű változást jelentett: ami maradt, az
        // pontosan az, ami eddig is nyert.
        $this->assertStringNotContainsString(
            "name('verification.verify')",
            file_get_contents(base_path('routes/web.php'))
        );
    }

    public function test_the_two_confirm_password_definitions_carry_different_names(): void
    {
        // MEGFORDÍTVA a v1-patch A7 javításával (TODO 26). Korábban mindkét
        // definíció a `password.confirm` nevet viselte, és ez a teszt azt rögzítette,
        // hogy MINDKETTŐ életben van - ami igaz is volt, de egyben
        // cachelhetetlenné tette a route-táblát, lásd
        // test_the_route_table_survives_route_cache.
        //
        // A két definíció továbbra is él, változatlan URI-val és middleware-rel,
        // csak már külön névvel. Az unió-alapú pillanatkép eddig egy sorrá mosta
        // össze őket, és elrejtette, hogy a throttle CSAK a POST-on van.
        $get = $this->routesNamed('password.confirm');
        $post = $this->routesNamed('password.confirm.store');

        $this->assertCount(1, $get);
        $this->assertCount(1, $post);

        $this->assertSame(['auth', 'web'], $this->sortedMiddleware($get[0]));
        $this->assertSame(['auth', 'throttle:6,1', 'web'], $this->sortedMiddleware($post[0]));

        // Az URI változatlanul azonos, ezért a javítás egyetlen URL-t sem mozdít el.
        $this->assertSame('confirm-password', $get[0]->uri());
        $this->assertSame('confirm-password', $post[0]->uri());
        $this->assertContains('GET', $get[0]->methods());
        $this->assertContains('POST', $post[0]->methods());
    }

    public function test_url_generation_resolves_password_confirm_to_the_get_definition(): void
    {
        // MEGFORDÍTVA a v1-patch A7 javításával. Korábban a nameList-ből az UTOLSÓ
        // definíció nyert - vagyis a POST -, és a `password.confirm`
        // middleware-alias (app/Http/Kernel.php:68) egy POST route nevére
        // irányított át. Kizárólag azért működött, mert a két URI azonos volt.
        // A név most a Laravel konvenciója szerint az űrlapot mutató GET ágra
        // mutat, tehát a RequirePassword átirányítása végre a saját metódusára esik.
        $resolved = app('router')->getRoutes()->getByName('password.confirm');

        $this->assertNotNull($resolved);
        $this->assertContains('GET', $resolved->methods());
        $this->assertNotContains('POST', $resolved->methods());

        // A generált URL viszont betűre ugyanaz, mint a javítás előtt.
        $this->assertSame('/confirm-password', route('password.confirm', [], false));
        $this->assertSame('/confirm-password', route('password.confirm.store', [], false));
    }

    public function test_the_route_table_survives_route_cache(): void
    {
        // EZ A JAVÍTÁS VALÓDI TÉTJE. Az `artisan optimize` (és a `route:cache`) a
        // teljes táblát Symfony route-gyűjteménnyé alakítja, ahol a név EGYEDI
        // KULCS: a második azonos nevű route LogicExceptiont dob, és az egész
        // parancs elhasal. A duplikált `password.confirm` miatt tehát ezen a
        // kódbázison SOHA nem futott le a route-cache - a v1-en sem.
        //
        // Ugyanazt a kódutat járjuk be, mint a parancs, csak nem írunk fájlt:
        // AbstractRouteCollection::toSymfonyRouteCollection() az, ami dob.
        $symfony = app('router')->getRoutes()->toSymfonyRouteCollection();

        $this->assertNotEmpty($symfony->all());
        $this->assertNotNull($symfony->get('password.confirm'));
        $this->assertNotNull($symfony->get('password.confirm.store'));
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

    public function test_the_route_files_contain_no_duplicate_names(): void
    {
        // MEGFORDÍTVA a v1-patch A4 (`verification.verify` törlése) és A7
        // (`password.confirm` szétválasztása) javításával, TODO 26. Mindkét
        // definíciópár él, csak már külön néven - lásd
        // test_the_two_confirm_password_definitions_carry_different_names.
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

        // MEGFORDÍTVA a v1-patch A7 javításával: a `password.confirm` második
        // definíciója `password.confirm.store` nevet kapott, tehát a forrásban
        // már EGYETLEN duplikált név sincs. Az őr innentől bármely új duplikátum
        // megjelenését elkapja - és mivel egyetlen duplikátum is
        // cachelhetetlenné teszi a route-táblát, ez immár telepítési hiba, nem
        // csak rendezetlenség.
        $this->assertSame([], $duplicates);
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
