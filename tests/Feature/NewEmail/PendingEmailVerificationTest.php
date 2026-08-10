<?php

namespace Tests\Feature\NewEmail;

use App\Mail\VerifyNewEmail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 19 / 19.1: the contract of the signed activation link.
 *
 * Until TODO 33.5 the `pendingEmail.verify` route came from the package's own
 * route file, and it loaded ONLY because the `route` key in the published
 * `config/verify-new-email.php` was `null`. It belongs to the application today
 * (routes/web.php), under an unchanged name and URI.
 *
 * The three rejection paths are asserted separately because THREE DIFFERENT
 * layers produce them: signature validation (403), expiry (also 403, but for a
 * different reason), and an unknown token (302). The last one was the
 * surprising one: InvalidVerificationLinkException extended Illuminate's
 * AuthenticationException, so the framework redirected to the login page and
 * the user arrived there with NO message. The direction stayed; since TODO 33.5
 * the message arrives too.
 */
class PendingEmailVerificationTest extends FeatureTestCase
{
    private function userWithPendingEmail(string $current, string $pending): array
    {
        Mail::fake();

        $user = $this->createUser(['email' => $current]);
        $user->newEmail($pending);

        $token = DB::table('pending_user_emails')
            ->where('user_id', $user->getKey())
            ->value('token');

        return [$user, $token];
    }

    // =========================================================================
    // 1. Az aktiválás
    // =========================================================================

