<?php

namespace Tests\Feature\Avatar;

use App\Http\Livewire\Groups\Messages;
use App\Models\GroupMessage;
use App\Models\GroupUser;
use App\Support\Avatar\InitialsAvatar;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 39.1: avatars on the group message board, after the package went.
 *
 * WHAT HAPPENED TO THE TODO 22.1 SUITE
 *
 * This file used to characterize a mechanism that no longer exists: laravolt
 * created a 100x100 PNG through Intervention Image, Messages::render() wrote it
 * to the `web` disk behind a Storage::exists() guard, and the view served it as
 * a static file. Seven of its ten tests described that mechanism - the PNG
 * signature, the configured dimensions, the GD driver, the disk-root/URL-prefix
 * pairing, "generated once", "never overwritten", and the stream() tripwire
 * that was supposed to fire when Intervention Image 4 arrived.
 *
 * None of them could survive the decision, because the decision was to stop
 * writing files at all. TODO 22.1's rule - a behaviour test may only be
 * rewritten when the behaviour genuinely changed - is met here, and this
 * docblock is the record of it rather than a silent diff.
 *
 * WHAT IS ACTUALLY BEING PROTECTED NOW
 *
 * The user-visible behaviour, which did NOT change: every message on the board
 * carries an avatar built from its author's name, an empty board carries none,
 * and one author yields one avatar however many messages they wrote. Plus the
 * new invariant that is easy to lose by accident - that rendering the board
 * touches no disk.
 *
 * The colour compatibility is proven in Tests\Unit\Support\InitialsAvatarTest
 * against hexes measured from the package before it was removed.
 */
class AvatarGenerationTest extends FeatureTestCase
{
    // =========================================================================
    // The behaviour that survived the migration
    // =========================================================================

    public function test_rendering_the_message_board_shows_an_avatar_for_the_author(): void
    {
        [$user, $group] = $this->boardWithOneMessage();

        Livewire::actingAs($user)
            ->test(Messages::class, ['group' => $group])
            ->assertSee(InitialsAvatar::dataUri($user->name), false);
    }

    public function test_an_empty_board_shows_no_avatar_at_all(): void
    {
        // Readable on purpose: a board the user may not read would show no
        // avatar either, and would prove nothing about having no messages.
        [$user, $group] = $this->readableBoard();

        Livewire::actingAs($user)
            ->test(Messages::class, ['group' => $group])
            ->assertDontSee('data:image/svg+xml', false);
    }

    public function test_one_author_yields_one_avatar_however_many_messages(): void
    {
        [$user, $group] = $this->boardWithOneMessage();

        GroupMessage::factory()->forGroup($group)->fromUser($user)->create();
        GroupMessage::factory()->forGroup($group)->fromUser($user)->create();

        $component = Livewire::actingAs($user)->test(Messages::class, ['group' => $group]);

        // render() keys the map by author, so three messages from one person
        // build one SVG - not three identical ones.
        $this->assertSame([$user->id], array_keys($component->viewData('avatars')));

        // The markup still carries one <img> per message, all pointing at that
        // single URI.
        $html = $component->lastRenderedDom;

        $this->assertSame(3, substr_count($html, 'data:image/svg+xml'));

        preg_match_all('/data:image\/svg\+xml;base64,[A-Za-z0-9+\/=]+/', $html, $matches);

        $this->assertCount(1, array_unique($matches[0]));
    }

    public function test_two_authors_get_their_own_avatars(): void
    {
        [$user, $group] = $this->boardWithOneMessage();

        $other = $this->createUser(['name' => 'Másik Szerző']);
        $this->attachUserToGroup($other, $group);
        GroupMessage::factory()->forGroup($group)->fromUser($other)->create();

        Livewire::actingAs($user)
            ->test(Messages::class, ['group' => $group])
            ->assertSee(InitialsAvatar::dataUri($user->name), false)
            ->assertSee(InitialsAvatar::dataUri('Másik Szerző'), false);
    }

