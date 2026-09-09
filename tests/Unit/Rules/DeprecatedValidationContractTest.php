<?php

namespace Tests\Unit\Rules;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 42: keeps the deprecated validation contracts out of app/.
 *
 * WHY A GUARD AND NOT JUST THE MIGRATION. Moving App\Rules\Throttle and
 * App\Rules\TimeCheck onto ValidationRule is behaviour-neutral - every
 * behavioural test in the suite stays green if the change is reverted. There
 * is therefore nothing except this file to stop the old contract reappearing,
 * whether by a revert or by the next `make:rule` written from an old example.
 * TODO 41 handed the item to TODO 42 rather than fixing it in passing; this
 * is what stops it being handed on again.
 *
 * THE TRAP THIS MUST NOT FALL INTO. Illuminate\Validation\Rule - WITHOUT
 * `Contracts` - is the static builder behind Rule::unique() and
 * Rule::notIn(), a completely different class, and it is legitimately
 * imported by app/Actions/Fortify/UpdateUserProfileInformation.php,
 * app/Actions/Fortify/CreateNewUser.php,
 * app/Http/Livewire/Groups/ListUsers.php and
 * app/Http/Livewire/Admin/Users/ListUsers.php. The `Contracts` segment in the
 * pattern below is load-bearing: without it this guard reports four false
 * positives, gets reverted, and the real rule is lost with it.
 *
 * WHAT IT CANNOT SEE, stated rather than implied. This reads source text, so
 * a class that inherits one of these contracts from a parent or picks it up
 * through a trait goes unnoticed. Reflection would catch that, at the price
 * of autoloading every class under app/; with app/Rules holding two files and
 * nothing else in app/ implementing a validation contract, the text scan is
 * the cheaper instrument for the same result.
 */
class DeprecatedValidationContractTest extends TestCase
{
    /**
     * Rule is deprecated in favour of ValidationRule; ImplicitRule and
     * InvokableRule are deprecated alongside it.
     */
    private const DEPRECATED_CONTRACTS = '/Illuminate\\\\Contracts\\\\Validation\\\\(Rule|ImplicitRule|InvokableRule)\b/';

    public function test_no_application_class_implements_a_deprecated_validation_contract(): void
    {
        $offenders = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);

            foreach ($lines as $number => $line) {
                if (preg_match(self::DEPRECATED_CONTRACTS, $line)) {
                    $offenders[] = sprintf(
                        '%s:%d',
                        str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1)),
                        $number + 1
                    );
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            'These files use a deprecated validation contract. Use '
            .'Illuminate\Contracts\Validation\ValidationRule and its '
            .'validate($attribute, $value, Closure $fail) method instead.'
        );
    }

    /**
     * The control experiment for the trap above, kept as an assertion rather
     * than as a note: the static builder must NOT match the pattern.
     */
    public function test_the_pattern_ignores_the_static_rule_builder(): void
    {
        $this->assertSame(
            0,
            preg_match(self::DEPRECATED_CONTRACTS, 'use Illuminate\Validation\Rule;'),
            'The static Rule builder is not a deprecated contract.'
        );

        $this->assertSame(
            0,
            preg_match(self::DEPRECATED_CONTRACTS, 'use Illuminate\Contracts\Validation\ValidationRule;'),
            'ValidationRule is the replacement, not an offender.'
        );

        $this->assertSame(
            1,
            preg_match(self::DEPRECATED_CONTRACTS, 'use Illuminate\Contracts\Validation\Rule;'),
            'The deprecated contract must still be recognised.'
        );
    }
}
