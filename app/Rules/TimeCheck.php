<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Compares one time field of an array row against its sibling in the same
 * row, allowing the pair to span midnight.
 *
 * WHY THIS EXISTS AT ALL, given `before_or_equal` / `after_or_equal`.
 * A group's service day may end at 00:00, meaning "midnight at the end of
 * this day". Every ordinary comparison rule reads 00:00 as the START of the
 * day and rejects 08:00 - 00:00, which is a legitimate template. The two
 * `*_or_midnight` types below encode that exception and nothing else.
 *
 * WHY IT IS DATA-AWARE rather than parameterised (`before_or_equal:other`).
 * It is attached to a wildcard attribute, so it has to find the SIBLING of
 * the row currently under validation, and the row key is only knowable from
 * the concrete attribute the validator hands in (`3.start_time`).
 */
class TimeCheck implements ValidationRule, DataAwareRule
{
    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Create a new rule instance.
     *
     * @param string $other The sibling field to compare against, e.g. `end_time`.
     * @param string $type  `after_or_midnight` or `before_or_midnight`.
     *
     * @return void
     */
    public function __construct(
        private $other,
        private $type,
    ) {
    }

    /**
     * Set the data under validation.
     *
     * Still reached under the current contract: InvokableValidationRule::passes()
     * calls setData($this->validator->getData()) whenever the invokable it
     * wraps is a DataAwareRule.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * THE COMPARISON IS SKIPPED, NOT FAILED, when the counterpart is missing
     * or is not a string. A comparison cannot decide anything without both
     * operands, and the counterpart always carries its own `required` and
     * `date_format:H:i` rules, which report the real problem against the
     * field that actually has it. The `passes()` this replaced returned false
     * here instead, with no error key set, so `message()` resolved
     * `trans('validation.')` - and the user saw the literal string
     * `validation.` attached to the wrong field. See TimeCheckTest.
     *
     * There is deliberately no state left on the object. A single instance
     * validates every row of the wildcard, so the error string and the
     * counterpart's time used to be properties shared across rows; building
     * the message inside this method makes that unrepresentable.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $siblingPath = Str::contains($attribute, '.')
            ? Str::beforeLast($attribute, '.').'.'.$this->other
            : $this->other;

        $otherValue = Arr::get($this->data, $siblingPath);

        if (! is_string($otherValue)) {
            return;
        }

        $midnight = strtotime('00:00');
        $other = strtotime($otherValue);
        $time = strtotime((string) $value);

        if ($other === false || $time === false) {
            return;
        }

        // Both ends at midnight is the "no service" template, not a range.
        if ($time === $other && $time === $midnight) {
            return;
        }

        if ($this->type === 'after_or_midnight') {
            if ($time > $other || $time === $midnight) {
                return;
            }

            $fail('validation.after')->translate(['date' => date('H:i', $other)]);

            return;
        }

        if ($this->type === 'before_or_midnight') {
            if ($time < $other) {
                return;
            }

            // The counterpart ends at midnight, so anything later than 00:00
            // precedes it on the same day.
            if ($other === $midnight && $time > $midnight) {
                return;
            }

            $fail('validation.before')->translate(['date' => date('H:i', $other)]);
        }
    }
}
