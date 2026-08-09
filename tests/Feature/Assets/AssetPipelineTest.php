<?php

namespace Tests\Feature\Assets;

use App\Models\User;
use Illuminate\Http\Request;
use Tests\Feature\FeatureTestCase;

/**
 * Az asset-pipeline karakterizációs készlete - a TODO 33.8 utáni alakban.
 *
 * MI VÁLTOZOTT
 *
 * A fájl eredetileg (TODO 21.1) az `eusonlito/laravel-packer` viselkedését
 * rögzítette: csomagolt fájlneveket, összefűzést, időbélyeges átnevezést és egy
 * `local` / nem-`local` kettősséget. A TODO 33.8-cal a csomag elment, a helyére
 * a `pwbs_asset()` helper lépett (app/Helpers/helpers.php), és ezzel a mért
 * felület is más lett: `?v={filemtime}` query az eredeti fájlon, tagenként egy
 * forrás, összefűzés nélkül, KÖRNYEZETTŐL FÜGGETLENÜL ugyanúgy.
 *
 * AZ UTOLSÓ TAGMONDAT A LÉNYEG
 *
 * A csomag `local` alatt átjáró volt, minden más környezetben csomagolt - és
 * minden mért hibája (a szétvert `data:` URI-k, a sémát bebetonozó abszolút
 * URL, a webgyökérbe írás) KIZÁRÓLAG a nem-`local` ágon jelentkezett. Ezért nem
 * vette észre őket senki fejlesztés közben, és ezért robbantak élesben. Az
 * itteni tesztek közül három szándékosan azt méri, hogy ez a kettősség
 * megszűnt - nem csak azt, hogy a tagek jól néznek ki.
 */
class AssetPipelineTest extends FeatureTestCase
{
    /** Ideiglenes fixture a public/ alatt; a tearDown takarítja. */
    private ?string $fixture = null;

    private ?User $user = null;

    protected function tearDown(): void
    {
        if ($this->fixture !== null && is_file($this->fixture)) {
            @unlink($this->fixture);
        }

        parent::tearDown();
    }

    // =========================================================================
    // A helper maga
    // =========================================================================

    public function test_the_helper_appends_the_files_modification_time(): void
    {
        $this->assertSame(
            asset('/css/style.css').'?v='.filemtime(public_path('css/style.css')),
            pwbs_asset('/css/style.css'),
            'A cache busting a csomag EGYETLEN ténylegesen szállított értéke volt; '
            .'a helper ugyanezt adja, egy filemtime() hívásból, lemezre írás nélkül.'
        );
    }

    public function test_the_helper_follows_a_changed_file(): void
    {
        $this->fixture = public_path('pwbs-asset-fixture.css');
        file_put_contents($this->fixture, 'a{}');

        touch($this->fixture, 1600000000);
        clearstatcache(true, $this->fixture);
        $this->assertStringEndsWith('?v=1600000000', pwbs_asset('/pwbs-asset-fixture.css'));

        // A clearstatcache() nem díszlet: nélküle a PHP stat-gyorsítótára a
        // MÁSODIK filemtime()-ot is az elsőből szolgálja ki, és a teszt zölden
        // hazudik. Egy kérés egy stat-ot csinál, tehát élesben nincs dolga.
        touch($this->fixture, 1700000000);
        clearstatcache(true, $this->fixture);
        $this->assertStringEndsWith('?v=1700000000', pwbs_asset('/pwbs-asset-fixture.css'));
    }

    public function test_the_helper_falls_back_to_a_plain_url_for_a_missing_file(): void
    {
        // Egy elgépelt útvonal ne öljön meg egy oldalt: token nélküli URL jön,
        // nem kivétel. A böngésző 404-et kap, ami látható és javítható.
        $this->assertSame(asset('/nincs-ilyen.css'), pwbs_asset('/nincs-ilyen.css'));
    }

    public function test_the_helper_treats_a_leading_slash_and_a_bare_path_alike(): void
    {
        // A régi hívási helyek vegyesen használták a két alakot ('css/style.css'
        // az összefűzött listában, '/dist/css/adminlte.min.css' egyfájlosként).
        // A helper mindkettőt ugyanarra az URL-re hozza, dupla perjel nélkül.
        $this->assertSame(pwbs_asset('/css/style.css'), pwbs_asset('css/style.css'));
        $this->assertStringNotContainsString('//css/style.css', pwbs_asset('/css/style.css'));
    }

