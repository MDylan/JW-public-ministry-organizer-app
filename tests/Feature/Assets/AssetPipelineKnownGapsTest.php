<?php

namespace Tests\Feature\Assets;

use Eusonlito\LaravelPacker\Packer;
use Eusonlito\LaravelPacker\PackerServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;
use ReflectionClass;
use Tests\Feature\FeatureTestCase;

/**
 * A TODO 21.1 által mért kilenc hiányosság - a TODO 33.8 lezárása közben.
 *
 * A fájl eredetileg a hibás viselkedést rögzítette, hogy a javítás pillanatában
 * bukjon, és az a bukás legyen a reviewálható diff - ugyanaz a fegyelem, mint a
 * TODO 14 duplikált-route tripwire-jeinél és a TODO 20.1 WeatherKnownGapsTest
 * fájljánál. A név szándékosan változatlan: a git történetben így követhető,
 * melyik állítás melyik hiányosság helyére lépett.
 *
 * A TODO 33.8 KÉT COMMITBAN SZÁLLÍT, ÉS EZ ITT LÁTSZIK
 *
 * Az első commit az alkalmazásszintű csere: a 16 `Packer::` hívási hely helyére
 * `pwbs_asset()` lép, és a provider meg az alias kikerül a config/app.php-ból.
 * Az ehhez tartozó négy hiányosság (3., 4., 7., 9.) itt már meg van fordítva.
 *
 * A második commit a csomagot magát viszi el. Az ahhoz kötött öt eset (1., 2.,
 * 5., 6., 8.) SZÁNDÉKOSAN érintetlen: mindegyik a telepített csomagot méri
 * (homokozóban példányosított Packer, composer.lock, .gitignore, reflection),
 * és mind igaz marad addig, amíg a `vendor/eusonlito` a helyén van. Egyiket sem
 * fordítja meg az első commit - ezt megmérni becsületesebb, mint feltételezni.
 */
