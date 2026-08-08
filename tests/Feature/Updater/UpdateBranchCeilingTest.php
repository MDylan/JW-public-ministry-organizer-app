<?php

namespace Tests\Feature\Updater;

use App\Http\Middleware\EnsureUpdateWithinBranch;
use App\Models\User;
use App\Support\Updates\UpdateBranch;
use App\View\Components\UpdateNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\Feature\FeatureTestCase;

/**
 * A frissítési ág plafonja: major verziót automatikusan nem lépünk át.
 *
 * MIÉRT VAN EZ KÜLÖN FÁJLBAN
 *
 * A UpdaterContractTest azt rögzíti, amit a VENDOR csinál, és ennek a
 * változásnak épp az a lényege, hogy a vendort nem érinti: a korlát egy
 * projektoldali réteg a csomag FÖLÖTT. A két szerződés így külön tud elromlani -
 * és ha valaki egyszer mégis beépíti a plafont a forkba, ez a fájl mondja meg,
 * mit kell tudnia.
 *
 * A csatorna itt is egy helyi könyvtár, ahogy a UpdaterContractTestben: az
 * `update_baseurl` sima útvonal is lehet, mert a kontroller
 * file_get_contents()-tel olvas.
 *
 * A TELEPÍTETT VERZIÓ MAJORJA 1 (version.txt), ezért végig az `1.9.9` az
 * "ágon belüli", a `2.0.0` pedig a "blokkolt" eset.
 */
class UpdateBranchCeilingTest extends FeatureTestCase
{
    private string $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = storage_path('framework/testing/update-channel-ceiling');
        File::deleteDirectory($this->channel);
        File::makeDirectory($this->channel, 0755, true);

        config(['laraupdater.update_baseurl' => $this->channel]);

