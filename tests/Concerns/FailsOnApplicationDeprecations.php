<?php

namespace Tests\Concerns;

/**
 * Fails a test when the application's OWN code raises a PHP deprecation.
 *
 * WHY THIS EXISTS, AND WHY `phpunit.xml` CANNOT DO THE SAME JOB
 *
 * PHPUnit 10 has a `failOnDeprecation` attribute, and in a Laravel test
 * suite it is inert. Two measurements, both taken from the installed
 * vendor tree rather than from the documentation:
 *
 * 1. `HandleExceptions::bootstrap()` installs its own `set_error_handler()`
 *    while the application boots, and `shouldIgnoreDeprecationErrors()`
 *    returns true whenever `runningUnitTests()` is true and
 *    `LOG_DEPRECATIONS_WHILE_TESTING` is unset. The handler therefore
 *    returns without logging, rethrowing or forwarding, so a deprecation
 *    raised under test is dropped on the floor.
 * 2. `PHPUnit\Runner\ErrorHandler::enable()` gives up when another handler
 *    is already registered - it calls `restore_error_handler()` and
 *    returns. Laravel never removes its handler, so from the first booted
 *    test onwards PHPUnit's own handler is not in the chain at all.
 *
 * So "the suite reports no deprecation notices" was never evidence that the
 * code raises none: nothing in the run was able to see one. This trait is
 * what makes the claim testable. It registers a handler ON TOP of Laravel's,
 * after the application has booted, and hands every level it does not own
 * back to the handler it replaced, so nothing else about the run changes.
 *
 * SCOPE IS DELIBERATELY `app_path()` ONLY. A deprecation raised inside
 * `vendor/` is a dependency's business and still reaches Laravel's handler,
 * which drops it exactly as it does today. Failing on those would make the
 * suite hostage to whatever a `composer update` happens to install.
 *
 * IT COLLECTS AND FAILS IN TEARDOWN RATHER THAN THROWING FROM THE HANDLER.
 * A deprecation does not interrupt execution in production either, and a
 * handler that throws would change the control flow it is supposed to be
 * observing - the test would then fail somewhere other than the place being
 * measured, and the first deprecation would hide every later one.
 *
 * THE TWO METHOD NAMES ARE LOAD-BEARING.
 * `Illuminate\Foundation\Testing\Concerns\InteractsWithTestCaseLifecycle::setUpTraits()`
 * calls `setUp<TraitBasename>()` and registers `tearDown<TraitBasename>()`
 * as a before-application-destroyed callback. It runs AFTER
 * `refreshApplication()`, which is exactly the ordering this needs: the
 * guard has to be the outermost handler, and it can only be that if
 * Laravel's is already in place when it registers.
 */
trait FailsOnApplicationDeprecations
{
    /**
     * Deprecations raised by `app/` code during the current test.
     *
     * @var array<int, string>
     */
    private array $applicationDeprecations = [];

    private bool $deprecationGuardInstalled = false;

    /**
     * Registers the guard on top of the handler Laravel installed at boot.
     */
    protected function setUpFailsOnApplicationDeprecations(): void
    {
        $applicationPath = $this->normalizedPath(app_path());

        $previous = set_error_handler(
            function (int $level, string $message, string $file = '', int $line = 0) use (&$previous, $applicationPath) {
                $isDeprecation = ($level & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0;

                if ($isDeprecation && str_starts_with($this->normalizedPath($file), $applicationPath)) {
                    $this->applicationDeprecations[] = sprintf('%s in %s on line %d', $message, $file, $line);

                    return true;
                }

                // Everything else behaves exactly as it did before this trait
                // existed: it goes to the handler this one displaced.
                return $previous === null
                    ? false
                    : $previous($level, $message, $file, $line);
            }
        );

        $this->deprecationGuardInstalled = true;
    }

    /**
     * Restores the handler stack and reports whatever the guard collected.
     */
    protected function tearDownFailsOnApplicationDeprecations(): void
    {
        if (! $this->deprecationGuardInstalled) {
            return;
        }

        restore_error_handler();
        $this->deprecationGuardInstalled = false;

        $deprecations = array_values(array_unique($this->applicationDeprecations));
        $this->applicationDeprecations = [];

        if ($deprecations === []) {
            return;
        }

        $this->fail(
            "The application's own code raised ".count($deprecations)." deprecation(s):\n- "
            .implode("\n- ", $deprecations)
        );
    }

    /**
     * Compares paths without caring which separator the platform used.
     *
     * Both spellings occur on Windows within a single run: `app_path()` is
     * built with DIRECTORY_SEPARATOR, while a file included through the
     * Composer autoloader reports forward slashes.
     */
    private function normalizedPath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
