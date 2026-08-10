<?php

namespace Tests\Feature\Avatar;

use App\Http\Livewire\Groups\Messages;
use App\Models\GroupMessage;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Image;
use Laravolt\Avatar\Facade as Avatar;
use Livewire\Livewire;
use Psr\Http\Message\StreamInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 22.1: the characterization suite for avatar generation.
 *
 * WHY THIS EXISTS
 *
 * TODO 22 was meant to establish that bumping versions on the remaining
 * dependencies is enough. For a single package this is demonstrably not true:
 * the installed 4.1.7 of `laravolt/avatar` runs out at `illuminate/support
 * ^9.0`, so resolution already fails AT PHASE 5 (Laravel 10) - and the
 * `composer.json` `^4.1` constraint admits no L10+ release at all. Every
 * L12/L13-capable line (6.1.2 and above) requires `intervention/image ^3.4`
 * or `^4.0`, where a piece of the mechanism below stops existing.
 *
 * The package has EXACTLY ONE call site in the whole project
 * (app/Http/Livewire/Groups/Messages.php:174-176), and before TODO 22 zero
 * tests exercised it. This file records what happens TODAY, so the Phase 5
 * migration's (TODO 39.1) diff can be reviewed.
 *
 * THE MEASURED FACT THAT DECIDES THE TRIPWIRE
 *
 * In Intervention Image 2, `stream()` is NOT a real method: it is declared by
 * the `@method` line of the Image class's docblock (Image.php:53), and
 * `__call()` (Image.php:106) forwards it on to Commands\StreamCommand. In the
 * v4 Image.php there is no `stream()`, no `__call()`, and no Commands
 * namespace - instead there is `encode(EncoderInterface)` and
 * `encodeUsingFormat(Format)`. The tripwire section records exactly this
 * difference, in both directions.
 *
 * LITTERING
 *
 * The integration tests write to the REAL `web` disk, because only that
 * proves where the file ends up. This is not new damage: `public/avatars/*`
 * is gitignored (.gitignore:5). Whatever the test creates, tearDown() cleans up.
 */