class AssetPipelineKnownGapsTest extends FeatureTestCase
{
    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null && is_dir($this->sandbox)) {
            $this->removeTree($this->sandbox);
        }

        parent::tearDown();
    }

    // =========================================================================
    // TODO 33.8 - a CSS-átírás élő produkciós kárt okoz
    // =========================================================================

    public function test_gap_the_css_packer_corrupts_data_uris(): void
    {
        $packer = $this->sandboxPacker();
        $this->writeAsset('plugins/toastr/toastr.min.css', '.toast{background-image:url(data:image/png;base64,AAAA)}');

        $packer->css('/plugins/toastr/toastr.min.css', '/cache/css/all_style.css');

        // Providers/CSS.php:25 MINDEN `url(` elé beszúrja az asset-bázist és a
        // forrásfájl könyvtárát. Relatív útnál ez helyes. Egy data: URI-nál viszont
        // értelmetlen abszolút előtagot kap, és a kép soha nem tölt be.
        $this->assertStringContainsString(
            'url(http://packer.test/plugins/toastr/data:image/png;base64,AAAA)',
            file_get_contents($packer->getFilePath()),
            'Ez nem elméleti: a ma kiszolgált public/cache/css/*-all_style.css mind a NÉGY '
            .'toastr-ikont pontosan így törte el, mert a toastr.min.css négy data: URI-t '
            .'tartalmaz. Vagyis a toastr értesítései minden nem-local környezetben ikon '
            .'nélkül jelennek meg - local alatt viszont jól, mert ott a Packer nem csomagol. '
            .'A TODO 33.8 összefűzés nélküli helpere ezt megszünteti.'
        );

        // És a projekt semmit nem nyer az átírásból: a két saját CSS-ében nulla `url(` van.
        foreach (['public/css/style.css', 'public/css/public_style.css'] as $own) {
            $this->assertStringNotContainsString(
                'url(',
                file_get_contents(base_path($own)),
                $own.' - az átírás egyetlen relatív utat sem javít ki, csak az idegen fájlt rontja el.'
            );
        }
    }

    // =========================================================================
    // TODO 33.8 - a csomag nem azt csinálja, aminek a neve mondja
    // =========================================================================

    public function test_gap_nothing_is_ever_minified(): void
    {
        $this->assertFalse(config('packer.css_minify'), 'config/packer.php:88');
        $this->assertFalse(config('packer.js_minify'), 'config/packer.php:99');

        $packer = $this->sandboxPacker();
        $this->writeAsset('js/a.js', "var  a   =   1;\n\n\n");
        $this->writeAsset('js/b.js', "var  b   =   2;\n\n\n");

        $packer->js(['/js/a.js', '/js/b.js'], '/cache/js/all.js');

        $sources = strlen(file_get_contents($this->sandbox.'/js/a.js'))
            + strlen(file_get_contents($this->sandbox.'/js/b.js'));

        // A kimenet a bemenetek összege plusz fájlonként egy pontosvessző. Se
        // whitespace-eltávolítás, se semmi: a "packer/minify" csomag ebben a
        // projektben egy összefűző és egy időbélyeges átnevező, semmi más.
        $this->assertSame(
            $sources + 2,
            strlen(file_get_contents($packer->getFilePath())),
            'Ha valaha bekapcsoljuk a minifikálást, ez a teszt megbukik - és az jó hír.'
        );
    }

    // =========================================================================
    // TODO 33.8 - a webgyökérbe írás és a storage:link ütközése
    // =========================================================================

    public function test_the_two_layouts_now_reference_the_same_file_by_the_same_url(): void
    {
        $app = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
        $setup = file_get_contents(base_path('resources/views/layouts/setup.blade.php'));

        // Korábban ugyanaz a jQuery két KÜLÖNBÖZŐ kimeneti helyre csomagolódott
        // (`/cache/js/jquery.js` és `/storage/cache/js/jquery.js`), tehát kétszer
        // volt a lemezen, két URL alatt, két cache-bejegyzésben. Kimeneti hely
        // már nincs: mindkét layout ugyanazt a forrásfájlt hivatkozza.
        foreach ([$app, $setup] as $layout) {
            $this->assertStringContainsString("pwbs_asset('/plugins/jquery/jquery.min.js')", $layout);
        }

        foreach (['/cache/js/', '/cache/css/', '/storage/cache/'] as $target) {
            $this->assertStringNotContainsString($target, $app);
            $this->assertStringNotContainsString($target, $setup);
        }
    }

    public function test_nothing_occupies_the_storage_symlink_path_any_more(): void
    {
        // A Packer::checkDir() valódi könyvtárat hozott létre ott, ahová a
        // `php artisan storage:link` a szimlinket tenné - a setup layout
        // `/storage/cache/...` céljai miatt, a telepítővarázsló ELSŐ
        // renderelésekor. Onnantól a parancs "The [public/storage] link already
        // exists" hibával kihagyta a linket (a --force sem segít, mert az csak
        // is_link() esetén töröl), és a publikus disk minden URL-je 404-et adott.
        $this->assertStringNotContainsString(
            '/storage/',
            file_get_contents(base_path('resources/views/layouts/setup.blade.php')),
            'A setup layout már semmit nem irányít a szimlink helyére.'
        );

        // A teljes suite renderel setup-oldalakat (tests/Feature/Setup/), tehát
        // ha bármi újra odaírna, ez a könyvtár megjelenne a futás alatt.
        $this->assertDirectoryDoesNotExist(
            public_path('storage/cache'),
            'Valami újra a webgyökér storage-útvonalára ír.'
        );
    }

    public function test_gap_nine_gitignore_lines_exist_only_to_contain_packer_output(): void
    {
        // Soronként, trimmelve: a fájl CRLF-fel van mentve, és a sorvégek nem
        // tárgya ennek a tesztnek.
        $gitignore = array_map('trim', file(base_path('.gitignore')));

        $lines = [
            'public/dist/css/*-cache_adminlte.min.css',
            'public/dist/js/*-cache_adminlte.js',
            'public/plugins/bootstrap/js/*-cache_bootstrap.js',
            'public/plugins/fontawesome-free/css/*-cache_fontawesome.css',
            'public/plugins/sweetalert2/*-cache_sweetalert2.js',
            'public/plugins/toastr/*-cache_toastr.js',
            'public/plugins/summernote/*-cache_summernote-bs4.min.js',
            '/public/cache',
        ];

        foreach ($lines as $line) {
            $this->assertContains(
                $line,
                $gitignore,
                'Ez a sor kizárólag azért van, mert a Packer kérés közben ír a webgyökérbe.'
            );
        }

        // A kilencedik sor elgépelt: ilyen könyvtár nincs és sosem volt. A valódi
        // cél a public/storage/cache/, amit a /public/storage sor takar véletlenül.
        $this->assertContains('/public/storages/cache/*', $gitignore);
        $this->assertDirectoryDoesNotExist(base_path('public/storages'));
    }

    // =========================================================================
    // TODO 33.8 - halott függőség és halott kód
    // =========================================================================

    public function test_gap_imagecow_is_installed_for_an_api_the_project_never_calls(): void
    {
        $lock = json_decode(file_get_contents(base_path('composer.lock')), true);

        $imagecow = collect($lock['packages'])->firstWhere('name', 'imagecow/imagecow');
        $this->assertNotNull($imagecow, 'Telepítve van.');

        // Egyetlen csomag húzza be, és az is csak a Packer::img() miatt.
        $requirers = collect($lock['packages'])
            ->filter(fn ($package) => isset($package['require']['imagecow/imagecow']))
            ->pluck('name')
            ->values()
            ->all();

        $this->assertSame(['eusonlito/laravel-packer'], $requirers);

        // A projekt viszont sosem hívja - se img(), se jsDir(), se cssDir().
        foreach (['img', 'jsDir', 'cssDir'] as $method) {
            $this->assertSame(
                [],
                $this->grepProjectSources('Packer::'.$method.'('),
                'A Packer::'.$method.'() a fogyasztott felületen kívül van. A projekt a 16 '
                .'hívási helyén KIZÁRÓLAG a css() és a js() metódust használja.'
            );
        }
    }

    public function test_the_provider_and_the_facade_alias_are_gone(): void
    {
        $config = file_get_contents(base_path('config/app.php'));

        // A TODO 25 második pontja ezt a két sztring-literált `::class`-ra
        // cserélte volna. A TODO 25 szándékosan nem nyúlt hozzájuk, mert a 33.8
        // úgyis törli őket - így senki nem szerkesztette kétszer ugyanazt a két
        // sort, és nem kellett reviewálni egy változtatást útban a törlés felé.
        $this->assertStringNotContainsString('Eusonlito', $config);

        $this->assertNotContains('Eusonlito\LaravelPacker\PackerServiceProvider', config('app.providers'));
        $this->assertArrayNotHasKey('Packer', config('app.aliases'));
    }

    public function test_gap_the_providers_defer_flag_has_been_dead_since_laravel_5_8(): void
    {
        $provider = new ReflectionClass(PackerServiceProvider::class);

        $defer = $provider->getDefaultProperties()['defer'] ?? null;
        $this->assertTrue($defer, 'A csomag még a régi `protected $defer = true;` alakot használja.');

        $this->assertTrue(
            $provider->hasMethod('provides'),
            'És a hozzá tartozó provides() metódust is szállítja.'
        );

        // A Laravel 5.8 óta a halasztás a DeferrableProvider interfészen múlik. A
        // provider ezt nem implementálja, tehát a $defer és a provides() együtt
        // hatástalan: a Packer MINDEN kérésnél eagerly regisztrálódik.
        $this->assertFalse(
            $provider->implementsInterface(DeferrableProvider::class),
            'Ha ez valaha igazra fordul, a csomag frissült - és a TODO 21 mérését újra kell olvasni.'
        );
    }

    // =========================================================================
    // TODO 52 / 53 - a Phase 7 premisszájának korrekciója
    // =========================================================================

    public function test_gap_the_mix_pipeline_is_dead_and_the_packer_is_the_only_asset_pipeline(): void
    {
        // A roadmap Phase 7-e szerint a Vite-migráció azért kötelező, mert a Mix 6
        // nem épül a Node 24-en, és a TODO 52 szerint `mix()` helpereket kell
        // `@vite`-ra cserélni a két layoutban. Egyik állítás sem áll.
        $this->assertSame(
            [],
            $this->grepProjectSources('mix('),
            'NULLA `mix()` hívás van a projektben - se a layoutokban, se máshol.'
        );

        $this->assertSame(0, filesize(base_path('resources/css/app.css')), 'A CSS-belépési pont üres.');
        $this->assertSame(
            ["require('./bootstrap');"],
            array_map('trim', file(base_path('resources/js/app.js'))),
            'A JS-belépési pont az érintetlen Laravel-alapértelmezés.'
        );

        // És a Mix kimenetei nem is léteznek, tehát a build sosem futott le itt.
        $this->assertFileDoesNotExist(base_path('public/js/app.js'));
        $this->assertFileDoesNotExist(base_path('public/css/app.css'));

        // A TODO 21 sorrendi korrekciója szerint a Vite NEM tehette feleslegessé
        // a Packert, mert az volt az alkalmazás egyetlen működő asset-pipeline-ja,
        // és a TODO 52 a 16 hívási helye közül egyet sem érintett. A 33.8 után a
        // sorrend megfordult: a TODO 52-nek már `pwbs_asset()` tageket kell
        // `@vite`-ra cserélnie, nem `Packer::` hívásokat.
        $this->assertSame([], $this->grepProjectSources('Packer::'));

        $callSites = array_values(array_filter(
            $this->grepProjectSources('pwbs_asset('),
            fn (string $hit) => str_starts_with($hit, 'resources')
        ));

        $this->assertCount(
            21,
            $callSites,
            'app.blade.php 12 + setup.blade.php 8 + poster-edit-modal.blade.php 1. A 16 hívásból '
            .'21 tag lett, mert a három többfájlos hívás forrásonként külön tagre bomlott.'
        );

        // A maradék találat maga a definíció - szűrés nélkül 22 jönne, mert a
        // helper forrása is tartalmazza a keresett szöveget. Sorszámra nem
        // állítunk semmit: az a helpers.php bármely fölötte lévő módosításától
        // elmozdulna, és nem az a kérdés, hányadik sorban van.
        foreach (array_diff($this->grepProjectSources('pwbs_asset('), $callSites) as $hit) {
            $this->assertStringStartsWith('app'.DIRECTORY_SEPARATOR.'Helpers', $hit);
        }
    }

    // =========================================================================
    // Segédek
    // =========================================================================

    /** @return list<string> a találatok `fájl:sor` alakban */
    private function grepProjectSources(string $needle): array
    {
        $hits = [];

        foreach ([base_path('app'), base_path('resources'), base_path('config'), base_path('routes')] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|blade\.php)$/', $file->getFilename())) {
                    continue;
                }

                foreach (file($file->getPathname()) as $number => $line) {
                    if (strpos($line, $needle) !== false) {
                        $hits[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.($number + 1);
                    }
                }
            }
        }

        sort($hits);

        return $hits;
    }

    private function sandboxPacker(): Packer
    {
        $this->sandbox = sys_get_temp_dir().'/kozter-packer-gaps-'.uniqid();
        mkdir($this->sandbox, 0777, true);

        return new Packer([
            'environment' => 'production',
            'ignore_environments' => ['local'],
            'public_path' => $this->sandbox,
            'asset' => 'http://packer.test',
            'cache_folder' => '/cache/',
            'check_timestamps' => true,
            'css_minify' => false,
            'js_minify' => false,
            'images_fake' => false,
            'quality' => 85,
        ]);
    }

    private function writeAsset(string $relative, string $contents): void
    {
        $path = $this->sandbox.'/'.$relative;

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    private function removeTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.'/'.$entry;

            is_dir($path) ? $this->removeTree($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
