<?php

namespace Tests\Unit\Rules;

use App\Rules\Throttle;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Validation\Rule as DeprecatedRuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * TODO 42: the rate-limiting validation rule, which had no test of its own.
 *
 * GroupMessagesTest already proved that a fourth message inside a minute is
 * rejected, but it goes through the whole component and asserts nothing
 * about the rule itself - not the message, not the key, and not where the
 * counter is incremented. This file addresses the rule.
 *
 * NO CLEANUP IS NEEDED between tests: phpunit.xml pins CACHE_DRIVER=array,
 * so the limiter starts empty for every test.
 *
 * ONE fails() PER ATTEMPT. Calling fails() twice on the same validator runs
 * the rule twice and therefore spends two attempts, which is easy to do by
 * accident when an assertion is written casually.
 *
 * The suite runs with APP_LANG=hu (.env.testing), so the expected sentence is
 * the Hungarian one from lang/hu/group.php.
 */
class ThrottleTest extends TestCase
{
    /**
     * The message rules from Groups\Messages::rules(), minus the length
     * bounds that are not this rule's business.
     */
    private function attempt(Throttle $rule): \Illuminate\Validation\Validator
    {
        return Validator::make(['message' => 'hello there'], ['message' => ['required', $rule]]);
    }

    /**
     * Throttle::throttleKey() - the key it was constructed with, the current
     * request's IP appended.
     */
    private function limiterKey(string $key): string
    {
        return $key.'|'.request()->ip();
    }

    public function test_the_budget_is_spent_after_the_configured_number_of_attempts(): void
    {
        $rule = new Throttle('probe-budget', 3, 1);

        $this->assertFalse($this->attempt($rule)->fails(), 'First attempt should pass.');
        $this->assertFalse($this->attempt($rule)->fails(), 'Second attempt should pass.');
        $this->assertFalse($this->attempt($rule)->fails(), 'Third attempt should pass.');
        $this->assertTrue($this->attempt($rule)->fails(), 'Fourth attempt should be blocked.');
    }

    /**
     * THIS IS THE TEST THAT PAYS FOR ->translate().
     *
     * $fail() on its own puts whatever string it is handed into the error
     * bag, so dropping ->translate() from the rule leaves the raw lang key
     * there and the form renders `group.messages.limit` to the user. Nothing
     * else in the suite would notice.
     */
    public function test_the_message_is_the_translated_sentence_and_not_the_lang_key(): void
    {
        $rule = new Throttle('probe-message', 1, 1);

        $this->attempt($rule)->fails();
        $validator = $this->attempt($rule);

        $this->assertTrue($validator->fails());
        $this->assertSame(['message' => ['Túl sok próbálkozás, kérlek várj.']], $validator->errors()->toArray());
        $this->assertSame(__('group.messages.limit'), $validator->errors()->first('message'));
        $this->assertNotSame('group.messages.limit', $validator->errors()->first('message'));
    }

    /**
     * See the note on TimeCheckTest's counterpart of this test: the key comes
     * from the rule's own class rather than from the InvokableValidationRule
     * the parser wraps it in, which is what keeps
     * assertHasErrors(['message' => Throttle::class]) working.
     */
    public function test_the_failure_is_recorded_under_the_rule_class(): void
    {
        $rule = new Throttle('probe-failed', 1, 1);

        $this->attempt($rule)->fails();
        $validator = $this->attempt($rule);
        $validator->fails();

        $this->assertSame([Throttle::class], array_keys($validator->failed()['message']));
    }

    /**
     * The counter is incremented on the PASSING branch only. If that ever
     * moved above the budget check, a blocked user would extend their own
     * lockout simply by retrying.
     */
    public function test_a_blocked_attempt_does_not_extend_the_lockout(): void
    {
        $rule = new Throttle('probe-lockout', 2, 1);

        $this->attempt($rule)->fails();
        $this->attempt($rule)->fails();

        $spent = app(RateLimiter::class)->attempts($this->limiterKey('probe-lockout'));
        $this->assertSame(2, $spent);

        $this->attempt($rule)->fails();
        $this->attempt($rule)->fails();
        $this->attempt($rule)->fails();

        $this->assertSame(
            $spent,
            app(RateLimiter::class)->attempts($this->limiterKey('probe-lockout')),
            'A blocked attempt must not be counted.'
        );
    }

    /**
     * Groups\Messages builds the key from the group id, so two groups must
     * not share one budget.
     */
    public function test_separate_keys_do_not_share_a_budget(): void
    {
        $first = new Throttle('probe-group-1', 1, 1);
        $second = new Throttle('probe-group-2', 1, 1);

        $this->assertFalse($this->attempt($first)->fails());
        $this->assertTrue($this->attempt($first)->fails());

        $this->assertFalse($this->attempt($second)->fails(), 'The second key has its own budget.');
    }

    public function test_the_rule_uses_the_current_validation_contract(): void
    {
        $rule = new Throttle('probe-contract', 1, 1);

        $this->assertInstanceOf(ValidationRule::class, $rule);
        $this->assertNotInstanceOf(DeprecatedRuleContract::class, $rule);
    }
}
