<?php

namespace Tests\Feature\Mail;

use Illuminate\Support\Env;
use Spatie\FailedJobMonitor\Notifiable;
use Tests\TestCase;
use TypeError;

/**
 * TODO 36: re-verifying spatie/laravel-failed-job-monitor, which the roadmap
 * entry asks for because it is wired to queued notifications.
 *
 * The re-verification found a real defect, and it is not in the mail layer at
 * all - it fires before Symfony Mailer is ever reached:
 *
 *     // vendor/spatie/laravel-failed-job-monitor/src/Notifiable.php
 *     public function routeNotificationForMail(): array
 *     {
 *         $recipients = config('failed-job-monitor.mail.to');
 *         if (is_string($recipients)) { return [$recipients]; }
 *         return $recipients;                 // null -> TypeError
 *     }
 *
 * config/failed-job-monitor.php feeds that from env('MAIL_FROM_ADDRESS', ...),
 * and an env() default only applies when the KEY IS ABSENT. .env.example ships
 * MAIL_FROM_ADDRESS=null, which env() turns into a real null (Env.php:88), so
 * the default never runs. On such an install a failed queue job takes down the
 * thing whose whole job is to report failed queue jobs, silently.
 *
 * The vendor behaviour is not something this repository can change, so the
 * cases below pin it as a constraint: the configured value must never be null.
 */
class FailedJobMonitorRouteTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup = [];

    protected function tearDown(): void
    {
        foreach ($this->serverBackup as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }

        parent::tearDown();
    }

    // =========================================================================
    // 1. The vendor constraint
    // =========================================================================

    public function test_the_vendor_route_wraps_a_string_recipient_in_a_list(): void
    {
        config()->set('failed-job-monitor.mail.to', 'hibak@example.test');

        $this->assertSame(['hibak@example.test'], (new Notifiable())->routeNotificationForMail());
    }

    public function test_the_vendor_route_dies_on_a_null_recipient(): void
    {
        // This is the constraint, not a bug report against the package: its
        // return type is array and it passes the config value through
        // untouched. Nothing downstream gets a chance to handle it, which is
        // why the fix has to be that the configured value is never null.
        config()->set('failed-job-monitor.mail.to', null);

        $this->expectException(TypeError::class);

        (new Notifiable())->routeNotificationForMail();
    }

    // =========================================================================
    // 2. Why null is reachable at all
    // =========================================================================

    public function test_env_turns_the_literal_null_string_into_a_real_null(): void
    {
        // Env.php:88. This is the whole mechanism: a .env line reading
        // MAIL_FROM_ADDRESS=null is not the string "null", it is null - so the
        // second argument of env() never gets a turn.
        $this->putServer('TODO36_PROBE', 'null');

        $this->assertNull(Env::get('TODO36_PROBE', 'a-default-that-never-applies'));
        $this->assertSame('a-default-that-never-applies', Env::get('TODO36_PROBE_ABSENT', 'a-default-that-never-applies'));
    }

    // =========================================================================
    // 3. What the configuration file actually produces
    // =========================================================================

    public function test_a_null_env_value_currently_leaves_the_recipient_null(): void
    {
        // THE DEFECT, recorded as it stands rather than described. The config
        // file is re-evaluated here rather than read through config(), because
        // config() only ever shows the value this process booted with;
        // re-requiring runs the env() call again against the environment this
        // test controls.
        //
        // Combined with the vendor case above, this is a complete failure path:
        // .env carries MAIL_FROM_ADDRESS=null -> the configured recipient is
        // null -> the first failed queue job raises a TypeError instead of a
        // notification. The assertion flips when config/failed-job-monitor.php
        // is fixed, so the fix has to prove itself here.
        $this->putServer('MAIL_FROM_ADDRESS', 'null');

        $config = require base_path('config/failed-job-monitor.php');

        $this->assertNull(
            $config['mail']['to'],
            'A hibafigyelő címzettje már nem null - ha ez elbukik, a javítás megtörtént és az assertion megfordítandó.'
        );
    }

    public function test_a_real_address_still_wins_over_the_fallback(): void
    {
        $this->putServer('MAIL_FROM_ADDRESS', 'uzemeltetes@example.test');

        $config = require base_path('config/failed-job-monitor.php');

        $this->assertSame('uzemeltetes@example.test', $config['mail']['to']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Set an environment value for this test only.
     *
     * Env reads through adapters that look at $_SERVER live, so writing there
     * is enough and there is nothing to flush. tearDown puts back whatever was
     * there before, including "nothing".
     */
    private function putServer(string $key, string $value): void
    {
        if (! array_key_exists($key, $this->serverBackup)) {
            $this->serverBackup[$key] = $_SERVER[$key] ?? null;
        }

        $_SERVER[$key] = $value;
    }
}
