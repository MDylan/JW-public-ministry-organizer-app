<?php

namespace Tests\Feature\Updater;

use App\Models\User;
use App\View\Components\UpdateNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 18 / 33.4: a laraupdater önfrissítő szerződése.
 *
 * A csomagnak eddig NULLA tesztje volt, miközben a vendor fájljai kézzel
 * módosítva vannak - vagyis bármelyik `composer update` némán visszaállította
 * volna az upstream 1.0.2-t, és semmi nem szólt volna. Ez a fájl rögzíti azt a
 * viselkedést, amit a saját fork (mdylan/laraupdater v2) átvenni köteles.
 *
 * A távoli csatorna seamje maga a konfiguráció: az `update_baseurl` egy sima
 * könyvtárútvonal is lehet, mert a kontroller `file_get_contents()`-tel olvas.
 * Ezért nincs szükség Http::fake()-re, és ezért írható meg ez a fájl EGYSZER,
 * a fork-váltás előtt és után is ugyanúgy.
 *
 * Amit NEM lehet tesztelni: az `update()` metódust. Nyers `echo`-val ír a
 * kimenetre és `exit`-tel zár, ami megölné a PHPUnit folyamatot - ugyanaz az
 * érv, ami a TODO 12.1-ben a `dd()`-re vonatkozott. Az `update()` korai
 * kilépési ága (version_compare) így közvetve, a `check()`-en át van pinelve.
 */
class UpdaterContractTest extends FeatureTestCase
{
    private string $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = storage_path('framework/testing/update-channel');
        File::deleteDirectory($this->channel);
        File::makeDirectory($this->channel, 0755, true);

        config(['laraupdater.update_baseurl' => $this->channel]);

