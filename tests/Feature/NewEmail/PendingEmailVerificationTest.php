<?php

namespace Tests\Feature\NewEmail;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 19 / 19.1: az aláírt aktiváló link szerződése.
 *
 * A `pendingEmail.verify` útvonal a csomag saját route-fájljából jön
 * (vendor/.../src/routes.php), és CSAK azért töltődik be, mert a publikált
 * `config/verify-new-email.php`-ban a `route` kulcs értéke `null`. Ez a fájl
 * ezt a betöltési feltételt is rögzíti, nem csak a viselkedést.
 *
 * A három elutasítási ág külön-külön áll, mert három KÜLÖNBÖZŐ réteg adja
 * őket: az aláírás-ellenőrzés (403), a lejárat (szintén 403, de más okból), és
 * az ismeretlen token (302). Az utolsó a legmeglepőbb: az
 * InvalidVerificationLinkException az Illuminate AuthenticationException
 * leszármazottja, ezért a keretrendszer a login oldalra tereli - a felhasználó
 * hibaüzenet nélkül köt ki ott.
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

    public function test_a_signed_link_with_an_unknown_token_redirects_to_login_instead_of_showing_an_error(): void
    {
        $this->userWithPendingEmail('unknown@example.test', 'unknown-new@example.test');

        // InvalidVerificationLinkException extends AuthenticationException,
        // ezért a keretrendszer kezelője a login oldalra tereli. A csomag
        // fordítási kulcsa ("The verification link is not valid anymore.")
        // így SOSEM jut el a felhasználóhoz.
        $this->get($this->signedRoute('pendingEmail.verify', ['token' => 'no-such-token']))
            ->assertRedirect(route('login'));
    }

    // =========================================================================
    // 3. A route szerződése
    // =========================================================================

    public function test_the_verification_route_is_registered_only_because_the_config_route_key_is_null(): void
    {
        $this->assertNull(
            config('verify-new-email.route'),
            'Ha ez nem null, a csomag ServiceProvider-e NEM tölti be a saját route-fájlját.'
        );

        $this->assertNotNull(Route::getRoutes()->getByName('pendingEmail.verify'));
        $this->assertSame(
            'pendingEmail/verify/{token}',
            Route::getRoutes()->getByName('pendingEmail.verify')->uri()
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
