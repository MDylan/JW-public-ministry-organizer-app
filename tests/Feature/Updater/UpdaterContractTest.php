<?php

namespace Tests\Feature\Updater;

use App\Models\User;
use App\View\Components\UpdateNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 18 / 33.4: laraupdater's self-update contract.
 *
 * The package had ZERO tests so far, while its vendor files are manually
 * modified - meaning any `composer update` would have silently reverted them
 * to upstream 1.0.2, and nothing would have said a word. This file records
 * the behavior that our own fork (mdylan/laraupdater v2) is obligated to carry over.
 *
 * The remote channel's seam is the configuration itself: `update_baseurl`
 * can be a plain directory path too, because the controller reads with
 * `file_get_contents()`. That's why there's no need for Http::fake(), and
 * why this file can be written ONCE, the same both before and after the fork switch.
 *
 * What CANNOT be tested: the `update()` method. It writes to output with a
 * raw `echo` and closes with `exit`, which would kill the PHPUnit process -
 * the same argument that applied to `dd()` in TODO 12.1. `update()`'s early
 * exit branch (version_compare) is thus pinned indirectly, through `check()`.
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

        // getLastVersion() is wrapped in Cache::remember, so the tests need
        // to start with a clean slate.
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

    /**
     * An instance of the updater with a given INSTALLED version.
     *
     * version.txt is part of the release process: every release rewrites
     * it. The scenarios below, however, are about the RELATIONSHIP between
     * the installed and the advertised version, not about a specific
     * number - if they relied on the real file, a version bump would
     * silently shift their premise. That is exactly what happened: the
     * 1.1.5 -> 1.2.0 bump broke four tests without anything changing in the
     * behavior under test.
     *
     * getCurrentVersion() is public, and check(), getDescription() and
     * getLastVersion() all call $this->getCurrentVersion(), so overriding it
     * controls the installed version without side effects - there's no need
     * to keep rewriting version.txt in the repo for it.
     *
     * What this does NOT prove - that the value comes from version.txt,
     * trimmed - is covered by section 1's two tests, which read the real file.
     */
    private function updaterInstalledAt(string $version)
    {
        return new class($version) extends \MDylan\LaraUpdater\LaraUpdaterController
        {
            /** @var string */
            private $installed;

            public function __construct(string $installed)
            {
                $this->installed = $installed;
            }

            public function getCurrentVersion()
            {
                return $this->installed;
            }
        };
    }

    /** Writes out a manifest for the channel. */
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
    // 1. The source of the local version
    // =========================================================================

    public function test_get_current_version_returns_the_trimmed_contents_of_version_txt(): void
    {
        $this->assertSame($this->localVersion(), $this->updater()->getCurrentVersion());
    }

    public function test_the_current_version_never_carries_surrounding_whitespace(): void
    {
        // This is not cosmetic. check() puts this value on the RIGHT side of
        // version_compare(), and a line break there produces a false positive:
        //
        //   version_compare("1.1.5", "1.1.5\n", ">")  ===  true
        //
        // In other words, without trim() the system would report an update
        // as ALWAYS available, even when the channel advertises exactly the
        // installed version. That is why `return trim($version);` in the
        // vendor is load-bearing.
        $version = $this->updater()->getCurrentVersion();

        $this->assertSame(trim($version), $version);
        $this->assertTrue(
            version_compare($version, $version."\n", '>'),
            'The false-positive mechanism that trim() rules out.'
        );
    }

    // =========================================================================
    // 2. check(): is there an update
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
        // The return type is a STRING, not an array. The fork's master
        // branch (EgyptianM's PR 5e6dcb22) switched to an array here;
        // App\View\Components\UpdateNotification expects a string and fetches the
        // description separately, so v2 carries forward the vendor's original behavior.
        $this->publishManifest(['version' => '9.9.9', 'archive' => 'RELEASE-9.9.9.zip', 'description' => 'uj']);

        $this->assertSame('9.9.9', $this->updater()->check());
    }

    public function test_check_compares_versions_numerically_not_as_strings(): void
    {
        // Installed at 1.1.5. As strings, "1.1.10" <= "1.1.5" is TRUE (at the
        // fourth character, '1' < '5'), so a naive comparison would hide the
        // update. This is the exact reason the vendor also put
        // version_compare() into update()'s early-exit branch - the fork's
        // master still uses a plain `<=` there.
        //
        // The version pair is fixed, because the trap only occurs for
        // certain numbers: the installed patch number's first digit must be
        // greater than the advertised one's. That's why we give the
        // installed version explicitly here, rather than taking it from version.txt.
        $this->assertTrue('1.1.10' <= '1.1.5', 'Demonstration of the string comparison\'s mistake.');

        $this->publishManifest(['version' => '1.1.10', 'archive' => 'RELEASE-1.1.10.zip', 'description' => 'uj']);

        $this->assertSame('1.1.10', $this->updaterInstalledAt('1.1.5')->check());
    }

    public function test_check_survives_an_unreachable_update_channel(): void
    {
        // There is no manifest on the channel. file_get_contents throws an
        // E_WARNING, which Laravel's HandleExceptions turns into an
        // ErrorException - the try/catch placed in Cache::remember's closure
        // swallows this. The catch branch is therefore load-bearing: without
        // it EVERY admin page render would give a 500 when the update server
        // is silent (both UpdateNotification::render() and
        // settings.blade.php call this).
        $this->assertSame('', $this->updater()->check());
    }

    // =========================================================================
    // 3. getDescription() and the cache
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
        // UpdateNotification::render() calls the two of them one after the
        // other. Without Cache::remember, that would be two network requests
        // on EVERY admin page render. The proof: deleting the manifest
        // between the two calls does not change the second one's result.
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
    // 4. The previous_version chain (multi-step update)
    // =========================================================================

    public function test_the_previous_version_chain_hands_back_the_intermediate_release(): void
    {
        // If the latest release names an intermediate version as a
        // prerequisite, and that version is also newer than the installed
        // one, then it must be installed FIRST - otherwise the intermediate
        // migrations would be skipped. This is the vendor's 5th manual
        // modification, and it's entirely absent from the fork's master.
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

        $this->assertSame('1.1.6', $this->updaterInstalledAt('1.1.5')->check());
    }

    public function test_the_previous_version_chain_walks_back_more_than_one_step(): void
    {
        // The real case is multi-step: 1.1.5 installed, the channel
        // advertises 1.2.0, which requires 1.1.7, which requires 1.1.6. The
        // recursion must return the EARLIEST still-pending step, not the
        // latest one - a single update() run installs one step, and the
        // next run carries on from there.
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

        $this->assertSame('1.1.6', $this->updaterInstalledAt('1.1.5')->check());
        $this->assertSame('elso', $this->updaterInstalledAt('1.1.5')->getDescription());
    }

    public function test_the_chain_stops_at_the_first_step_that_is_already_installed(): void
    {
        // The same chain, but the intermediate 1.1.6 step has already been
        // installed - with the installed version at 1.1.5, 1.1.7 is the
        // first pending step once its own previous_version is no longer newer than the installed one.
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

        $this->assertSame('1.1.7', $this->updaterInstalledAt('1.1.5')->check());
    }

    public function test_an_already_installed_previous_version_does_not_divert_the_chain(): void
    {
        // If the intermediate release is already installed, the chain does not step backward.
        $this->publishManifest([
            'version'          => '9.9.9',
            'archive'          => 'RELEASE-9.9.9.zip',
            'description'      => 'legujabb',
            'previous_version' => '0.0.1',
        ]);

        $this->assertSame('9.9.9', $this->updater()->check());
    }

    // =========================================================================
    // 5. The updater endpoints
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
        // DELIBERATELY asserts the DESIRED state, not today's. The
        // package's vendor routes.php puts NO middleware at all on the check
        // and currentVersion endpoints - not even `web` - so today anyone
        // can query the installed version
        // (upgrade-notes/baseline-routes.txt:250-252), while
        // `allow_users_id => false` also disables the controller's ID check.
        // These two cases start red before the fork switch: this is the control step.
        $this->get('/'.$uri)->assertRedirect(route('login'));
    }

    /**
     * @dataProvider updaterEndpoints
     */
    public function test_every_updater_endpoint_rejects_a_non_admin(string $uri): void
    {
        $this->actingAs($this->createUser(['email' => 'non-admin@example.test']));

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
    // 6. The UpdateNotification Blade component
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
     * The version number in this block of three is DELIBERATELY 1.9.9, not
     * 9.9.9 like above.
     *
     * Since the update-branch ceiling (App\Support\Updates\UpdateBranch),
     * 9.9.9 is no longer simply "a newer release" but a HIGHER MAJOR - i.e.
     * the component would render the manual-update card for it, not this
     * one. These tests are about the usual, button-bearing card, so they
     * need an in-branch version. The check()/getDescription() blocks above
     * can stay at 9.9.9: those pin the vendor, which the ceiling does not affect.
     *
     * The ceiling's own contract is in UpdateBranchCeilingTest.
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
        // In the vendor era, the button's href was the hardcoded
        // "/updater.update", which breaks on any subdirectory install. v2
        // names the route, so route() can generate it. Per phpunit.xml,
        // APP_URL is http://kozter.test, so the absolute form can only come from generation.
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