    public function test_a_renamed_user_gets_the_avatar_of_their_current_name(): void
    {
        // REVERSED by TODO 39.1, and deliberately so. The Storage::exists()
        // guard in the old render() meant the first PNG ever written for a user
        // was the one served forever; a rename never reached the board. The SVG
        // is computed per render, so it follows the name.
        [$user, $group] = $this->boardWithOneMessage();

        $formerName = $user->name;

        Livewire::actingAs($user)
            ->test(Messages::class, ['group' => $group])
            ->assertSee(InitialsAvatar::dataUri($formerName), false);

        $user->forceFill(['name' => 'Új Név'])->save();

        Livewire::actingAs($user)
            ->test(Messages::class, ['group' => $group])
            ->assertSee(InitialsAvatar::dataUri('Új Név'), false)
            ->assertDontSee(InitialsAvatar::dataUri($formerName), false);
    }

    // =========================================================================
    // The new invariant: the board writes nothing
    // =========================================================================

    public function test_rendering_the_board_writes_no_file_to_the_web_disk(): void
    {
        [$user, $group] = $this->boardWithOneMessage();

        // The path the removed mechanism would have written to. If a
        // regeneration ever creeps back in - a cache, a "warm the avatars"
        // command - this is where it lands first.
        $path = 'avatars/avatar-'.$user->id.'.png';

        Storage::disk('web')->delete($path);

        Livewire::actingAs($user)->test(Messages::class, ['group' => $group]);

        $this->assertFalse(Storage::disk('web')->exists($path));
    }

    public function test_the_view_no_longer_addresses_a_stored_avatar_file(): void
    {
        $view = file_get_contents(resource_path('views/livewire/groups/messages.blade.php'));

        $this->assertStringContainsString('$avatars[$message->user_id]', $view);
        $this->assertStringNotContainsString("asset('avatars/avatar-'", $view);
        $this->assertStringNotContainsString("asset('public/avatars/avatar-'", $view);
    }

    // =========================================================================
    // The removal itself
    // =========================================================================

    public function test_the_avatar_package_and_its_imaging_library_are_gone(): void
    {
        $root = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertArrayNotHasKey('laravolt/avatar', $root['require']);
        $this->assertArrayNotHasKey('intervention/image', $root['require']);

        $lock = json_decode(file_get_contents(base_path('composer.lock')), true);
        $installed = array_column(array_merge($lock['packages'], $lock['packages-dev']), 'name');

        $this->assertNotContains('laravolt/avatar', $installed);
        $this->assertNotContains('intervention/image', $installed);

        $this->assertFalse(class_exists('Laravolt\Avatar\Avatar'));
        $this->assertFalse(class_exists('Intervention\Image\Image'));
    }

    public function test_the_generator_has_a_single_call_site(): void
    {
        $hits = $this->grepProjectSources('InitialsAvatar::');

        $this->assertCount(1, $hits, 'Új avatar-hívás keletkezett: '.implode(', ', $hits));
        $this->assertStringContainsString('Messages.php', $hits[0]);

        $this->assertSame([], $this->grepProjectSources('Avatar::create'));
    }

    public function test_the_removed_packages_configuration_is_gone(): void
    {
        $this->assertDirectoryDoesNotExist(config_path('laravolt'));
        $this->assertNull(config('laravolt.avatar.width'));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function boardWithOneMessage(): array
    {
        [$user, $group] = $this->readableBoard();

        GroupMessage::factory()->forGroup($group)->fromUser($user)->create();

        return [$user, $group];
    }

    /**
     * A group whose board the returned user may actually read.
     *
     * This is new work compared with the file-based suite, and the reason is
     * the migration itself. The old mechanism wrote its PNG from render()
     * REGARDLESS of privilege, so a test could observe it without ever passing
     * checkPrivilege(); the avatar now only exists in the rendered markup, so
     * every assertion here depends on the message list actually being drawn.
     *
     * message_use = 2 is the cheapest grant (Messages::checkPrivilege():146-149)
     * - it skips the "has an event in the next 24 hours" path that
     * Tests\Feature\Livewire\GroupMessagesTest exercises in full.
     */
    private function readableBoard(): array
    {
        $user = $this->createUser();
        $group = $this->createGroup(['messages_on' => 1]);
        $this->attachUserToGroup($user, $group);

        GroupUser::where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->update(['message_use' => 2]);

        return [$user, $group];
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
