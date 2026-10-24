<?php

namespace Tests\Feature\Database;

use Tests\TestCase;

/**
 * Roadmap TODO 57: doctrine/dbal is gone.
 *
 * Up to Laravel 10 the package did two jobs here: `->change()` in migrations
 * and the installer's schema-manager table listing. Laravel 11 made
 * `change()` native whether or not the package is installed - which is why
 * `2026_09_06_120000_pin_the_changed_column_definitions` exists, see
 * ChangedColumnDefinitionsTest - and TODO 57.1 moved the installer onto
 * `Schema::getTableListing()`. After that nothing called the package, so it
 * went, with the two packages only it required.
 *
 * The suite's own `migrate:fresh` (RefreshDatabase) is the proof that every
 * migration still runs without it; these cases make sure it does not drift
 * back in through a `require` line.
 */
class DoctrineDbalRemovedTest extends TestCase
{
    public function test_the_package_is_not_installed(): void
    {
        $this->assertFalse(class_exists('Doctrine\DBAL\Connection'));
        $this->assertFalse(class_exists('Doctrine\Common\EventManager'));
        // doctrine/deprecations left with dbal at TODO 57 and came back at
        // TODO 69 for an unrelated reason: phpdocumentor/reflection-docblock
        // and type-resolver require it, and web-auth/webauthn-lib (the
        // passkeys) requires those. It is not dbal's, so it is not asserted
        // here; dbal itself and its event manager are.
    }

    public function test_composer_json_does_not_require_it(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertArrayNotHasKey('doctrine/dbal', $composer['require']);
        $this->assertArrayNotHasKey('doctrine/dbal', $composer['require-dev'] ?? []);
    }
}
