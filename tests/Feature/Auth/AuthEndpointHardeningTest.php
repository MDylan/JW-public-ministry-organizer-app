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
    // CRLF a levelet küldő végpontok e-mail mezőjében
    // =========================================================================

    /**
     * @dataProvider crlfPayloads
     */
    public function test_a_crlf_payload_is_rejected_on_the_forgot_password_endpoint(string $payload): void
    {
        // A Laravel 8 alapértelmezett `email` szabálya RFCValidation-t használ,
        // ami ELFOGADJA a CR/LF-et a címben (GHSA-5vg9-5847-vvmq, high). Onnan a
        // cím levélfejlécbe kerül, ahol a sortörés új fejlécet nyit: a támadó
        // befolyásolhatja a levél tartalmát, más címzettnek kézbesíttetheti,
        // vagy a levelezőt idegen üzenetek küldésére bírhatja. A javítás csak a
        // 12.60.0-ban van meg, Laravel 8-ra nincs backport - ezért ül a
        // `strictEmail` middleware a két vendor-controller előtt.
        //
        // Ez a végpont ANONIM, és a megadott címre levelet küld.
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
        // A middleware nem szigoríthat a kelleténél jobban: egy szabályos cím
        // változatlanul eljut a Fortify controlleréig.
        $user = $this->createUser(['email' => 'reset-me@example.test']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();
    }

    public function test_the_two_vendor_email_endpoints_declare_the_strict_rule(): void
    {
        // Mindkét controller a vendorban él és `required|email`-t validál, tehát
        // a szabály ott nem szerkeszthető maradandóan - a következő
        // `composer update` felülírná. A védelem kizárólag addig áll, amíg ez a
        // middleware a route-on van.
        foreach (['password.email', 'password.update'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, $name);
            $this->assertContains('strictEmail', $route->gatherMiddleware(), $name);
        }
    }

    public function test_the_registration_flows_reject_a_crlf_address(): void
    {
        // Az alkalmazás saját validációi `email:filter`-t használnak, ami
        // `filter_var(FILTER_VALIDATE_EMAIL)`-lel dolgozik és a CRLF-et eleve
        // elutasítja. Ez a végponton keresztül igazolja, nem a forrás
        // olvasásával - a `filter` és az alapértelmezett `rfc` közti különbség
        // épp az, ami a kódot olvasva nem látszik.
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
        // A MAIL_FROM_ADDRESS a From FEJLÉCBE kerül. A telepítő a
        // `setup/*` csoportban, installer-token mögött él, tehát a támadási
        // felület szűk - de a mező akkor is fejlécérték.
        $rules = (new \App\Http\Requests\SetupMailRequest())->rules();

        $this->assertStringContainsString('email:filter', $rules['MAIL_FROM_ADDRESS']);
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