        // A getLastVersion() Cache::remember-be van csomagolva, tehát a
        // teszteknek tiszta lappal kell indulniuk.
        Cache::forget('laraupdater_lastversion');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->channel);

        parent::tearDown();
    }

    private function updater()
    {
        return new \MDylan\LaraUpdater\LaraUpdaterController();
    }

    /** Kiírja a csatorna egy manifesztjét. */
    private function publishManifest(array $payload, string $file = 'laraupdater.json'): void
    {
        File::put($this->channel.'/'.$file, json_encode($payload));
    }

    private function localVersion(): string
    {
        return trim(File::get(base_path('version.txt')));
    }

    private function admin(): User
    {
        return User::where('email', 'owner@example.test')->firstOrFail();
    }

    // =========================================================================
    // 1. A helyi verzió forrása
    // =========================================================================

    public function test_get_current_version_returns_the_trimmed_contents_of_version_txt(): void
    {
        $this->assertSame($this->localVersion(), $this->updater()->getCurrentVersion());
    }

    public function test_the_current_version_never_carries_surrounding_whitespace(): void
    {
        // Ez nem kozmetika. A check() a version_compare() JOBB oldalára teszi
        // ezt az értéket, és egy sortörés ott hamis pozitívot szül:
        //
        //   version_compare("1.1.5", "1.1.5\n", ">")  ===  true
        //
        // Vagyis trim() nélkül a rendszer ÖRÖKKÉ elérhető frissítést jelezne,
        // akkor is, ha a csatorna pontosan a telepített verziót hirdeti.
        // Ezért teherviselő a vendorban a `return trim($version);`.
        $version = $this->updater()->getCurrentVersion();

        $this->assertSame(trim($version), $version);
        $this->assertTrue(
            version_compare($version, $version."\n", '>'),
            'A hamis pozitív mechanizmusa, amit a trim() zár ki.'
        );
    }

    // =========================================================================
    // 2. check(): van-e frissítés
    // =========================================================================

    public function test_check_returns_an_empty_string_when_the_channel_advertises_the_installed_version(): void
    {
        $this->publishManifest([
            'version'     => $this->localVersion(),
            'archive'     => 'RELEASE.zip',
            'description' => 'ugyanaz',
        ]);

        $this->assertSame('', $this->updater()->check());
    }

    public function test_check_returns_an_empty_string_when_the_channel_is_behind(): void
    {
        $this->publishManifest(['version' => '0.0.1', 'archive' => 'RELEASE.zip', 'description' => 'regi']);

        $this->assertSame('', $this->updater()->check());
    }

    public function test_check_returns_the_remote_version_string_when_the_channel_is_ahead(): void
    {
        // A visszatérési típus STRING, nem tömb. A fork master ága (EgyptianM
        // 5e6dcb22 PR-je) itt tömbre váltott; az App\View\Components\
        // UpdateNotification stringet vár és a leírást külön kéri le, ezért a
        // v2 a vendor viselkedését viszi tovább.
        $this->publishManifest(['version' => '9.9.9', 'archive' => 'RELEASE-9.9.9.zip', 'description' => 'uj']);

        $this->assertSame('9.9.9', $this->updater()->check());
    }

    public function test_check_compares_versions_numerically_not_as_strings(): void
    {
        // A telepített verzió 1.1.5. Stringként "1.1.10" <= "1.1.5" IGAZ (a
        // negyedik karakternél '1' < '5'), tehát a naiv összehasonlítás
        // elrejtené a frissítést. Ez a pontos oka annak, hogy a vendor az
        // update() korai kilépési ágába is version_compare()-t tett - a fork
        // mastere ott még sima `<=`-t használ.
        $this->assertTrue('1.1.10' <= '1.1.5', 'A stringes összehasonlítás tévedésének demonstrációja.');

        $this->publishManifest(['version' => '1.1.10', 'archive' => 'RELEASE-1.1.10.zip', 'description' => 'uj']);

        $this->assertSame('1.1.10', $this->updater()->check());
    }

    public function test_check_survives_an_unreachable_update_channel(): void
    {
        // Nincs manifeszt a csatornán. A file_get_contents E_WARNING-ot dob,
        // amit a Laravel HandleExceptions ErrorException-né alakít - ezt a
        // Cache::remember closure-jébe tett try/catch nyeli el. A catch ág
        // tehát teherviselő: nélküle MINDEN admin oldalrenderelés 500-at adna,
        // amikor az update-szerver néma (UpdateNotification::render() és a
        // settings.blade.php is hívja).
        $this->assertSame('', $this->updater()->check());
    }

    // =========================================================================
    // 3. getDescription() és a gyorsítótár
    // =========================================================================

    public function test_get_description_returns_the_changelog_of_the_available_update(): void
    {
        $this->publishManifest([
            'version'     => '9.9.9',
            'archive'     => 'RELEASE-9.9.9.zip',
            'description' => 'Ez a valtozasnaplo.',
        ]);

        $this->assertSame('Ez a valtozasnaplo.', $this->updater()->getDescription());
    }

    public function test_check_and_get_description_read_the_channel_only_once(): void
    {
        // Az UpdateNotification::render() egymás után hívja a kettőt. Ha nem
        // lenne a Cache::remember, az két hálózati kérés lenne MINDEN admin
        // oldalrendereléskor. A bizonyíték: a manifeszt törlése a két hívás
        // között nem változtat a második eredményén.
        $this->publishManifest([
            'version'     => '9.9.9',
            'archive'     => 'RELEASE-9.9.9.zip',
            'description' => 'gyorsitotarbol',
        ]);

        $updater = $this->updater();
        $this->assertSame('9.9.9', $updater->check());

        File::delete($this->channel.'/laraupdater.json');

        $this->assertSame('gyorsitotarbol', $updater->getDescription());
    }

    // =========================================================================
    // 4. A previous_version lánc (többlépcsős frissítés)
    // =========================================================================

    public function test_the_previous_version_chain_hands_back_the_intermediate_release(): void
    {
        // Ha a legfrissebb kiadás egy köztes verziót jelöl meg előfeltételként,
        // és az is újabb a telepítettnél, akkor ELŐSZÖR azt kell telepíteni -
        // különben a köztes migrációk kimaradnának. Ez a vendor 5. kézi
        // módosítása, és a fork masterében egyáltalán nincs meg.
        $this->publishManifest([
            'version'          => '9.9.9',
            'archive'          => 'RELEASE-9.9.9.zip',
            'description'      => 'legujabb',
            'previous_version' => '1.1.6',
        ]);
        $this->publishManifest([
            'version'     => '1.1.6',
            'archive'     => 'RELEASE-1.1.6.zip',
            'description' => 'kozbenso',
        ], 'laraupdater-1.1.6.json');

        $this->assertSame('1.1.6', $this->updater()->check());
    }

    public function test_the_previous_version_chain_walks_back_more_than_one_step(): void
    {
        // A valós eset többlépcsős: 1.1.5 telepítve, a csatorna 1.2.0-t hirdet,
        // ami 1.1.7-et követel, ami 1.1.6-ot. A rekurziónak a LEGKORÁBBI még
        // függőben lévő lépcsőt kell visszaadnia, nem a legfrissebbet - egy
        // update() futás egy lépcsőt telepít, és a következő futás megy tovább.
        $this->publishManifest([
            'version'          => '1.2.0',
            'archive'          => 'RELEASE-1.2.0.zip',
            'description'      => 'harmadik',
            'previous_version' => '1.1.7',
        ]);
        $this->publishManifest([
            'version'          => '1.1.7',
            'archive'          => 'RELEASE-1.1.7.zip',
            'description'      => 'masodik',
            'previous_version' => '1.1.6',
        ], 'laraupdater-1.1.7.json');
        $this->publishManifest([
            'version'     => '1.1.6',
            'archive'     => 'RELEASE-1.1.6.zip',
            'description' => 'elso',
        ], 'laraupdater-1.1.6.json');

        $this->assertSame('1.1.6', $this->updater()->check());
        $this->assertSame('elso', $this->updater()->getDescription());
    }

    public function test_the_chain_stops_at_the_first_step_that_is_already_installed(): void
    {
        // Ugyanaz a lánc, de a köztes 1.1.6 lépcsőt már feltettük - a telepített
        // verzió 1.1.5-nél a 1.1.7 az elsö függőben lévő, ha az ő
        // previous_version-je már nem újabb a telepítettnél.
        $this->publishManifest([
            'version'          => '1.2.0',
            'archive'          => 'RELEASE-1.2.0.zip',
            'description'      => 'harmadik',
            'previous_version' => '1.1.7',
        ]);
        $this->publishManifest([
            'version'          => '1.1.7',
            'archive'          => 'RELEASE-1.1.7.zip',
            'description'      => 'masodik',
            'previous_version' => '1.1.5',
        ], 'laraupdater-1.1.7.json');

        $this->assertSame('1.1.7', $this->updater()->check());
    }

    public function test_an_already_installed_previous_version_does_not_divert_the_chain(): void
    {
        // Ha a köztes kiadás már fent van, a lánc nem lép hátra.
        $this->publishManifest([
            'version'          => '9.9.9',
            'archive'          => 'RELEASE-9.9.9.zip',
            'description'      => 'legujabb',
            'previous_version' => '0.0.1',
        ]);

        $this->assertSame('9.9.9', $this->updater()->check());
    }

    // =========================================================================
    // 5. A frissítő végpontok
    // =========================================================================

    public function test_the_three_updater_routes_are_registered(): void
    {
        $uris = [];

        foreach (app('router')->getRoutes() as $route) {
            $uris[] = $route->uri();
        }

        $this->assertContains('updater.check', $uris);
        $this->assertContains('updater.currentVersion', $uris);
        $this->assertContains('updater.update', $uris);
    }

    /**
     * @dataProvider updaterEndpoints
     */
    public function test_every_updater_endpoint_rejects_a_guest(string $uri): void
    {
        // SZÁNDÉKOSAN a KÍVÁNT állapotot állítja, nem a mait. A csomag
        // vendor-beli routes.php-ja a check és a currentVersion végpontra
        // SEMMILYEN middleware-t nem tesz - még `web`-et sem -, tehát ma bárki
        // lekérdezheti a telepített verziót (upgrade-notes/baseline-routes.txt:250-252),
        // miközben az `allow_users_id => false` a kontrollerbeli ID-ellenőrzést
        // is kikapcsolja. Ez a két eset a fork-váltás előtt pirosan indul: ez a
        // kontroll-lépés.
        $this->get('/'.$uri)->assertRedirect(route('login'));
    }

    /**
     * @dataProvider updaterEndpoints
     */
    public function test_every_updater_endpoint_rejects_a_non_admin(string $uri): void
    {
        $this->actingAs($this->createUser(['email' => 'nem-admin@example.test']));

        $this->get('/'.$uri)->assertForbidden();
    }

    public function test_an_admin_may_read_the_current_version_endpoint(): void
    {
        $this->actingAs($this->admin());

        $this->get('/updater.currentVersion')
            ->assertOk()
            ->assertSee($this->localVersion());
    }

    public static function updaterEndpoints(): array
    {
        return [
            'check'          => ['updater.check'],
            'currentVersion' => ['updater.currentVersion'],
            'update'         => ['updater.update'],
        ];
    }

    // =========================================================================
    // 6. Az UpdateNotification Blade komponens
    // =========================================================================

    public function test_the_notification_component_renders_nothing_when_the_system_is_current(): void
    {
        $this->publishManifest([
            'version'     => $this->localVersion(),
            'archive'     => 'RELEASE.zip',
            'description' => 'ugyanaz',
        ]);

        $this->assertSame('', (new UpdateNotification())->render());
    }

    /*
     * A hármas blokk verziószáma SZÁNDÉKOSAN 1.9.9, nem 9.9.9, mint fentebb.
     *
     * A frissítési ág plafonja óta (App\Support\Updates\UpdateBranch) a 9.9.9
     * már nem egyszerűen "újabb kiadás", hanem MAGASABB MAJOR - vagyis a
     * komponens a kézi frissítés kártyáját adná rá, nem ezt. Ezek a tesztek a
     * szokásos, gombos kártyáról szólnak, tehát ágon belüli verzió kell hozzá.
     * A fenti check()/getDescription() blokkok maradhatnak 9.9.9-en: azok a
     * vendort pinelik, amit a plafon nem érint.
     *
     * A plafon saját szerződése a UpdateBranchCeilingTestben van.
     */

    public function test_the_notification_component_renders_the_card_when_an_update_exists(): void
    {
        $this->publishManifest([
            'version'     => '1.9.9',
            'archive'     => 'RELEASE-1.9.9.zip',
            'description' => 'Valtozasnaplo szovege.',
        ]);

        $rendered = (new UpdateNotification())->render();

        $this->assertInstanceOf(View::class, $rendered);
        $this->assertSame('components.update-notification', $rendered->name());
        $this->assertSame('1.9.9', $rendered->getData()['version']);
        $this->assertSame('Valtozasnaplo szovege.', $rendered->getData()['description']);

        $html = $rendered->render();

        $this->assertStringContainsString($this->localVersion(), $html);
        $this->assertStringContainsString('1.9.9', $html);
        $this->assertStringContainsString('Valtozasnaplo szovege.', $html);
    }

    public function test_the_update_button_points_at_a_generated_url_not_a_hardcoded_path(): void
    {
        // A vendor-korszakban a gomb href-je a hardkódolt "/updater.update"
        // volt, ami minden alkönyvtáras telepítésen eltörik. A v2 nevesíti a
        // route-ot, így route() generálhatja. Az APP_URL a phpunit.xml szerint
        // http://kozter.test, tehát az abszolút alak csak generálásból jöhet.
        $this->publishManifest(['version' => '1.9.9', 'archive' => 'RELEASE-1.9.9.zip', 'description' => 'x']);

        $html = (new UpdateNotification())->render()->render();

        $this->assertStringContainsString('http://kozter.test/updater.update', $html);
    }

    public function test_the_notification_component_reports_the_online_user_count(): void
    {
        $this->publishManifest(['version' => '1.9.9', 'archive' => 'RELEASE-1.9.9.zip', 'description' => 'x']);

        $this->createUser(['email' => 'online@example.test', 'last_activity' => now()->subSeconds(30)]);

        $this->assertSame(1, (new UpdateNotification())->render()->getData()['online']);
    }
}