class AvatarGenerationTest extends FeatureTestCase
{
    /** Avatar files created by the test, relative to the `web` disk. */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if (Storage::disk('web')->exists($path)) {
                Storage::disk('web')->delete($path);
            }
        }

        $this->written = [];

        parent::tearDown();
    }

    /** The PNG signature: 89 50 4E 47 0D 0A 1A 0A. */
    private function pngSignature(): string
    {
        return chr(0x89).'PNG'.chr(0x0D).chr(0x0A).chr(0x1A).chr(0x0A);
    }

    // =========================================================================
    // The mechanism, as it works today
    // =========================================================================

    public function test_the_facade_produces_an_intervention_image_and_a_png_stream(): void
    {
        $image = Avatar::create('Test User')->getImageObject();

        $this->assertInstanceOf(Image::class, $image);

        $stream = $image->stream('png');

        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertSame($this->pngSignature(), substr((string) $stream, 0, 8));
    }

    public function test_the_generated_image_carries_the_configured_dimensions(): void
    {
        $bytes = (string) Avatar::create('Test User')->getImageObject()->stream('png');

        [$width, $height] = getimagesizefromstring($bytes);

        $this->assertSame(100, config('laravolt.avatar.width'));
        $this->assertSame(100, config('laravolt.avatar.height'));
        $this->assertSame(100, $width);
        $this->assertSame(100, $height);
    }

    public function test_the_configured_driver_is_gd_and_it_is_available(): void
    {
        // Switching to 6.x also rewrites the Intervention driver handling, so
        // the starting state is recorded here: GD, not Imagick.
        $this->assertSame('gd', config('laravolt.avatar.driver'));
        $this->assertTrue(extension_loaded('gd'));
    }

    // =========================================================================
    // The integration: the Groups\Messages component
    // =========================================================================

    public function test_rendering_the_message_board_writes_one_png_per_author_to_the_web_disk(): void
    {
        [$user, $group] = $this->boardWithOneMessage();

        $path = $this->avatarPathFor($user->id);

        Storage::disk('web')->delete($path);
        $this->assertFalse(Storage::disk('web')->exists($path));

        Livewire::actingAs($user)->test(Messages::class, ['group' => $group]);

        $this->assertTrue(Storage::disk('web')->exists($path));
        $this->assertSame(
            $this->pngSignature(),
            substr(Storage::disk('web')->get($path), 0, 8)
        );
    }

    public function test_the_avatar_is_generated_once_and_never_overwritten(): void
    {
        [$user, $group] = $this->boardWithOneMessage();

        $path = $this->avatarPathFor($user->id);

        Livewire::actingAs($user)->test(Messages::class, ['group' => $group]);
        $this->assertTrue(Storage::disk('web')->exists($path));

        // The Storage::exists() guard in Messages::render() (Messages.php:173)
        // is the only thing holding back regeneration. If it fails, this
        // sentinel disappears.
        Storage::disk('web')->put($path, 'SENTINEL');

        Livewire::actingAs($user)->test(Messages::class, ['group' => $group]);

        $this->assertSame('SENTINEL', Storage::disk('web')->get($path));
    }

    public function test_an_empty_board_generates_no_avatar_at_all(): void
    {
        $user = $this->createUser();
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group);

        $path = $this->avatarPathFor($user->id);

        Storage::disk('web')->delete($path);

        Livewire::actingAs($user)->test(Messages::class, ['group' => $group]);

        $this->assertFalse(Storage::disk('web')->exists($path));
    }

    public function test_the_disk_root_and_the_views_url_prefix_are_a_matched_pair(): void
    {
        // REVERSED by the v1-patch A5 fix.
        //
        // Previously the `web` disk root was the bare relative 'public', while
        // the view addressed the same file via asset('public/avatars/...').
        // The two only met in a WEB REQUEST, where PHP's working directory is
        // the public/ directory itself, so the relative 'public' resolved to
        // public/public/ - exactly where the URL pointed too. Under Artisan, a
        // queue worker, or a test, however, the working directory is the
        // project root, so the same disk wrote into public/, while the view
        // still requested from public/public/. The old .gitignore line
        // (`/public/public/avatars/*`) was the imprint of this.
        //
        // Now the root is absolute (public_path()), and the URL prefix lost
        // the duplicated 'public/' segment. The pair is still only correct
        // TOGETHER - that's why this test stays in one file covering both.
        $this->assertSame(public_path(), config('filesystems.disks.web.root'));

        $view = file_get_contents(resource_path('views/livewire/groups/messages.blade.php'));

        $this->assertStringContainsString("asset('avatars/avatar-'", $view);
        $this->assertStringNotContainsString("asset('public/avatars/avatar-'", $view);
    }

    // =========================================================================
    // Tripwire: what Intervention Image 4 removes - property of TODO 39.1
    // =========================================================================

    public function test_gap_stream_is_a_magic_method_that_intervention_image_4_removes(): void
    {
        $this->assertFalse(
            method_exists(Image::class, 'stream'),
            'A stream() ma NEM valódi metódus - ha az lenne, a docblock-alapú érvelés megdőlne.'
        );

        $this->assertTrue(method_exists(Image::class, '__call'));
        $this->assertTrue(class_exists('Intervention\Image\Commands\StreamCommand'));

        $this->assertFalse(
            method_exists(Image::class, 'encodeUsingFormat'),
            'Az encodeUsingFormat() a v4 API-ja; amint létezik, a hívási helyet át kell írni.'
        );

        $this->assertTrue(is_callable([Avatar::create('Test User')->getImageObject(), 'stream']));
    }

    public function test_gap_the_only_avatar_call_site_is_the_group_message_board(): void
    {
        $hits = $this->grepProjectSources('Avatar::');

        $this->assertCount(1, $hits, 'Új avatar-hívás keletkezett: '.implode(', ', $hits));
        $this->assertStringContainsString('Messages.php', $hits[0]);
    }

    public function test_gap_the_declared_constraint_admits_no_laravel_10_capable_release(): void
    {
        // The TODO 22 measurement, recorded. The `^4.1` upper bound is 5.0.0,
        // and the first L10-capable release is exactly 5.0.0 - so the
        // constraint admits nothing, and a constraint edit is needed, not a
        // lock bump.
        $root = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertSame('^4.1', $root['require']['laravolt/avatar']);

        $installed = $this->lockedPackage('laravolt/avatar');

        $this->assertSame('4.1.7', $installed['version']);
        $this->assertSame(
            '^6.0|^7.0|^8.0|^9.0',
            $installed['require']['illuminate/support'],
            'A telepített vonal a Laravel 9-nél elfogy, tehát a Phase 5-ön hasal el.'
        );

        // It's the intervention/image 2.x line that brings stream().
        $this->assertSame('2.7.2', $this->lockedPackage('intervention/image')['version']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function avatarPathFor(int $userId): string
    {
        $path = 'avatars/avatar-'.$userId.'.png';

        $this->written[] = $path;

        return $path;
    }

    private function boardWithOneMessage(): array
    {
        $user = $this->createUser();
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group);

        GroupMessage::factory()->forGroup($group)->fromUser($user)->create();

        return [$user, $group];
    }

    private function lockedPackage(string $name): array
    {
        $lock = json_decode(file_get_contents(base_path('composer.lock')), true);

        foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
            if ($package['name'] === $name) {
                return $package;
            }
        }

        $this->fail($name.' nincs a composer.lock-ban.');
    }

    /** @return array<int, string> "file:line" hits in the project's own sources. */
    private function grepProjectSources(string $needle): array
    {
        $hits = [];

        foreach (['app', 'config', 'routes', 'resources'] as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(base_path($directory), RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                foreach (file($file->getPathname()) as $number => $line) {
                    if (strpos($line, $needle) !== false) {
                        $hits[] = $file->getPathname().':'.($number + 1);
                    }
                }
            }
        }

        sort($hits);

        return $hits;
    }
}
