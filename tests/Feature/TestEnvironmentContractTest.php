<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * TODO 40: phpunit.xml and .env.testing describe the same environment twice.
 *
 * WHY THIS IS A TRAP RATHER THAN MERE DUPLICATION
 *
 * PHPUnit writes the <php><server> entries into $_SERVER before the framework
 * boots. Laravel then loads .env.testing with Dotenv's immutable reader, which
 * does NOT overwrite a variable that already exists. So for every key declared
 * in both files, phpunit.xml wins and the .env.testing line is dead weight -
 * and editing it has no effect whatsoever, silently.
 *
 * That is the failure this guard exists for: not the duplication itself, but
 * somebody changing DB_DATABASE in .env.testing, watching the suite keep
 * running against the old schema, and having nothing to tell them why.
 *
 * Neither file can simply be deleted. phpunit.xml has to carry the
 * database-safety keys, because they must apply even if .env.testing is
 * missing - that is the pairing tests/CreatesApplication.php guards. And
 * .env.testing carries APP_KEY, without which nothing encrypted can be read.
 *
 * So both stay, and the rule is: where they overlap they must agree.
 */
class TestEnvironmentContractTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function phpunitServerVars(): array
    {
        $xml = simplexml_load_file(base_path('phpunit.xml'));

        $vars = [];

        foreach ($xml->php->server as $server) {
            $vars[(string) $server['name']] = (string) $server['value'];
        }

        return $vars;
    }

    /**
     * @return array<string, string>
     */
    private function envTestingVars(): array
    {
        $vars = [];

        foreach (file(base_path('.env.testing'), FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $vars[trim($key)] = trim(trim($value), '"');
        }

        return $vars;
    }

    public function test_the_two_test_environment_files_do_not_disagree(): void
    {
        $xml = $this->phpunitServerVars();
        $env = $this->envTestingVars();

        $shared = array_intersect_key($xml, $env);

        $this->assertNotEmpty($shared, 'Both files have to be readable for this to measure anything.');

        foreach ($shared as $key => $value) {
            $this->assertSame(
                $value,
                $env[$key],
                "{$key} differs between phpunit.xml and .env.testing. phpunit.xml wins at runtime, "
                .'so the .env.testing value would be silently ignored.'
            );
        }
    }

    public function test_the_database_safety_keys_live_in_phpunit_xml(): void
    {
        // These four are what stand between the suite and the application
        // database. They belong in the file PHPUnit reads first, so that they
        // hold even when .env.testing is absent.
        $xml = $this->phpunitServerVars();

        foreach (['APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'MAIL_MAILER'] as $key) {
            $this->assertArrayHasKey($key, $xml, "{$key} has to be declared in phpunit.xml.");
        }

        $this->assertSame('kozter_testing', $xml['DB_DATABASE']);
        $this->assertSame('testing', $xml['APP_ENV']);
    }

    public function test_phpunit_xml_declares_no_key_for_an_absent_package(): void
    {
        // TELESCOPE_ENABLED sat here for years with laravel/telescope never
        // installed. A setting nothing reads is not harmless: it suggests a
        // package is in play, and the next person to look for Telescope finds
        // only the switch.
        $this->assertArrayNotHasKey(
            'TELESCOPE_ENABLED',
            $this->phpunitServerVars(),
            'laravel/telescope is not a dependency of this project.'
        );
    }
}
