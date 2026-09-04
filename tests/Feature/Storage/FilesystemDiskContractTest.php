<?php

namespace Tests\Feature\Storage;

use Tests\TestCase;

/**
 * TODO 37: what config/filesystems.php actually declares, which nothing
 * measured before.
 *
 * The Laravel 9 hop replaced Flysystem 1.1.10 with 3.35.2, and TODO 30 is the
 * reason the suite stayed green across it: every disk carries 'throw' => false
 * and the web disk roots at public_path(). Both of those are load-bearing and
 * neither was asserted anywhere, so a later edit could quietly remove them.
 *
 * This file pins the configuration itself. The behaviour that configuration
 * produces is the subject of FlysystemThreeSemanticsTest, which builds real
 * disks from these very entries.
 *
 * The web disk's root/URL pairing is deliberately NOT repeated here:
 * AvatarGenerationTest:166 already asserts public_path() against
 * filesystems.disks.web.root, and duplicating it would give a future edit two
 * places to satisfy instead of one.
 */
class FilesystemDiskContractTest extends TestCase
{
    /**
     * The whole disk list, as a single assertion.
     *
     * Written as one assertSame on the key list rather than five separate
     * assertArrayHasKey calls, because the thing worth guarding is the SET:
     * an entry appearing is as much a change as an entry vanishing.
     */
    public function test_the_application_configures_exactly_five_disks()
    {
        $this->assertSame(
            ['local', 'public', 'web', 'news_files', 's3'],
            array_keys(config('filesystems.disks'))
        );
    }

    /**
     * Four of the five disks are local; the fifth names a driver whose adapter
     * package is not installed and never was.
     *
     * league/flysystem-aws-s3-v3 is absent from composer.json, from
     * composer.lock and from vendor/league/, and no application code addresses
     * the s3 disk. Resolving it would fail at the adapter, not at the
     * credentials - so the entry is unreachable configuration rather than an
     * unconfigured feature.
     */
    public function test_the_s3_disk_names_a_driver_whose_adapter_is_not_installed()
    {
        $drivers = array_map(
            fn (array $disk): string => $disk['driver'],
            config('filesystems.disks')
        );

        $this->assertSame(
            ['local' => 'local', 'public' => 'local', 'web' => 'local', 'news_files' => 'local', 's3' => 's3'],
            $drivers
        );

        $this->assertFalse(
            class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class),
            'The s3 adapter package is installed; the s3 disk is no longer unreachable configuration.'
        );
    }

    /**
     * TODO 30 set this on every disk and the Laravel 9 hop depended on it.
     *
     * Asserted as a strict false rather than a falsy check, because
     * FilesystemAdapter::throwsExceptions() reads ($this->config['throw'] ??
     * false) - a missing key and an explicit false behave identically today,
     * and the point of the entry is to say the choice was made on purpose.
     */
    public function test_every_disk_suppresses_flysystem_exceptions()
    {
        foreach (config('filesystems.disks') as $name => $disk) {
            $this->assertArrayHasKey('throw', $disk, "Disk '{$name}' does not declare 'throw'.");
            $this->assertFalse($disk['throw'], "Disk '{$name}' does not suppress Flysystem exceptions.");
        }
    }

    /**
     * The default disk is what the bare Storage:: facade calls land on, and
     * two of this application's sentinels are exactly that: the installer's
     * installed.txt (routes/web.php:104, app/Exceptions/Handler.php:60,
     * Setup/MetaController.php:89) and EnsureInstallerToken's token file
     * (:78-89). Both therefore live under storage/app.
     */
    public function test_the_default_disk_is_local_and_roots_at_storage_app()
    {
        $this->assertSame('local', config('filesystems.default'));
        $this->assertSame(storage_path('app'), config('filesystems.disks.local.root'));
    }

    /**
     * Only the public disk is linked into the docroot.
     *
     * The news_files entry on the next line of the config is commented out on
     * purpose: that disk holds group news attachments, which are served
     * through GroupNewsFileDownloadController behind the group-member
     * middleware. A symlink would put them in the docroot and route around
     * the authorization entirely.
     */
    public function test_the_storage_link_map_covers_only_the_public_disk()
    {
        $this->assertSame(
            [public_path('storage') => storage_path('app/public')],
            config('filesystems.links')
        );
    }
}
