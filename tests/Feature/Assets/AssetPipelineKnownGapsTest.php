<?php

namespace Tests\Feature\Assets;

use Tests\Feature\FeatureTestCase;

/**
 * A TODO 21.1 által mért kilenc hiányosság - MIND LEZÁRVA a TODO 33.8-cal.
 *
 * A fájl eredetileg a hibás viselkedést rögzítette, hogy a javítás pillanatában
 * bukjon, és az a bukás legyen a reviewálható diff - ugyanaz a fegyelem, mint a
 * TODO 14 duplikált-route tripwire-jeinél és a TODO 20.1 WeatherKnownGapsTest
 * fájljánál. Ez a bukás bekövetkezett: mind a kilenc eset megfordult, és a fájl
 * most azt őrzi, hogy a rés ne nyíljon ki újra.
 *
 * A név szándékosan változatlan: a git történetben így követhető, melyik állítás
 * melyik hiányosság helyére lépett.
 *
 * A kilenc eset két commitban fordult meg, mert a TODO 33.8 két commitban
 * szállított: az első az alkalmazásszintű cserét vitte (hívási helyek, provider,
 * alias), a második magát a csomagot. Az első commit után az itteni öt csomag-
 * szintű eset szándékosan MÉG IGAZ VOLT - a telepített csomagot mérték, és a
 * `vendor/eusonlito` a helyén volt. Megmérni becsületesebb volt, mint feltenni.
 */
class AssetPipelineKnownGapsTest extends FeatureTestCase
{
    // =========================================================================
    // 1. A CSS-átírás élő produkciós kárt okozott
    // =========================================================================

    public function test_the_embedded_icons_reach_the_browser_untouched(): void
    {
        // A Providers/CSS.php:25 MINDEN `url(` elé beszúrta az asset-bázist és a
        // forrásfájl könyvtárát, feltétel nélkül. Relatív útnál ez helyes volt;
        // egy data: URI-nál értelmetlen abszolút előtagot adott, és a kép soha
        // nem töltött be. A TODO 21 négy ilyet rögzített (a toastr ikonjait);
        // a valódi szám 181, mert az adminlte.min.css-t senki nem nézte meg.
        $counts = [
            'dist/css/adminlte.min.css' => 177,
            'plugins/toastr/toastr.min.css' => 4,
        ];

        foreach ($counts as $file => $expected) {
            $contents = file_get_contents(public_path($file));

            $this->assertSame(
                $expected,
                preg_match_all('#url\(\s*["\']?data:#i', $contents),
                $file.' data: URI-jainak száma elmozdult - a teszt már nem azt méri, amiért íródott.'
            );

            $this->assertSame(
                0,
                preg_match_all('#url\(\s*["\']?https?://#i', $contents),
                $file.' abszolút hivatkozást tartalmaz. Semmi nem írhatja át a kiszolgált CSS-t.'
            );
        }

        // És a projekt semmit nem nyert az átírásból: a két saját CSS-ében
        // nulla `url(` van, tehát nem volt mit kijavítani rajtuk.
        foreach (['css/style.css', 'css/public_style.css'] as $own) {
            $this->assertStringNotContainsString('url(', file_get_contents(public_path($own)));
        }
    }

    // =========================================================================
    // 2. A csomag nem azt csinálta, aminek a neve mondta
    // =========================================================================

    public function test_nothing_is_generated_so_there_is_nothing_to_minify(): void
    {
        // A `css_minify` és a `js_minify` egyaránt `false` volt, tehát a
        // "packer/minify" csomag ebben a projektben egy összefűző és egy
        // időbélyeges átnevező volt, semmi más. A konfigurációja már nincs meg.
        $this->assertNull(config('packer'), 'A config/packer.php elment.');
        $this->assertFileDoesNotExist(base_path('config/packer.php'));

        // Az egyetlen dolog, amit ténylegesen szállított, a cache busting volt.
        // Azt most egy filemtime() hívás adja, generált fájl nélkül.
        $this->assertStringContainsString('?v=', pwbs_asset('/css/style.css'));
    }

    // =========================================================================
    // 3-4. A webgyökérbe írás és a storage:link ütközése
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

    // =========================================================================
    // 5. A kilenc .gitignore sor, ami csak a kimenetet fékezte
    // =========================================================================

    public function test_the_nine_gitignore_lines_are_gone(): void
    {
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
            // A kilencedik elgépelt volt: ilyen könyvtár nincs és sosem volt.
            '/public/storages/cache/*',
        ];

        foreach ($lines as $line) {
            $this->assertNotContains(
                $line,
                $gitignore,
                'Ez a sor kizárólag azért létezett, mert a Packer kérés közben írt a webgyökérbe.'
            );
        }

        // A tizedik marad: az a szimlinké, nem a csomagé.
        $this->assertContains('/public/storage', $gitignore);
    }

    // =========================================================================
    // 6-7. A halott függőség és a halott provider
    // =========================================================================

    public function test_neither_the_packer_nor_imagecow_is_installed_any_more(): void
    {
        $lock = json_decode(file_get_contents(base_path('composer.lock')), true);
        $json = json_decode(file_get_contents(base_path('composer.json')), true);

        foreach (['eusonlito/laravel-packer', 'imagecow/imagecow'] as $package) {
            $this->assertNull(
                collect($lock['packages'])->firstWhere('name', $package),
                $package.' még benne van a lockban.'
            );

            $this->assertArrayNotHasKey($package, $json['require']);
        }

        // Az imagecow-t EGYETLEN csomag húzta be, és az is csak a soha nem hívott
        // Packer::img() miatt - vagyis egy függőség egy holt API kedvéért.
        $this->assertSame(
            [],
            collect($lock['packages'])
                ->filter(fn ($package) => isset($package['require']['imagecow/imagecow']))
                ->pluck('name')
                ->values()
                ->all()
        );

        $this->assertDirectoryDoesNotExist(base_path('vendor/eusonlito'));
        $this->assertDirectoryDoesNotExist(base_path('vendor/imagecow'));
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

        // A provider a Laravel 5.8 óta halott `protected $defer = true;` alakot
        // használta `provides()`-szal, DeferrableProvider nélkül - tehát a
        // halasztása hat major verzión át hatástalan volt, és a csomag minden
        // kérésnél eagerly regisztrálódott. Az osztály már nincs meg.
        $this->assertFalse(class_exists('Eusonlito\LaravelPacker\PackerServiceProvider'));
        $this->assertFalse(class_exists('Eusonlito\LaravelPacker\Packer'));
    }

    // =========================================================================
    // 8-9. TODO 52 / 53 - a Phase 7 premisszájának korrekciója
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
}
