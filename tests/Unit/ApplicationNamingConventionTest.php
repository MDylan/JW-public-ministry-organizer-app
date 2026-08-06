<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 15: alkalmazás-szintű névészeti őrök.
 *
 * Az app/Notifications/GroupPriorityMessageNotificationTest.php egy véletlenül
 * kétszer lefuttatott `make:notification` maradványa volt: érintetlen sablon,
 * dispatch site nélkül, produkciós namespace-ben, `Test` utótaggal. Törölve.
 *
 * Ma ez a név még ártalmatlan: a phpunit.xml testsuite-jai kizárólag a
 * ./tests/Unit és ./tests/Feature könyvtárat pásztázzák, tehát az app/ alatti
 * fájl nem kerül felfedezésre. A TODO 40 viszont átírja a phpunit.xml-t a
 * PHPUnit 10 sémára - és a PHPUnit 10+ hibára fut egy olyan osztályon, amit a
 * suite felvesz, de nem örököl TestCase-ből. Ez az őr addig is a helyén tartja
 * a szabályt.
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
