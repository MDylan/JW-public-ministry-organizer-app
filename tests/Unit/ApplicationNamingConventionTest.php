<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 15: application-level naming-convention guards.
 *
 * app/Notifications/GroupPriorityMessageNotificationTest.php was the leftover
 * of a `make:notification` accidentally run twice: an untouched template,
 * with no dispatch site, in the production namespace, with a `Test` suffix.
 * Deleted.
 *
 * Today this name is still harmless: the phpunit.xml testsuites scan
 * exclusively the ./tests/Unit and ./tests/Feature directories, so a file
 * under app/ is never discovered. TODO 40, however, rewrites phpunit.xml to
 * the PHPUnit 10 schema - and PHPUnit 10+ errors out on a class that the
 * suite picks up but that does not extend TestCase. This guard keeps the
 * rule in place until then.
 */
class ApplicationNamingConventionTest extends TestCase
{
    public function test_no_application_class_carries_a_phpunit_test_suffix(): void
    {
        $offenders = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                $offenders[] = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            'Produkciós osztály nem viselhet `Test` utótagot - a PHPUnit teszt-osztálynak nézné.'
        );
    }
}