        Cache::forget('laraupdater_lastversion');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->channel);

        parent::tearDown();
    }

    private function publishManifest(array $payload, string $file = 'laraupdater.json'): void
    {
        File::put($this->channel.'/'.$file, json_encode($payload));
    }

    private function publishVersion(string $version): void
    {
        $this->publishManifest([
            'version'     => $version,
            'archive'     => 'RELEASE-'.$version.'.zip',
            'description' => 'A kiadas leirasa.',
        ]);
    }

    private function admin(): User
    {
        return User::where('email', 'owner@example.test')->firstOrFail();
    }

    /** A guard közvetlen meghívása, HTTP nélkül. */
    private function guard(string $routeName): Response
    {
        $route = app('router')->getRoutes()->getByName($routeName);
        $request = Request::create('/'.$route->uri(), 'GET');
        $request->setRouteResolver(fn () => $route);

        return (new EnsureUpdateWithinBranch)->handle($request, fn () => new Response('atengedve'));
    }

    // =========================================================================
    // 1. UpdateBranch: maga a döntés
    // =========================================================================

    public function test_the_branch_allows_a_newer_release_of_the_same_major(): void
    {
        $this->assertSame(1, UpdateBranch::currentMajor());

        $this->assertTrue(UpdateBranch::allows('1.1.6'));
        $this->assertTrue(UpdateBranch::allows('1.9.9'));
        $this->assertTrue(UpdateBranch::allows('1.10.0'));
    }

    public function test_the_branch_blocks_every_higher_major(): void
    {
        $this->assertFalse(UpdateBranch::allows('2.0.0'));
        $this->assertFalse(UpdateBranch::allows('2.0.0-beta1'));
        $this->assertFalse(UpdateBranch::allows('10.0.0'));
    }

    public function test_an_unreadable_version_is_blocked_not_allowed(): void
    {
        // FAIL-CLOSED. A két tévedés nem egyenrangú: a fölösleges tiltás
        // annyit jelent, hogy az adminnak kézzel kell frissítenie, a
        // fölösleges engedés viszont éles rendszert tesz tönkre.
        $this->assertFalse(UpdateBranch::allows(''));
        $this->assertFalse(UpdateBranch::allows('kiadas'));
        $this->assertFalse(UpdateBranch::allows('.2.0'));
    }

    public function test_the_major_is_read_from_the_usual_version_spellings(): void
    {
        // A csatorna tartalmát nem mi validáljuk, ezért a kiolvasás megengedő.
        $this->assertSame(2, UpdateBranch::majorOf('2.0.0'));
        $this->assertSame(2, UpdateBranch::majorOf('v2.0.0'));
        $this->assertSame(2, UpdateBranch::majorOf('2.0.0-beta1'));
        $this->assertSame(2, UpdateBranch::majorOf(' 2.0 '));
        $this->assertNull(UpdateBranch::majorOf('kiadas'));
    }

    public function test_the_current_major_follows_version_txt(): void
    {
        $this->assertSame(
            (int) explode('.', trim(File::get(base_path('version.txt'))))[0],
            UpdateBranch::currentMajor()
        );
    }

    // =========================================================================
    // 2. A vendor viselkedése VÁLTOZATLAN
    // =========================================================================

    public function test_the_package_itself_still_reports_the_blocked_release(): void
    {
        // Ez pineli, hogy a plafon a csomag FÖLÖTT van. A check() dolga
        // továbbra is annyi, hogy megmondja, mit hirdet a csatorna - a
        // "telepíthetjük-e" kérdés nem az övé. Ha ez a teszt egyszer elbukik,
        // az azt jelenti, hogy valaki a vendorba is beleírta a korlátot, és
        // akkor a két réteget össze kell hangolni.
        $this->publishVersion('2.0.0');

        $this->assertSame('2.0.0', (new \MDylan\LaraUpdater\LaraUpdaterController)->check());
    }

    // =========================================================================
    // 3. A felület: melyik kártya jelenik meg
    // =========================================================================

    public function test_a_blocked_release_renders_the_manual_update_card(): void
    {
        $this->publishVersion('2.0.0');

        $rendered = (new UpdateNotification())->render();

        $this->assertSame('components.update-notification-manual', $rendered->name());
        $this->assertSame('2.0.0', $rendered->getData()['version']);
        $this->assertSame('A kiadas leirasa.', $rendered->getData()['description']);

        $html = $rendered->render();

        $this->assertStringContainsString('2.0.0', $html);
        $this->assertStringContainsString(trim(File::get(base_path('version.txt'))), $html);
        $this->assertStringContainsString('A kiadas leirasa.', $html);
        $this->assertStringContainsString(config('events.github_url'), $html);

        // A LÉNYEG: nincs mire kattintani. A gomb elrejtése önmagában nem
        // védelem (azt a guard adja), de egy működésképtelen gombot kínálni
        // ennél is rosszabb lenne.
        $this->assertStringNotContainsString('updater.update', $html);
    }

    public function test_a_release_within_the_branch_still_renders_the_normal_card(): void
    {
        $this->publishVersion('1.9.9');

        $rendered = (new UpdateNotification())->render();

        $this->assertSame('components.update-notification', $rendered->name());
        $this->assertSame('1.9.9', $rendered->getData()['version']);
        $this->assertStringContainsString('updater.update', $rendered->render());
    }

    // =========================================================================
    // 4. A guard: az /updater.update kapuja
    // =========================================================================

    public function test_the_update_endpoint_is_forbidden_for_a_higher_major(): void
    {
        // A gomb elrejtése nem véd: az URL kézzel is megnyitható, és onnantól
        // az update() letölt, karbantartás módba kapcsol és migrál.
        $this->publishVersion('2.0.0');

        $this->actingAs($this->admin());

        $this->get('/updater.update')->assertForbidden();
    }

    public function test_the_guard_reads_the_channel_afresh_not_from_the_cache(): void
    {
        // Az update() szándékosan cache nélkül olvas. Ha a guard a legfeljebb
        // 15 perces cache-bejegyzést nézné, a kettő elcsúszhatna: a kapu még
        // az ágon belüli 1.9.9-et látná, az update() viszont már a frissen
        // kirakott 2.0.0-t telepítené.
        $this->publishVersion('1.9.9');
        $this->assertSame('1.9.9', (new \MDylan\LaraUpdater\LaraUpdaterController)->check());

        $this->publishVersion('2.0.0');

        $this->actingAs($this->admin());

        $this->get('/updater.update')->assertForbidden();
    }

    public function test_the_guard_lets_a_release_within_the_branch_through(): void
    {
        // SZÁNDÉKOSAN nem a route-on keresztül. Az update() nyers echo-val ír
        // és exit-tel zár, ami megölné a PHPUnit folyamatot - ugyanaz az érv,
        // amiért a UpdaterContractTest sem hívja meg. A guard viszont önmagában
        // is meghívható, és épp az az érdekes, hogy TOVÁBBENGED.
        $this->publishVersion('1.9.9');

        $this->assertSame('atengedve', $this->guard('laraupdater.update')->getContent());
    }

    public function test_the_guard_ignores_the_read_only_endpoints(): void
    {
        // A check és a currentVersion nem telepít semmit, és épp az elérhető
        // verzió megmutatása a dolguk - elzárni őket értelmetlen lenne, akkor
        // is, ha a csatorna blokkolt kiadást hirdet.
        $this->publishVersion('2.0.0');

        $this->assertSame('atengedve', $this->guard('laraupdater.check')->getContent());
        $this->assertSame('atengedve', $this->guard('laraupdater.currentVersion')->getContent());
    }

    public function test_the_guard_does_not_block_when_the_channel_is_silent(): void
    {
        // Nincs manifeszt: a check() üres stringet ad. Az update() ilyenkor
        // magától kilép; a guardnak nincs dolga, és főleg nem szabad a
        // kiolvashatatlan verziót blokkolásnak minősítenie.
        $this->assertSame('atengedve', $this->guard('laraupdater.update')->getContent());
    }

    // =========================================================================
    // 5. A previous_version lánc - ez zárja ki a megkerülést
    // =========================================================================

    public function test_the_chain_keeps_the_last_same_major_release_installable(): void
    {
        // EZ A LEGFONTOSABB ESET. A plafon csak azokon a telepítéseken véd,
        // amelyeken MÁR FUT az őt tartalmazó kiadás. A régebbieket a lánc
        // hozza ide: ha a csatorna feje 2.0.0, és a previous_version-je az
        // utolsó 1.x kiadás, akkor egy régi telepítés előbb ARRA frissül fel -
        // vagyis a korlátot megkerülve senki nem juthat 2.0.0-ra.
        $this->publishManifest([
            'version'          => '2.0.0',
            'archive'          => 'RELEASE-2.0.0.zip',
            'description'      => 'uj foverzio',
            'previous_version' => '1.9.9',
        ]);
        $this->publishManifest([
            'version'     => '1.9.9',
            'archive'     => 'RELEASE-1.9.9.zip',
            'description' => 'az utolso 1.x',
        ], 'laraupdater-1.9.9.json');

        $this->assertSame('1.9.9', (new \MDylan\LaraUpdater\LaraUpdaterController)->check());

        $rendered = (new UpdateNotification())->render();
        $this->assertSame('components.update-notification', $rendered->name());

        $this->assertSame('atengedve', $this->guard('laraupdater.update')->getContent());
    }
}
