<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\SetGuestLanguage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 09: a SetGuestLanguage middleware.
 *
 * Egyetlen route-on ül: a signed finish_registration-ön (web.php:66). A
 * feladata, hogy a meghívott - még be nem jelentkezett - felhasználó a saját
 * nyelvén lássa a regisztráció befejezését, mielőtt egyáltalán belépne.
 *
 * Ma nulla lefedettsége van, pedig ez a SetLocale session-ágának egyetlen
 * vendég-oldali forrása: a két middleware kapcsolata sehol nincs
 * dokumentálva.
 */
class SetGuestLanguageTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('available_languages', [
            'hu' => ['name' => 'Magyar', 'visible' => true],
            'en' => ['name' => 'English', 'visible' => true],
        ]);
        Config::set('settings_default_language', 'hu');

        app()->setLocale('hu');
    }

    private function invitedUser(string $language, string $email): User
    {
        return $this->createUser([
            'email'             => $email,
            'language'          => $language,
            'role'              => 'registered',
            'email_verified_at' => null,
            'name'              => null,
        ]);
    }

    private function runMiddleware(Request $request)
    {
        return (new SetGuestLanguage())->handle($request, fn () => response('ok'));
    }

    // =========================================================================
    // 1. A valódi route
    // =========================================================================

    public function test_the_signed_invitation_link_switches_to_the_invited_users_language(): void
    {
        $user = $this->invitedUser('en', 'guest-lang-en@example.test');

        $this->get($this->signedRoute('finish_registration', ['id' => $user->id]))
            ->assertStatus(200);

        $this->assertSame('en', app()->getLocale());
        $this->assertSame('en', session('language'));
    }

    public function test_the_locale_stays_at_the_default_for_a_hungarian_invitee(): void
    {
        $user = $this->invitedUser('hu', 'guest-lang-hu@example.test');

        $this->get($this->signedRoute('finish_registration', ['id' => $user->id]))
            ->assertStatus(200);

        $this->assertSame('hu', app()->getLocale());
        $this->assertSame('hu', session('language'));
    }

    public function test_the_choice_survives_into_the_next_request_through_the_session(): void
    {
        // Itt kapcsolódik össze a két middleware: a SetGuestLanguage a
        // session-be ír, a SetLocale pedig a következő kérésen a
        // session('language') ágon (SetLocale.php:51-53) veszi elő. A vendég
        // tehát a meghívó link megnyitása után végig a saját nyelvén látja
        // az oldalt, bejelentkezés nélkül is.
        $user = $this->invitedUser('en', 'guest-lang-carry@example.test');

        $this->get($this->signedRoute('finish_registration', ['id' => $user->id]))
            ->assertStatus(200);

        app()->setLocale('hu');

        $this->get(route('static_page', ['slug' => 'home']))->assertStatus(200);

        $this->assertSame('en', app()->getLocale());
    }

    // =========================================================================
    // 2. A védőfeltételek
    // =========================================================================

    public function test_a_request_without_an_id_is_left_alone(): void
    {
        $response = $this->runMiddleware(Request::create('/valami'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame('hu', app()->getLocale());
    }

    public function test_an_unknown_id_is_left_alone(): void
    {
        // A $user !== null őr miatt egy nem létező azonosító nem okoz hibát -
        // a kérés érintetlenül halad tovább.
        $response = $this->runMiddleware(Request::create('/valami?id=999999'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame('hu', app()->getLocale());
    }

    public function test_an_anonymized_user_still_switches_the_locale(): void
    {
        // A lekérdezés csupasz User::where('id', ...), tehát semmit nem szűr
        // az azonosítón kívül. Egy GDPR-anonimizált felhasználó nyelve is
        // érvényre jut - szemben például a Group::groupUsers() relációval,
        // ami where('isAnonymized', 0)-val kizárja őket.
        //
        // Ma ártalmatlan (a nyelv nem személyes adat), de rögzítjük, mert a
        // TODO 12 GDPR-átvizsgálásakor ez az egyik olyan hely, ahol egy
        // anonimizált sor még olvasásra kerül.
        $user = $this->invitedUser('en', 'guest-lang-anon@example.test');
        $user->forceFill(['isAnonymized' => 1])->save();

        $response = $this->runMiddleware(Request::create('/valami?id='.$user->id));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame('en', app()->getLocale());
    }

    // =========================================================================
    // 3. A validáció hiánya
    // =========================================================================

    public function test_a_language_outside_the_available_list_is_applied_without_validation(): void
    {
        // KARAKTERIZÁLÓ TESZT. A SetGuestLanguage - a SetLocale ?lang=
        // ágával ellentétben - NEM ellenőrzi az available_languages-t: amit
        // a users.language oszlopban talál, azt beteszi a locale-ba és a
        // session-be.
        //
        // Következmény: egy olyan nyelv, amit az adminisztrátor időközben
        // kivett a listából (vagy sosem tett bele), így is érvényre jut, és
        // a session miatt a további kéréseken is megmarad. Fordítás
        // hiányában a kulcsok nyersen jelennek meg.
        $user = $this->invitedUser('sk', 'guest-lang-unknown@example.test');

        $this->runMiddleware(Request::create('/valami?id='.$user->id));

        $this->assertSame('sk', app()->getLocale(), 'A listán kívüli nyelv is beáll.');
        $this->assertSame('sk', session('language'));
    }

    public function test_any_id_in_the_query_string_is_accepted_not_just_the_route_parameter(): void
    {
        // A $request->id magic property előbb a route-paramétereket nézi,
        // aztán a bemenetet - a middleware tehát nem köti magát a
        // finish_registration route-hoz. Ma ez ártalmatlan, mert csak ott
        // van bekötve és az az útvonal aláírt; egy jövőbeli újrafelhasználás
        // előtt viszont tudni kell róla.
        $user = $this->invitedUser('en', 'guest-lang-query@example.test');

        $this->runMiddleware(Request::create('/barmi?id='.$user->id));

        $this->assertSame('en', app()->getLocale());
    }
}
