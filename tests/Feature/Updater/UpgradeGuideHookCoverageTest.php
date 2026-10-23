<?php

namespace Tests\Feature\Updater;

use Tests\Feature\FeatureTestCase;

/**
 * Every path the frozen 1.x hook removes, and that no 1.x release ever removed,
 * has to be in upgrade-guide.md (TODO 54).
 *
 * release/upgrade.php is the hook LaraUpdaterController::install() runs after
 * unpacking a release. Its Phase 3 half (TODO 33.2 - 33.8) was written on the
 * 2.x branch and never shipped: the last 1.x release, 1.2.0, carries a hook
 * that stops at vendor/rakibdevs, and 2.x never reaches install() because
 * UpdateBranch refuses a higher major. So on every deployed host that cleanup
 * is still to do, and the guide is the only place an operator learns about it.
 * One of those paths stops every Artisan command when it lingers (see the
 * guide's section 3), which is why the list cannot be left to memory.
 *
 * The test reads both files as text. It cannot see a host, and it does not
 * try to: it only keeps the two documents from drifting apart.
 */
class UpgradeGuideHookCoverageTest extends FeatureTestCase
{
    /**
     * The hook entries that DID ship, in 1.2.0's own hook, and so already ran
     * on every host that updated through the 1.x line.
     */
    private const SHIPPED_IN_1X = [
        'vendor/pcinaglia',
        'resources/views/vendor/laraupdater',
        'bootstrap/cache/packages.php',
        'bootstrap/cache/services.php',
        'vendor/rakibdevs',
    ];

    public function test_every_unshipped_hook_path_is_in_the_upgrade_guide(): void
    {
        $guide = file_get_contents(base_path('upgrade-guide.md'));
        $unshipped = array_diff($this->hookPaths(), self::SHIPPED_IN_1X);

        // A floor, so a broken regex cannot pass this by finding nothing:
        // Phase 3 left fifteen single paths and four glob patterns in main().
        $this->assertGreaterThanOrEqual(19, count($unshipped));

        foreach ($unshipped as $path) {
            $this->assertStringContainsString(
                $path,
                $guide,
                "release/upgrade.php removes {$path}, no 1.x release ever did, and upgrade-guide.md "
                .'does not mention it - an operator upgrading to 2.0.0 would never learn it is there.'
            );
        }
    }

    public function test_the_public_storage_repair_is_in_the_upgrade_guide(): void
    {
        // The one Phase 3 step that is not a plain removal.
        $this->assertStringContainsString(
            'laraupdater_upgrade_repair_public_storage()',
            file_get_contents(base_path('release/upgrade.php'))
        );
        $this->assertStringContainsString('`public/storage`', file_get_contents(base_path('upgrade-guide.md')));
    }

    /**
     * Every base_path() argument in the hook - the removals and the globs.
     *
     * @return list<string>
     */
    private function hookPaths(): array
    {
        $source = file_get_contents(base_path('release/upgrade.php'));
        $main = substr($source, strpos($source, 'function main()'));
        $main = substr($main, 0, strpos($main, 'return $ok;'));

        preg_match_all("/base_path\\(\\s*'([^']+)'\\s*\\)/", $main, $removals);
        preg_match_all("/^\\s*'([^']*\\*[^']*)',\\s*$/m", $main, $globs);

        return array_values(array_unique(array_merge($removals[1], $globs[1])));
    }
}