    public function test_a_valid_signed_link_moves_the_address_onto_the_user_and_marks_it_verified(): void
    {
        [$user, $token] = $this->userWithPendingEmail('before@example.test', 'after@example.test');

        $user->forceFill(['email_verified_at' => null])->save();

        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]))
            ->assertStatus(302);

        $fresh = $user->fresh();

        $this->assertSame('after@example.test', $fresh->email);
        $this->assertNotNull($fresh->email_verified_at, 'Az aktiválás egyben verifikál is.');
    }

    public function test_activation_dispatches_the_verified_event_and_deletes_the_pending_row(): void
    {
        Event::fake([Verified::class]);

        [$user, $token] = $this->userWithPendingEmail('event-before@example.test', 'event-after@example.test');

        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]))
            ->assertStatus(302);

        Event::assertDispatched(Verified::class, function ($event) use ($user) {
            return $event->user->is($user);
        });

        $this->assertSame(
            0,
            DB::table('pending_user_emails')->where('user_id', $user->getKey())->count(),
            'Az activate() minden azonos címre szóló sort töröl.'
        );
    }

    public function test_activation_redirects_to_the_configured_path_with_the_verified_flag(): void
    {
        [, $token] = $this->userWithPendingEmail('redir@example.test', 'redir-new@example.test');

        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]))
            ->assertRedirect(config('verify-new-email.redirect_to'))
            ->assertSessionHas('verified', true);

        $this->assertSame('/user/new-email-verified', config('verify-new-email.redirect_to'));
    }

    public function test_activation_does_not_log_the_visitor_in(): void
    {
        [, $token] = $this->userWithPendingEmail('guest@example.test', 'guest-new@example.test');

        // login_after_verification => false. A linket tipikusan MÁS eszközön
        // nyitják meg, mint ahol a kérés indult - ezért is nincs `auth` az
        // útvonalon (lásd lentebb).
        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]))
            ->assertStatus(302);

        $this->assertGuest();
        $this->assertFalse(config('verify-new-email.login_after_verification'));
    }

    public function test_the_redirect_helper_sends_a_verified_guest_to_the_login_page(): void
    {
        $this->withSession(['verified' => true])
            ->get(route('user.new-email-verified'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('verified', true);
    }

    // =========================================================================
    // 2. A három elutasítási ág
    // =========================================================================

    public function test_an_unsigned_link_is_rejected_with_403(): void
    {
        [$user, $token] = $this->userWithPendingEmail('unsigned@example.test', 'unsigned-new@example.test');

        $this->get(route('pendingEmail.verify', ['token' => $token]))
            ->assertStatus(403);

        $this->assertSame('unsigned@example.test', $user->fresh()->email);
    }

    public function test_a_tampered_signature_is_rejected_with_403(): void
    {
        [$user, $token] = $this->userWithPendingEmail('tampered@example.test', 'tampered-new@example.test');

        $url = $this->signedRoute('pendingEmail.verify', ['token' => $token]);

        $this->get($url.'0')->assertStatus(403);

        $this->assertSame('tampered@example.test', $user->fresh()->email);
    }

    public function test_an_expired_link_is_rejected_with_403(): void
    {
        [$user, $token] = $this->userWithPendingEmail('expired@example.test', 'expired-new@example.test');

        $url = $this->signedRoute('pendingEmail.verify', ['token' => $token], 60);

        // A lejárat forrása az auth.verification.expire, ami ma nincs
        // definiálva a config/auth.php-ban, tehát a 60 perces alapérték él.
        $this->assertSame(60, (int) config('auth.verification.expire', 60));

        $this->travel(61)->minutes();

        $this->get($url)->assertStatus(403);

        $this->assertSame('expired@example.test', $user->fresh()->email);

        $this->travelBack();
    }

    /** PARTLY REVERSED by TODO 33.5. */
    public function test_a_signed_link_with_an_unknown_token_redirects_to_login_with_a_message(): void
    {
        $this->userWithPendingEmail('unknown@example.test', 'unknown-new@example.test');

        // BEFORE: InvalidVerificationLinkException extended
        // AuthenticationException, so the framework's handler did the
        // redirecting - and the package's own translation key ("The
        // verification link is not valid anymore.") NEVER reached anyone,
        // because an exception message does not travel across a redirect.
        //
        // The direction is deliberately unchanged, but the message now arrives
        // and the login view renders it - see auth/login.blade.php.
        $this->get($this->signedRoute('pendingEmail.verify', ['token' => 'no-such-token']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('profile_message', __('user.newEmail.invalid_link'));
    }

    public function test_a_logged_in_visitor_with_an_unknown_token_lands_on_the_profile(): void
    {
        $user = $this->createUser(['email' => 'known-visitor@example.test']);

        $this->actingAs($user)
            ->get($this->signedRoute('pendingEmail.verify', ['token' => 'no-such-token']))
            ->assertRedirect(route('user.profile'))
            ->assertSessionHas('profile_message', __('user.newEmail.invalid_link'));
    }

    public function test_the_profile_page_renders_the_failure_message(): void
    {
        // The message must also be SHOWN - no view in this project renders a
        // flash called `error`, which is why it rides `profile_message`.
        // Without that the whole feedback would stay silent, which is exactly
        // the defect this change set fixes.
        $user = $this->createUser(['email' => 'renders@example.test']);

        $this->actingAs($user)
            ->withSession(['profile_message' => 'Ez a hibaüzenet látszik.'])
            ->get(route('user.profile'))
            ->assertStatus(200)
            ->assertSee('Ez a hibaüzenet látszik.', false);
    }

    public function test_the_login_page_renders_the_failure_message(): void
    {
        $this->withSession(['profile_message' => 'Ez a hibaüzenet is látszik.'])
            ->get(route('login'))
            ->assertStatus(200)
            ->assertSee('Ez a hibaüzenet is látszik.', false);
    }

    // =========================================================================
    // 3. A route szerződése
    // =========================================================================

    /** REVERSED by TODO 33.5. */
    public function test_the_verification_route_is_registered_by_the_application_itself(): void
    {
        // BEFORE: the route came from the package's own route file, and it
        // loaded only because the `route` key in the published config was
        // `null` - an empty configuration value was what kept the whole
        // endpoint alive. The key went away with the package.
        //
        // The name and URI are unchanged to the letter: no link already in
        // flight may be invalidated by this change set.
        $this->assertNull(config('verify-new-email.route'), 'Not a trace of the key may remain.');

        $route = Route::getRoutes()->getByName('pendingEmail.verify');

        $this->assertNotNull($route);
        $this->assertSame('pendingEmail/verify/{token}', $route->uri());
        $this->assertSame(
            'App\\Http\\Controllers\\User\\VerifyNewEmailController@verify',
            $route->getActionName()
        );

        $this->assertStringContainsString(
            "name('pendingEmail.verify')",
            file_get_contents(base_path('routes/web.php')),
            'The route lives in the application route file, not in vendor code.'
        );
    }

    public function test_the_verification_route_deliberately_carries_no_auth_middleware(): void
    {
        $middleware = Route::getRoutes()->getByName('pendingEmail.verify')->gatherMiddleware();

        // Szándékos: a linket más eszközön (vagy kijelentkezve) is meg kell
        // tudni nyitni. A védelmet az aláírás és a token adja, nem a session.
        $this->assertContains('web', $middleware);
        $this->assertContains('signed', $middleware);
        $this->assertContains('throttle:6,1', $middleware);
        $this->assertNotContains('auth', $middleware);
    }

    public function test_the_route_is_throttled_at_six_requests_per_minute(): void
    {
        $url = $this->signedRoute('pendingEmail.verify', ['token' => 'throttle-probe']);

        for ($i = 1; $i <= 6; $i++) {
            $this->get($url)->assertStatus(302, "A(z) {$i}. kérésnek még át kell mennie.");
        }

        $this->get($url)->assertStatus(429);
    }

    public function test_the_new_email_mail_body_carries_the_working_signed_link(): void
    {
        // This is what ties the Mailable to the route. The other tests measure
        // either the queueing (Mail::fake) or the link on its own - none of
        // them checks that the button URL is actually inside the rendered mail.
        //
        // Mail::fake() is DELIBERATELY absent: MailFake cannot render(), so the
        // row is created through the non-sending half of the flow.
        $user = $this->createUser([
            'email' => 'render@example.test',
            'email_verified_at' => now(),
        ]);
        $pending = $user->createPendingUserEmailModel('render-new@example.test');

        $body = html_entity_decode((new VerifyNewEmail($pending))->render());

        $this->assertStringContainsString('/pendingEmail/verify/'.$pending->token, $body);
        $this->assertStringContainsString('signature=', $body);
        $this->assertStringContainsString(__('email.verifyNewEmail.line_1'), $body);
    }

    public function test_the_model_builds_its_url_from_the_named_route(): void
    {
        [$user, $token] = $this->userWithPendingEmail('url@example.test', 'url-new@example.test');

        $pending = app(config('verify-new-email.model'))->whereToken($token)->firstOrFail();

        $url = $pending->verificationUrl();

        $this->assertStringContainsString('/pendingEmail/verify/'.$token, $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);

        // A generált URL-nek működnie kell - ez köti össze a Mailable-t a route-tal.
        $this->get($url)->assertRedirect(config('verify-new-email.redirect_to'));
        $this->assertSame('url-new@example.test', $user->fresh()->email);
    }
}
