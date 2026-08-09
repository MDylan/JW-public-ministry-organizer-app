<?php

namespace Tests\Feature\Assets;

use App\Models\User;
use Eusonlito\LaravelPacker\Packer;
use Illuminate\Support\Facades\Facade;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 21.1: az asset-pipeline karakterizációs készlete.
 *
 * MIÉRT LÉTEZIK
 *
 * Az alkalmazás teljes asset-kiszolgálása az `eusonlito/laravel-packer` 16 hívási
 * helyén nyugszik (layouts/app.blade.php 9, layouts/setup.blade.php 6, a
 * poster-edit-modal 1), és a TODO 21 előtt EGYETLEN teszt sem gyakorolta. Így a
 * "maradjon", a "frissüljön" és a "tűnjön el" kockázatban megkülönböztethetetlen
 * volt. Ez a fájl azt rögzíti, amit a csomag MA kibocsát; a hibáit a
 * AssetPipelineKnownGapsTest rögzíti külön.
 *
 * KÉTFÉLE TESZT VAN ITT, ÉS EZ SZÁNDÉKOS
 *
 * 1. A layoutot renderelő tesztek a VALÓDI public/ könyvtárat használják, mert
 *    csak úgy bizonyítható, mit lát a böngésző. Ez nem új károsítás: a szuite ma
 *    is ír a public/ alá (a SetupFlowTest GET-jei átmennek a setup layouton), és
 *    a kimenet minden darabja gitignore-olt.
 *
 * 2. A mechanizmust vizsgáló tesztek SAJÁT ideiglenes public_path-ot kapnak, és
 *    utána takarítanak. Ezek közvetlenül példányosítják a Packert - pontosan azt,
 *    amit a PackerServiceProvider is tesz (`new Packer($this->config())`), csak
 *    konténeres bűvészkedés nélkül.
 *
 * KÉT MÉRT TÉNY, AMI ITT MINDEN ÁLLÍTÁST ELDÖNT
 *
 * - A JS-provider MINDEN becsomagolt fájl elé egy pontosvesszőt ír
 *   (Providers/JS.php:16), tehát a kimenet SOHA nem bájtra azonos a forrással,
 *   még egyfájlos hívásnál sem.
 * - A CSS-provider minden `url(` előtagot abszolútra ír át (Providers/CSS.php:25).
 *   Relatív utaknál ez helyes; minden más alaknál károsítás - lásd a gap-készletet.
 */
