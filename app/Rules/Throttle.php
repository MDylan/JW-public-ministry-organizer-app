<?php

namespace App\Rules;

use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Validation\Rule;

class Throttle implements Rule
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
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($this->hasTooManyAttempts()) {
            return false;
        }

        $this->incrementAttempts();

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('group.messages.limit');
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
