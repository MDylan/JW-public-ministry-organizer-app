<?php

namespace App\Rules;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

class Throttle implements ValidationRule
{
   /**
     * The throttle key.
     *
     * @var string
     */
    protected $key = 'validation';

    /**
     * The maximum number of attempts a user can perform.
     *
     * @var int
     */
    protected $maxAttempts = 5;

    /**
     * The amount of minutes to restrict the requests by.
     *
     * @var int
     */
    protected $decayInMinutes = 10;

    /**
     * Create a new rule instance.
     *
     * @param string $key
     * @param int    $maxAttempts
     * @param int    $decayInMinutes
     *
     * @return void
     */
    public function __construct($key = 'validation', $maxAttempts = 5, $decayInMinutes = 10)
    {
        $this->key = $key;
        $this->maxAttempts = $maxAttempts;
        $this->decayInMinutes = $decayInMinutes;
    }

    /**
     * Run the validation rule.
     *
     * THE ATTEMPT COUNTER IS INCREMENTED HERE, as a side effect of a passing
     * check, exactly as it was under the `passes()` this replaced. That is
     * what makes this a throttle rather than a predicate: no caller has to
     * record the attempt itself. Once the budget is spent the counter is
     * deliberately NOT incremented again, so a blocked user cannot extend
     * their own lockout by retrying.
     *
     * `->translate()` IS LOAD-BEARING. `$fail('group.messages.limit')` on its
     * own puts the raw key into the error bag - PotentiallyTranslatedString
     * returns the string it was given when no translation was requested - and
     * the form would render `group.messages.limit` to the user with nothing
     * anywhere reporting a problem. ThrottleTest asserts the resolved
     * sentence for exactly that reason.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->hasTooManyAttempts()) {
            $fail('group.messages.limit')->translate();

            return;
        }

        $this->incrementAttempts();
    }

    /**
     * Determine if the user has too many failed attempts.
     *
     * @return bool
     */
    protected function hasTooManyAttempts()
    {
        return $this->limiter()->tooManyAttempts(
            $this->throttleKey(), $this->maxAttempts
        );
    }

    /**
     * Increment the attempts for the user.
     *
     * @return void
     */
    protected function incrementAttempts()
    {
        $this->limiter()->hit(
            $this->throttleKey(), $this->decayInMinutes * 60
        );
    }

    /**
     * Get the throttle key for the given request.
     *
     * @return string
     */
    protected function throttleKey()
    {
        return $this->key . '|' . $this->request()->ip();
    }

    /**
     * Get the rate limiter instance.
     *
     * @return \Illuminate\Cache\RateLimiter
     */
    protected function limiter()
    {
        return app(RateLimiter::class);
    }

    /**
     * Get the current HTTP request.
     *
     * @return \Illuminate\Http\Request
     */
    protected function request()
    {
        return app(Request::class);
    }
}