    public function test_the_helper_follows_the_scheme_of_the_current_request(): void
    {
        // EZ AZ, AMI ÉLESBEN ELTÖRT. A Packer a generáló kérés sémáját sütötte
        // bele a csomagolt CSS abszolút url()-jeibe, a fájlt pedig korlátlanul
        // újrahasznosította - egy http alatt készült fájl https-en mixed
        // contentet okozott, és soha nem gyógyult meg magától, mert a fájlnév a
        // FORRÁS filemtime-jából jött, nem a tartalomból. A helper minden
        // kérésnél újraszámol, tehát ez a hibaosztály nem létezik többé.
        foreach (['https', 'http'] as $scheme) {
            $this->app['url']->setRequest(Request::create($scheme.'://kozter.test/home', 'GET'));

            $this->assertStringStartsWith(
                $scheme.'://kozter.test/',
                pwbs_asset('/css/style.css'),
                'A séma az aktuális kérésé, nem egy korábbié.'
            );
        }
    }

    // =========================================================================
    // Amit a böngésző lát
    // =========================================================================

    public function test_the_app_layout_emits_a_versioned_tag_for_every_asset(): void
    {
        $html = $this->renderHome();

        foreach ($this->appLayoutAssets() as $path) {
            $this->assertMatchesRegularExpression(
                '#'.preg_quote($path, '#').'\?v=\d+#',
                $html,
                $path.' vagy nincs kiírva, vagy nincs rajta cache-busting token.'
            );
        }

        // A három többfájlos hívás egyenként külön tagre bomlott - az összefűzés
        // szándékosan esett ki (TODO 21 döntése): 16-ból 3 hívási helyet
        // érintett, HTTP/2 fölött semmit nem hozott, cserébe ő volt az, ami a
        // webgyökérbe írt.
        $this->assertStringContainsString('/js/custom.js?v=', $html);
        $this->assertStringContainsString('/js/modal.js?v=', $html);

        // És nyoma sincs a régi, csomagolt fájlneveknek.
        $this->assertDoesNotMatchRegularExpression('#/cache/(js|css)/\d+-#', $html);
        $this->assertStringNotContainsString('cache_fontawesome', $html);
        $this->assertStringNotContainsString('cache_adminlte', $html);
    }

    public function test_the_layout_emits_the_same_assets_in_every_environment(): void
    {
        $inTesting = $this->assetUrls($this->renderHome());

        $this->app['env'] = 'production';
        $inProduction = $this->assetUrls($this->renderHome());

        // A Packer alatt ez a két lista KÜLÖNBÖZÖTT, és a különbségben lakott
        // mind a négy mért hiba. Ha valaha újra eltér, valaki visszahozott egy
        // környezetfüggő asset-ágat.
        $this->assertSame($inTesting, $inProduction);
        $this->assertNotSame([], $inTesting);
    }

    public function test_rendering_a_page_writes_nothing_into_the_web_root(): void
    {
        $before = $this->generatedArtifacts();

        $this->renderHome();

        // A Packer process()-e kérés közben mkdir + tempnam + fopen + rename +
        // chmod-ot futtatott a public/ alatt, `local` kivételével MINDEN
        // környezetben - a tesztfutás is. Így írta felül egy `composer test` a
        // böngészőnek kiszolgált fájlokat.
        $this->assertSame(
            $before,
            $this->generatedArtifacts(),
            'Egy oldal renderelése nem hozhat létre fájlt a webgyökérben.'
        );

        // Konkrétan erre az útvonalra a setup layout írt, és ezzel foglalta el a
        // `storage:link` helyét. Magára a public/storage-ra nem állítunk semmit:
        // egy rendesen linkelt telepítésen az LÉTEZIK, csak épp szimlinkként.
        $this->assertDirectoryDoesNotExist(public_path('storage/cache'));
    }

