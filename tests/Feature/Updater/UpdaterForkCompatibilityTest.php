<?php

namespace Tests\Feature\Updater;

use Composer\InstalledVersions;
use Tests\TestCase;

/**
 * Roadmap TODO 58: the self-updater fork does not block a framework hop.
 *
 * The updater is the project's own fork, mdylan/laraupdater (TODO 33.4). The
 * upstream 1.0.2 declared only `php >=5.4.0` and so blocked nothing by
 * accident; the fork declares an `illuminate/support` range on purpose, which
 * means it CAN block a hop - Composer would refuse the next framework major
 * until the fork admits it. TODO 55 measured that it already admits 11, 12
 * and 13, so the roadmap's remaining hops (Phase 9, 10) do not need a fork
 * release first.
 *
 * These cases read the installed package's own metadata, not the fork's
 * repository: the range that matters is the one Composer resolved against.
 */
class UpdaterForkCompatibilityTest extends TestCase
{
    public function test_the_fork_admits_the_installed_framework_major(): void
    {
        $framework = $this->majorOf(InstalledVersions::getPrettyVersion('laravel/framework'));

        $this->assertContains(
            $framework,
            $this->admittedMajors(),
            "mdylan/laraupdater does not admit laravel/framework {$framework}.x - release the fork first."
        );
    }

    public function test_the_fork_already_admits_the_roadmaps_remaining_majors(): void
    {
        // Laravel 12 (Phase 9) and 13 (the roadmap's target). When the fork
        // drops a major, this case says so before a resolution does.
        foreach ([11, 12, 13] as $major) {
            $this->assertContains($major, $this->admittedMajors());
        }
    }

    public function test_the_installed_fork_is_the_projects_own(): void
    {
        $this->assertTrue(InstalledVersions::isInstalled('mdylan/laraupdater'));
        $this->assertFalse(InstalledVersions::isInstalled('pcinaglia/laraupdater'));
        $this->assertSame(2, $this->majorOf(InstalledVersions::getPrettyVersion('mdylan/laraupdater')));
    }

    /** The majors of the fork's `illuminate/support` constraint, e.g. `^8.12|^9.0` -> [8, 9]. */
    private function admittedMajors(): array
    {
        $installed = json_decode(file_get_contents(base_path('vendor/composer/installed.json')), true);
        $fork = collect($installed['packages'])->firstWhere('name', 'mdylan/laraupdater');

        $this->assertNotNull($fork, 'mdylan/laraupdater is not in vendor/composer/installed.json.');

        $constraint = $fork['require']['illuminate/support'] ?? '';

        // Only plain caret alternatives are understood; anything else fails
        // loudly instead of being half-parsed.
        $this->assertMatchesRegularExpression('/^\^\d+(\.\d+)*(\s*\|\|?\s*\^\d+(\.\d+)*)*$/', $constraint);

        preg_match_all('/\^(\d+)/', $constraint, $m);

        return array_map('intval', $m[1]);
    }

    private function majorOf(string $version): int
    {
        return (int) ltrim(explode('.', $version)[0], 'v');
    }
}
