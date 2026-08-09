<?php

namespace Tests\Feature\Auth;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\FeatureTestCase;

/**
 * v1-patch H: a biztonsági audit közepes és alacsony prioritású tételei, amiket
 * nem fed külön tesztfájl.
 */
class AuthEndpointHardeningTest extends FeatureTestCase
{
    // =========================================================================
    // A második, throttle NÉLKÜLI jelszó-megerősítő végpont
    // =========================================================================

    public function test_the_unthrottled_fortify_confirm_password_endpoint_is_gone(): void
    {
        // A routes/fortify.php-ban a Fortify saját `POST /user/confirm-password`
        // definíciója élt, `auth:web`-bel és SEMMILYEN sebességkorláttal. Egy
        // ellopott munkamenettel korlátlanul lehetett rajta a felhasználó
        // jelszavát próbálgatni, miközben az alkalmazás saját ága (a
        // `password.confirm.store`) `throttle:6,1`-et visel.
        $user = $this->createUser(['email' => 'confirm-endpoint@example.test']);

        // 404, nem 405: a hozzá tartozó GET pár már korábban ki volt
        // kommentelve, tehát ezen az URI-n egyetlen definíció sem maradt.
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
    // A bejelentkezési sebességkorlát kulcsa
    // =========================================================================

    public function test_the_login_limiter_normalizes_the_email_address(): void
    {
        // A kulcs KORÁBBAN a nyers `email . ip` összefűzés volt. A MySQL
        // alapértelmezett collationje kis-/nagybetűre érzéketlen, tehát a
        // `User@x.hu` és a `user@x.hu` UGYANAZT a fiókot találja meg - a
        // limiter viszont két külön vödröt nyitott nekik, így az 5/perc korlát
        // a betűváltozatokkal tetszőlegesen sokszorozható volt.
        $lower = $this->loginLimits('user@example.test');
        $mixed = $this->loginLimits('  User@Example.TEST  ');

        $this->assertSame($lower[0]->key, $mixed[0]->key);
    }

    public function test_the_login_limiter_separates_the_email_from_the_ip(): void
    {
        // Elválasztó nélkül a `bob@x.hu` + `1.2.3.41` és a `bob@x.hu1` +
        // `.2.3.41` ugyanazt a kulcsot adta.
        $this->assertStringContainsString('|', $this->loginLimits('bob@example.test')[0]->key);
    }

    public function test_the_login_limiter_also_caps_a_single_ip(): void
    {
        // Az e-mail-forgatásos próbálkozás ellen: egy IP-ről percenként 20
        // kísérlet mehet, akárhány különböző címmel. Korábban minden új cím új
        // vödröt kapott, tehát az IP-nek nem volt felső határa.
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
    // GET /email/verify
    // =========================================================================

    public function test_the_verification_notice_page_requires_authentication(): void
    {
        // Az `auth` KORÁBBAN hiányzott. Jogosultságmegkerülés nem látszott
        // belőle, de a nézet `<x-admin-layout>`-ot használ, ami vendégnél
        // `auth()->user()`-t dereferál: minden kijelentkezett látogatás 500-at
        // és egy stack trace-t adott a naplóba.
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
