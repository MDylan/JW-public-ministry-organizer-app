<?php

namespace Tests\Feature\Auth;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\FeatureTestCase;

/**
 * v1-patch H: the medium- and low-priority items from the security audit that
 * are not covered by a dedicated test file.
 */
class AuthEndpointHardeningTest extends FeatureTestCase
{
    // =========================================================================
    // The second password-confirmation endpoint, WITHOUT throttling
    // =========================================================================

    public function test_the_unthrottled_fortify_confirm_password_endpoint_is_gone(): void
    {
        // Fortify's own `POST /user/confirm-password` definition lived in
        // routes/fortify.php, with `auth:web` and NO rate limiting whatsoever.
        // With a stolen session you could brute-force the user's password on
        // it without limit, while the application's own branch
        // (`password.confirm.store`) carries `throttle:6,1`.
        $user = $this->createUser(['email' => 'confirm-endpoint@example.test']);

        // 404, not 405: the matching GET route had already been commented out
        // earlier, so no definition at all remained on this URI.
        $this->actingAs($user)
            ->post('/user/confirm-password', ['password' => 'password'])
            ->assertNotFound();
    }

    public function test_the_surviving_confirm_password_endpoint_is_throttled(): void
    {
        $route = app('router')->getRoutes()->getByName('password.confirm.store');

        $this->assertNotNull($route);
        $this->assertContains('throttle:6,1', $route->gatherMiddleware());
    }

    // =========================================================================
    // The login rate-limiter's key
    // =========================================================================

    public function test_the_login_limiter_normalizes_the_email_address(): void
    {
        // The key USED TO BE the raw `email . ip` concatenation. MySQL's
        // default collation is case-insensitive, so `User@x.hu` and
        // `user@x.hu` find the SAME account - but the limiter opened two
        // separate buckets for them, so the 5/minute limit could be
        // multiplied arbitrarily using letter-case variants.
        $lower = $this->loginLimits('user@example.test');
        $mixed = $this->loginLimits('  User@Example.TEST  ');

        $this->assertSame($lower[0]->key, $mixed[0]->key);
    }

    public function test_the_login_limiter_separates_the_email_from_the_ip(): void
    {
        // Without a separator, `bob@x.hu` + `1.2.3.41` and `bob@x.hu1` +
        // `.2.3.41` produced the same key.
        $this->assertStringContainsString('|', $this->loginLimits('bob@example.test')[0]->key);
    }

    public function test_the_login_limiter_also_caps_a_single_ip(): void
    {
        // Against email-rotation attempts: 20 attempts per minute are allowed
        // from a single IP, with any number of different addresses.
        // Previously every new address got a new bucket, so the IP had no
        // upper bound.
        $limits = $this->loginLimits('bob@example.test');

        $this->assertCount(2, $limits);
        $this->assertSame(5, $limits[0]->maxAttempts);
        $this->assertSame(20, $limits[1]->maxAttempts);
    }

    /**
     * @return Limit[]
     */
    private function loginLimits(string $email): array
    {
        $limits = RateLimiter::limiter('login')(
            Request::create('/login', 'POST', ['email' => $email])
        );

        return is_array($limits) ? array_values($limits) : [$limits];
    }

    // =========================================================================
    // CRLF in the email field of the mail-sending endpoints
    // =========================================================================

    /**
     * @dataProvider crlfPayloads
     */
    public function test_a_crlf_payload_is_rejected_on_the_forgot_password_endpoint(string $payload): void
    {
        // Laravel 8's default `email` rule uses RFCValidation, which ACCEPTS
        // CR/LF in the address (GHSA-5vg9-5847-vvmq, high). From there the
        // address ends up in a mail header, where the line break opens a new
        // header: the attacker can influence the mail's contents, have it
        // delivered to a different recipient, or coerce the mailer into
        // sending unrelated messages. The fix only exists in 12.60.0, there
        // is no backport for Laravel 8 - that's why the `strictEmail`
        // middleware sits in front of the two vendor controllers.
        //
        // This endpoint is ANONYMOUS, and sends mail to the given address.
        $this->post(route('password.email'), ['email' => $payload])
            ->assertSessionHasErrors('email');
    }

    /**
     * @dataProvider crlfPayloads
     */
    public function test_a_crlf_payload_is_rejected_on_the_reset_password_endpoint(string $payload): void
    {
        $this->post(route('password.update'), [
            'token' => 'barmi',
            'email' => $payload,
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
        ])->assertSessionHasErrors('email');
    }

    public function crlfPayloads(): array
    {
        return [
            'CRLF' => ["valaki@example.test\r\nBcc: aldozat@example.test"],
            'LF'   => ["valaki@example.test\nBcc: aldozat@example.test"],
            'CR'   => ["valaki@example.test\rBcc: aldozat@example.test"],
        ];
    }

    public function test_a_well_formed_address_still_gets_through_to_the_controller(): void
    {
        // The middleware must not be stricter than necessary: a well-formed
        // address still reaches Fortify's controller unchanged.
        $user = $this->createUser(['email' => 'reset-me@example.test']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();
    }

    public function test_the_two_vendor_email_endpoints_declare_the_strict_rule(): void
    {
        // Both controllers live in the vendor directory and validate
        // `required|email`, so the rule cannot be edited there permanently -
        // the next `composer update` would overwrite it. The protection holds
        // only as long as this middleware remains on the route.
        foreach (['password.email', 'password.update'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, $name);
            $this->assertContains('strictEmail', $route->gatherMiddleware(), $name);
        }
    }

    public function test_the_registration_flows_reject_a_crlf_address(): void
    {
        // The application's own validations use `email:filter`, which works
        // via `filter_var(FILTER_VALIDATE_EMAIL)` and rejects CRLF outright.
        // This is proven through the endpoint, not by reading the source -
        // the difference between `filter` and the default `rfc` is exactly
        // what doesn't show when reading the code.
        $this->post(route('register'), [
            'name' => 'Teszt Elek',
            'email' => "valaki@example.test\r\nBcc: aldozat@example.test",
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
            'terms' => 1,
        ])->assertSessionHasErrors('email');
    }

    public function test_the_mail_setup_request_uses_the_filter_variant(): void
    {
        // MAIL_FROM_ADDRESS ends up in the From HEADER. The installer lives in
        // the `setup/*` group, behind an installer token, so the attack
        // surface is narrow - but the field is still a header value.
        $rules = (new \App\Http\Requests\SetupMailRequest())->rules();

        $this->assertStringContainsString('email:filter', $rules['MAIL_FROM_ADDRESS']);
    }

    // =========================================================================
    // GET /email/verify
    // =========================================================================

    public function test_the_verification_notice_page_requires_authentication(): void
    {
        // `auth` was PREVIOUSLY missing. No authorization bypass resulted from
        // it, but the view uses `<x-admin-layout>`, which dereferences
        // `auth()->user()` for a guest: every logged-out visit produced a 500
        // and a stack trace in the log.
        $this->get(route('verification.notice'))->assertRedirect(route('login'));
    }

    public function test_the_verification_notice_page_still_renders_for_a_logged_in_user(): void
    {
        $user = $this->createUser([
            'email' => 'verify-notice@example.test',
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertStatus(200);
    }
}