    public function test_the_emitted_tags_have_the_shapes_the_layouts_depend_on(): void
    {
        $html = $this->renderHome();

        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]+\?v=\d+">#', $html);
        $this->assertMatchesRegularExpression('#<script src="[^"]+\?v=\d+"></script>#', $html);
    }

    // =========================================================================
    // Amit a böngésző a tageken KERESZTÜL kap
    // =========================================================================

    public function test_no_stylesheet_the_page_links_contains_an_absolute_url(): void
    {
        foreach ($this->linkedStylesheets() as $path => $contents) {
            $this->assertDoesNotMatchRegularExpression(
                '#url\(\s*["\']?https?://#i',
                $contents,
                $path.' abszolút hivatkozást tartalmaz. Pontosan ez okozta a mixed contentet: '
                .'a séma bele volt sütve a kiszolgált fájlba.'
            );
        }
    }

    public function test_no_stylesheet_the_page_links_has_a_mangled_data_uri(): void
    {
        $seen = 0;

        foreach ($this->linkedStylesheets() as $path => $contents) {
            preg_match_all('#url\(\s*["\']?([^)"\']*data:)#i', $contents, $matches);

            foreach ($matches[1] as $match) {
                $seen++;

                $this->assertSame(
                    'data:',
                    $match,
                    $path.' egyik data: URI-ja elé előtag került. A Packer 181-et tört el így '
                    .'(177 az adminlte.min.css-ben, 4 a toastr.min.css-ben) - annyi beágyazott '
                    .'ikon, amennyi az alkalmazás űrlapjain és gombjain végig megjelenik.'
                );
            }
        }

        $this->assertGreaterThan(
            170,
            $seen,
            'Ha ez a szám leesik, a teszt már nem azt méri, amiért íródott.'
        );
    }

    // =========================================================================
    // Segédek
    // =========================================================================

    /** @return list<string> az app layout által kiírt asset-útvonalak, sorrendben */
    private function appLayoutAssets(): array
    {
        return [
            '/plugins/fontawesome-free/css/all.min.css',
            '/dist/css/adminlte.min.css',
            '/css/style.css',
            '/plugins/toastr/toastr.min.css',
            '/css/public_style.css',
            '/plugins/jquery/jquery.min.js',
            '/plugins/bootstrap/js/bootstrap.bundle.min.js',
            '/dist/js/adminlte.min.js',
            '/plugins/toastr/toastr.min.js',
            '/plugins/sweetalert2/sweetalert2.all.min.js',
            '/js/custom.js',
            '/js/modal.js',
        ];
    }

    private function renderHome(): string
    {
        return $this->actingAs($this->pageUser())->get('/home')->assertStatus(200)->getContent();
    }

    /** @return list<string> a HTML-ben szereplő asset-URL-ek, sorrendben */
    private function assetUrls(string $html): array
    {
        preg_match_all('#(?:href|src)="([^"]+\?v=\d+)"#', $html, $matches);

        return $matches[1];
    }

    /**
     * A linkelt stylesheetek tartalma, útvonal => tartalom.
     *
     * Szándékosan a RENDERELT HTML-ből indul, nem egy kézzel írt listából: ha
     * valaha újra generált fájl kerül a tagbe, ez a két teszt azt vizsgálja meg,
     * nem az érintetlen forrást.
     *
     * @return array<string, string>
     */
    private function linkedStylesheets(): array
    {
        preg_match_all('#<link rel="stylesheet" href="([^"]+)"#', $this->renderHome(), $matches);

        $this->assertNotEmpty($matches[1], 'A layout nem linkelt egyetlen stylesheetet sem.');

        $files = [];

        foreach ($matches[1] as $url) {
            $path = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
            $file = public_path($path);

            $this->assertFileExists($file, $url.' a public/ alatt nem létezik.');

            $files[$path] = file_get_contents($file);
        }

        return $files;
    }

    /** @return list<string> a Packer-korszak generált artefaktjai, ha valamitől visszatérnének */
    private function generatedArtifacts(): array
    {
        $found = [];

        foreach ([
            public_path('cache/css/*'),
            public_path('cache/js/*'),
            public_path('dist/css/*-cache_*'),
            public_path('dist/js/*-cache_*'),
            public_path('plugins/*/*-cache_*'),
            public_path('plugins/*/*/*-cache_*'),
            public_path('storage/cache/*'),
        ] as $pattern) {
            $found = array_merge($found, glob($pattern) ?: []);
        }

        sort($found);

        return $found;
    }

    /**
     * Egy sima, aktivált felhasználó, aki a /home-ot 200-zal megkapja.
     *
     * Memoizálva: két teszt is kétszer renderel (a környezet-összehasonlítás és
     * a stylesheet-olvasó), és egy fix e-mail-címmel a második létrehozás az
     * egyediségi megszorításba futna.
     */
    private function pageUser(): User
    {
        return $this->user ??= $this->createUser([
            'email' => 'asset-pipeline@example.test',
            'email_verified_at' => now(),
        ]);
    }
}
