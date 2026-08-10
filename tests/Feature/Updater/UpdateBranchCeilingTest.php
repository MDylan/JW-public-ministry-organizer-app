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
 * The update branch's ceiling: we do not automatically cross a major version.
 *
 * WHY THIS IS IN A SEPARATE FILE
 *
 * UpdaterContractTest records what the VENDOR does, and the whole point of
 * this change is that it does not touch the vendor: the limit is a
 * project-side layer ABOVE the package. The two contracts can thus break
 * independently - and if someone ever does build the ceiling into the fork,
 * this file states what it needs to know.
 *
 * Here too the channel is a local directory, as in UpdaterContractTest:
 * `update_baseurl` can be a plain path, because the controller reads with
 * file_get_contents().
 *
 * The INSTALLED VERSION'S MAJOR is 1 (version.txt), so throughout, `1.9.9`
 * is the "within branch" case, and `2.0.0` is the "blocked" case.
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

    /** Directly invokes the guard, without HTTP. */
    private function guard(string $routeName): Response
    {
        $route = app('router')->getRoutes()->getByName($routeName);
        $request = Request::create('/'.$route->uri(), 'GET');
        $request->setRouteResolver(fn () => $route);

        return (new EnsureUpdateWithinBranch)->handle($request, fn () => new Response('atengedve'));
    }

    // =========================================================================
    // 1. UpdateBranch: the decision itself
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
        // FAIL-CLOSED. The two mistakes are not equivalent: an unnecessary
        // block simply means the admin has to update manually, while an
        // unnecessary allow wrecks a production system.
        $this->assertFalse(UpdateBranch::allows(''));
        $this->assertFalse(UpdateBranch::allows('kiadas'));
        $this->assertFalse(UpdateBranch::allows('.2.0'));
    }

    public function test_the_major_is_read_from_the_usual_version_spellings(): void
    {
        // We don't validate the channel's content, so the parsing is permissive.
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
    // 2. The vendor's behavior is UNCHANGED
    // =========================================================================

    public function test_the_package_itself_still_reports_the_blocked_release(): void
    {
        // This pins that the ceiling is above the package. check()'s job
        // remains simply to say what the channel advertises - the "can we
        // install it" question is not its concern. If this test ever fails,
        // it means someone has also written the limit into the vendor, and
        // then the two layers need to be reconciled.
        $this->publishVersion('2.0.0');

        $this->assertSame('2.0.0', (new \MDylan\LaraUpdater\LaraUpdaterController)->check());
    }

    // =========================================================================
    // 3. The UI: which card is displayed
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

        // THE POINT: there is nothing to click. Hiding the button by itself
        // is not protection (the guard provides that), but offering a
        // non-functional button would be even worse.
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
    // 4. The guard: the gate for /updater.update
    // =========================================================================

    public function test_the_update_endpoint_is_forbidden_for_a_higher_major(): void
    {
        // Hiding the button does not protect: the URL can be opened by hand
        // too, and from there update() downloads, switches to maintenance mode, and migrates.
        $this->publishVersion('2.0.0');

        $this->actingAs($this->admin());

        $this->get('/updater.update')->assertForbidden();
    }

    public function test_the_guard_reads_the_channel_afresh_not_from_the_cache(): void
    {
        // update() deliberately reads without a cache. If the guard looked
        // at the up-to-15-minute-old cache entry instead, the two could
        // drift apart: the gate would still see the in-branch 1.9.9, while
        // update() would already install the freshly published 2.0.0.
        $this->publishVersion('1.9.9');
        $this->assertSame('1.9.9', (new \MDylan\LaraUpdater\LaraUpdaterController)->check());

        $this->publishVersion('2.0.0');

        $this->actingAs($this->admin());

        $this->get('/updater.update')->assertForbidden();
    }

    public function test_the_guard_lets_a_release_within_the_branch_through(): void
    {
        // DELIBERATELY not through the route. update() writes with a raw
        // echo and closes with exit, which would kill the PHPUnit process -
        // the same argument for which UpdaterContractTest doesn't call it
        // either. The guard, however, can be invoked on its own, and what's
        // interesting here is precisely that it LETS IT THROUGH.
        $this->publishVersion('1.9.9');

        $this->assertSame('atengedve', $this->guard('laraupdater.update')->getContent());
    }

    public function test_the_guard_ignores_the_read_only_endpoints(): void
    {
        // check and currentVersion install nothing, and showing the
        // available version is precisely their job - blocking them would be
        // pointless, even if the channel advertises a blocked release.
        $this->publishVersion('2.0.0');

        $this->assertSame('atengedve', $this->guard('laraupdater.check')->getContent());
        $this->assertSame('atengedve', $this->guard('laraupdater.currentVersion')->getContent());
    }

    public function test_the_guard_does_not_block_when_the_channel_is_silent(): void
    {
        // There is no manifest: check() gives an empty string. update() then
        // exits on its own; the guard has nothing to do, and above all must
        // not classify an unreadable version as a block.
        $this->assertSame('atengedve', $this->guard('laraupdater.update')->getContent());
    }

    // =========================================================================
    // 5. The previous_version chain - this is what rules out bypassing it
    // =========================================================================

    public function test_the_chain_keeps_the_last_same_major_release_installable(): void
    {
        // THIS IS THE MOST IMPORTANT CASE. The ceiling only protects
        // installs that ALREADY RUN the release containing it. The chain
        // brings older ones up to that point: if the channel's head is
        // 2.0.0, and its previous_version is the last 1.x release, then an
        // old install first updates to THAT - meaning no one can bypass the
        // limit and reach 2.0.0 directly.
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
