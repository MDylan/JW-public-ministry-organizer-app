<?php

namespace Tests\Unit\Rules;

use App\Rules\TimeCheck;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule as DeprecatedRuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TODO 42: the service-day time comparison, which had no test of its own.
 *
 * Before this file the rule was covered only by component tests that all
 * submit 08:00-10:00, so exactly one branch of it ever ran. Nothing
 * exercised midnight, nothing exercised a rejection, and nothing looked at
 * the message text.
 *
 * WHY THIS IS DRIVEN THROUGH Validator::make() AND NOT THROUGH THE FORM.
 * The rules array below is a copy of the second validator in
 * UpdateGroupForm::updateGroup() (UpdateGroupForm.php:268-272), which
 * validates $this->days directly rather than through $this->state. Driving
 * it here needs no group, no user and no database, and it addresses the rule
 * rather than the form around it. GroupDayTimeRangeTest covers the same rule
 * end to end.
 *
 * NOTE THE ERROR KEYS: `3.start_time`, not `days.3.start_time`. That second
 * validator is handed the day rows as its whole data set, so the row key is
 * the top-level key. This is not a typo, and it is the shape the component
 * really produces - see GroupDayTimeRangeTest for what the view does with it.
 *
 * The suite runs with APP_LANG=hu (.env.testing), so the expected sentences
 * are the Hungarian ones from lang/hu/validation.php.
 */
class TimeCheckTest extends TestCase
{
    /**
     * A day row as the form submits it. A null time is omitted entirely,
     * which is how a row arrives when the field was never filled in.
     */
    private function days(int $day, ?string $start, ?string $end): array
    {
        $row = ['day_number' => (string) $day];

        if ($start !== null) {
            $row['start_time'] = $start;
        }

        if ($end !== null) {
            $row['end_time'] = $end;
        }

        return [$day => $row];
    }

    /**
     * UpdateGroupForm.php:268-272, verbatim.
     */
    private function validateDays(array $days): \Illuminate\Validation\Validator
    {
        return Validator::make($days, [
            '*.day_number' => 'required',
            '*.start_time' => ['required', 'date_format:H:i', new TimeCheck('end_time', 'before_or_midnight')],
            '*.end_time' => ['required', 'date_format:H:i', new TimeCheck('start_time', 'after_or_midnight')],
        ]);
    }

    /**
     * The 18:00-00:00 row is the one that justifies the rule's existence:
     * before_or_equal reads 00:00 as the start of the day and rejects it.
     */
    public static function acceptedRangeProvider(): array
    {
        return [
            'an ordinary range' => ['08:00', '10:00'],
            'a day that ends at midnight' => ['18:00', '00:00'],
            'no service at all' => ['00:00', '00:00'],
            'a day that starts at midnight' => ['00:00', '10:00'],
        ];
    }

    #[DataProvider('acceptedRangeProvider')]
    public function test_a_legitimate_range_passes(string $start, string $end): void
    {
        $validator = $this->validateDays($this->days(3, $start, $end));

        $this->assertFalse($validator->fails());
        $this->assertSame([], $validator->errors()->toArray());
    }

    /**
     * One assertion pins the lang keys, the :date replacement, the :attribute
     * resolution and the error-bag key shape at once.
     */
    public function test_a_reversed_range_is_rejected_naming_each_counterpart(): void
    {
        $validator = $this->validateDays($this->days(3, '10:00', '08:00'));

        $this->assertTrue($validator->fails());
        $this->assertSame([
            '3.start_time' => ['A(z) szolgálat kezdete 08:00 előtti dátum kell, hogy legyen!'],
            '3.end_time' => ['A(z) szolgálat vége 10:00 utáni dátum kell, hogy legyen!'],
        ], $validator->errors()->toArray());
    }

    public function test_a_zero_length_range_is_rejected(): void
    {
        $validator = $this->validateDays($this->days(3, '08:00', '08:00'));

        $this->assertTrue($validator->fails());
        $this->assertSame([
            '3.start_time' => ['A(z) szolgálat kezdete 08:00 előtti dátum kell, hogy legyen!'],
            '3.end_time' => ['A(z) szolgálat vége 08:00 utáni dátum kell, hogy legyen!'],
        ], $validator->errors()->toArray());
    }

    /**
     * The failure is recorded under the rule's OWN class, not under the
     * InvokableValidationRule the parser wraps it in. That unwrapping
     * (Validator.php:876-878) is what keeps assertHasErrors() with a rule
     * class working now that the rule implements ValidationRule; if a future
     * framework version changed it, this is what would say so.
     */
    public function test_the_failure_is_recorded_under_the_rule_class(): void
    {
        $validator = $this->validateDays($this->days(3, '10:00', '08:00'));

        $validator->fails();

        $this->assertSame([TimeCheck::class], array_keys($validator->failed()['3.start_time']));
    }

    /**
     * Before TODO 42 this array also carried
     * '3.start_time' => ['validation.'] - the rule failed without an error
     * string, so message() resolved a lang key that does not exist, and the
     * literal string was shown against the field that was fine.
     */
    public function test_a_missing_counterpart_reports_only_the_field_that_is_missing(): void
    {
        $validator = $this->validateDays($this->days(3, '08:00', null));

        $this->assertTrue($validator->fails());
        $this->assertSame([
            '3.end_time' => ['A(z) szolgálat vége megadása kötelező!'],
        ], $validator->errors()->toArray());
        $this->assertArrayNotHasKey('3.start_time', $validator->errors()->toArray());
    }

    /**
     * Skipping the comparison must not let anything through: the
     * counterpart's own rules still block the save.
     */
    public function test_a_missing_counterpart_still_fails_the_validation(): void
    {
        $this->assertTrue($this->validateDays($this->days(3, '08:00', null))->fails());
        $this->assertTrue($this->validateDays($this->days(3, null, '10:00'))->fails());
    }

    /**
     * Honest framing: this passes on the pre-TODO-42 rule too, because the
     * validator read message() immediately after passes(). It is a pin
     * against the mutable per-row state the rewrite removed, not the
     * demonstration of a bug that was live.
     */
    public function test_each_row_is_measured_against_its_own_counterpart(): void
    {
        $days = $this->days(1, '18:00', '12:00') + $this->days(3, '09:00', '07:00');

        $validator = $this->validateDays($days);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'A(z) szolgálat kezdete 12:00 előtti dátum kell, hogy legyen!',
            $validator->errors()->first('1.start_time')
        );
        $this->assertSame(
            'A(z) szolgálat kezdete 07:00 előtti dátum kell, hogy legyen!',
            $validator->errors()->first('3.start_time')
        );
    }

    public function test_the_rule_uses_the_current_validation_contract(): void
    {
        $rule = new TimeCheck('end_time', 'before_or_midnight');

        $this->assertInstanceOf(ValidationRule::class, $rule);
        $this->assertInstanceOf(DataAwareRule::class, $rule);
        $this->assertNotInstanceOf(DeprecatedRuleContract::class, $rule);
    }
}