class AssetPipelineTest extends FeatureTestCase
{
    /** Ideiglenes public_path a mechanizmus-tesztekhez. */
    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null && is_dir($this->sandbox)) {
            $this->removeTree($this->sandbox);
        }

        parent::tearDown();
    }

    // =========================================================================
    // A konténeres bekötés
    // =========================================================================

    public function test_the_container_binds_packer_with_the_projects_own_public_path(): void
    {
        $packer = app('packer');

        $this->assertInstanceOf(
            Packer::class,
            $packer,
            'A `packer` singletont a PackerServiceProvider köti be, amit a config/app.php:185 '
            .'string literálként regisztrál. A Packer facade (config/app.php:238) ezt oldja fel.'
        );

        // A config/packer.php `public_path` és `asset` kulcsa NULL; a provider
        // config() metódusa tölti ki őket public_path()-ból és asset('')-ból.
        // Vagyis a csomag a valódi webgyökérbe ír, nem valami sandboxba.
        $this->assertSame(
            realpath(public_path()),
            realpath($packer->path('public')),
            'A provider a public_path() alá irányítja a csomagolt fájlokat.'
        );
    }

    // =========================================================================
    // Amit a böngésző lát
    // =========================================================================

    public function test_the_app_layout_emits_packed_urls_outside_local(): void
    {
        // A tesztkörnyezet APP_ENV-je `testing`, a config/packer.php
        // ignore_environments-e pedig CSAK a `local`-t tartalmazza - tehát a
        // Packer itt ugyanúgy csomagol, mint élesben.
        $this->assertNotContains(
            app()->environment(),
            config('packer.ignore_environments'),
            'Ha ez megbukik, az egész fájl premisszája dőlt meg.'
        );

        $html = $this->actingAs($this->pageUser())->get('/home')->assertStatus(200)->getContent();

        $this->assertMatchesRegularExpression(
            '#/cache/js/\d+-all\.js#',
            $html,
            'Az app.blade.php:77 két projekt-JS-t fűz össze `/cache/js/all.js` név alá; a '
            .'Packer a legfrissebb forrás filemtime-ját teszi a név elé cache-bustingként.'
        );

        $this->assertMatchesRegularExpression(
            '#/cache/css/\d+-all_style\.css#',
            $html,
            'Ugyanez az app.blade.php:10 három CSS-ére.'
        );

        $this->assertStringNotContainsString(
            '/js/custom.js"',
            $html,
            'Csomagolt módban a forrásfájlok NEM jelennek meg külön tagként.'
        );
    }

    public function test_the_same_layout_emits_the_individual_sources_in_local(): void
    {
        $this->repointPacker(['environment' => 'local']);

        $html = $this->actingAs($this->pageUser())->get('/home')->assertStatus(200)->getContent();

        // Packer::isLocal() igazra fordul, a process() azonnal visszatér, és a
        // render() a nyers fájllistát adja vissza - vagyis fejlesztés közben a
        // csomag tiszta átjáró, és semmit nem ír a lemezre.
        $this->assertStringContainsString('/js/custom.js', $html);
        $this->assertStringContainsString('/js/modal.js', $html);

        $this->assertDoesNotMatchRegularExpression(
            '#/cache/js/\d+-all\.js#',
            $html,
            'Local alatt nincs csomagolás, tehát időbélyeges név sem keletkezik.'
        );
    }

    public function test_the_emitted_tags_have_the_shapes_the_layouts_depend_on(): void
    {
        $packer = $this->sandboxPacker();
        $this->writeAsset('src/one.css', 'a{}');
        $this->writeAsset('src/one.js', 'var a;');

        $css = (string) $packer->css('/src/one.css', '/out/one.css');
        $js = (string) $packer->js('/src/one.js', '/out/one.js');

        // A layoutok `{!! !!}`-lal írják ki, tehát a nyers HTML-alak szerződés.
        $this->assertMatchesRegularExpression('#^<link href="[^"]+" rel="stylesheet" />#', $css);
        $this->assertMatchesRegularExpression('#^<script src="[^"]+"></script>#', $js);
    }

    // =========================================================================
    // A csomagolás mechanizmusa - saját homokozóban
    // =========================================================================

    public function test_a_multi_file_call_concatenates_the_sources_in_order(): void
    {
        $packer = $this->sandboxPacker();
        $this->writeAsset('js/custom.js', 'CUSTOM');
        $this->writeAsset('js/modal.js', 'MODAL');

        $packer->js(['/js/custom.js', '/js/modal.js'], '/cache/js/all.js');

        // Fájlonként egy pontosvessző (Providers/JS.php:16), majd a nyers tartalom,
        // a megadott sorrendben. Ennyi a "csomagolás".
        $this->assertSame(';CUSTOM;MODAL', file_get_contents($packer->getFilePath()));
    }

    public function test_a_single_already_minified_file_is_copied_with_only_a_semicolon_added(): void
    {
        $packer = $this->sandboxPacker();
        $this->writeAsset('plugins/jquery/jquery.min.js', 'JQUERY-BODY');

        $packer->js('/plugins/jquery/jquery.min.js', '/cache/js/jquery.js');

        // A 16 hívásból 13 pontosan ilyen: EGYETLEN, már minifikált fájl. A Packer
        // ezekre annyit tesz, hogy átmásolja őket egy időbélyeges név alá. A valódi
        // public/cache/js/*-jquery.js ma 89 KB - a jquery.min.js egy pontosvesszővel.
        $this->assertSame(';JQUERY-BODY', file_get_contents($packer->getFilePath()));
    }

    public function test_the_filename_carries_the_newest_source_timestamp_and_follows_it(): void
    {
        $packer = $this->sandboxPacker();
        $this->writeAsset('js/a.js', 'A');
        $this->writeAsset('js/b.js', 'B');

        touch($this->sandbox.'/js/a.js', 1600000000);
        touch($this->sandbox.'/js/b.js', 1700000000);

        $first = basename($packer->js(['/js/a.js', '/js/b.js'], '/cache/js/all.js')->getFilePath());
        $this->assertSame('1700000000-all.js', $first, 'A prefix a bemenetek max(filemtime)-ja.');

        // Ez a cache-busting a csomag EGYETLEN ténylegesen szállított értéke ebben
        // a projektben - a minifikálás ki van kapcsolva, lásd a gap-készletet.
        touch($this->sandbox.'/js/a.js', 1800000000);

        $second = basename($packer->js(['/js/a.js', '/js/b.js'], '/cache/js/all.js')->getFilePath());
        $this->assertSame('1800000000-all.js', $second);
    }

    public function test_packer_creates_the_output_directory_tree_it_is_given(): void
    {
        $packer = $this->sandboxPacker();
        $this->writeAsset('js/a.js', 'A');

        $this->assertDirectoryDoesNotExist($this->sandbox.'/deep');

        $packer->js('/js/a.js', '/deep/nested/out.js');

        // Ez az a viselkedés, ami kérés közben ír a webgyökérbe, és ami a valódi
        // public/storage könyvtárat is létrehozta - lásd a gap-készletet.
        $this->assertDirectoryExists($this->sandbox.'/deep/nested');
        $this->assertFileExists($packer->getFilePath());
    }

    // =========================================================================
    // Segédek
    // =========================================================================

    /** Egy sima, aktivált felhasználó, aki a /home-ot 200-zal megkapja. */
    private function pageUser(): User
    {
        return $this->createUser([
            'email' => 'asset-pipeline@example.test',
            'email_verified_at' => now(),
        ]);
    }

    /** Ideiglenes public_path-ra állított, közvetlenül példányosított Packer. */
    private function sandboxPacker(): Packer
    {
        $this->sandbox = sys_get_temp_dir().'/kozter-packer-'.uniqid();
        mkdir($this->sandbox, 0777, true);

        return new Packer($this->sandboxConfig());
    }

    private function sandboxConfig(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }

    /** A konténeres singleton újrahúzása módosított configgal. */
    private function repointPacker(array $overrides): void
    {
        foreach ($overrides as $key => $value) {
            config(['packer.'.$key => $value]);
        }

        app()->forgetInstance('packer');
        Facade::clearResolvedInstance('packer');
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
