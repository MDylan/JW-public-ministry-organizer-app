<?php

namespace Tests\Feature\Updater;

use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 73 / 75: the 2.x line takes its updates from the `v2`
 * channel.
 *
 * Until this change config/laraupdater.php defaulted to `.../v1` on this
 * branch too. UpdateBranch only refuses a HIGHER major, so an install of
 * the branch - `version.txt` still `1.2.0` - was offered the 1.x release
 * from v1, and accepting it would have overwritten a Laravel 13 install
 * with the Laravel 8 archive.
 *
 * The suite sets the channel to a local directory everywhere else, so
 * nothing pinned the default. The config file is evaluated here directly,
 * with and without UPDATE_CHANNEL.
 */
class UpdateChannelTest extends FeatureTestCase
{
    public function test_the_default_channel_is_v2(): void
    {
        $channel = $this->withEnvValue('UPDATE_CHANNEL', null, fn () => (require config_path('laraupdater.php'))['update_baseurl']);

        $this->assertSame('https://updates.teruletek.hu/v2', $channel);
    }

    public function test_an_explicit_update_channel_still_wins(): void
    {
        $channel = $this->withEnvValue('UPDATE_CHANNEL', 'https://example.test/own', fn () => (require config_path('laraupdater.php'))['update_baseurl']);

        $this->assertSame('https://example.test/own', $channel);
    }
}
