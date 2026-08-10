<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\SetGuestLanguage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 09: the SetGuestLanguage middleware.
 *
 * It sits on a single route: the signed finish_registration (web.php:66). Its
 * job is to let the invited - not yet logged-in - user see the completion of
 * registration in their own language, before they even log in.
 *
 * It has zero coverage today, even though this is the only guest-side source
 * of SetLocale's session branch: the connection between the two middlewares
 * is documented nowhere.
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
    // 1. The real route
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
        // This is where the two middlewares connect: SetGuestLanguage writes
        // into the session, and SetLocale picks it up on the next request via
        // the session('language') branch (SetLocale.php:51-53). The guest
        // therefore sees the site in their own language throughout, after
        // opening the invitation link, even without logging in.
        $user = $this->invitedUser('en', 'guest-lang-carry@example.test');

        $this->get($this->signedRoute('finish_registration', ['id' => $user->id]))
            ->assertStatus(200);

        app()->setLocale('hu');

        $this->get(route('static_page', ['slug' => 'home']))->assertStatus(200);

        $this->assertSame('en', app()->getLocale());
    }

    // =========================================================================
    // 2. The guard conditions
    // =========================================================================

    public function test_a_request_without_an_id_is_left_alone(): void
    {
        $response = $this->runMiddleware(Request::create('/valami'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame('hu', app()->getLocale());
    }

    public function test_an_unknown_id_is_left_alone(): void
    {
        // Because of the $user !== null guard, a non-existent id does not
        // cause an error - the request passes through untouched.
        $response = $this->runMiddleware(Request::create('/valami?id=999999'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame('hu', app()->getLocale());
    }

    public function test_an_anonymized_user_still_switches_the_locale(): void
    {
        // The query is a bare User::where('id', ...), so it filters nothing
        // besides the id. An anonymized GDPR user's language also takes
        // effect - unlike, for example, the Group::groupUsers() relation,
        // which excludes them with where('isAnonymized', 0).
        //
        // Harmless today (language is not personal data), but we record it
        // because it is one of the places, during the TODO 12 GDPR review,
        // where an anonymized row still gets read.
        $user = $this->invitedUser('en', 'guest-lang-anon@example.test');
        $user->forceFill(['isAnonymized' => 1])->save();

        $response = $this->runMiddleware(Request::create('/valami?id='.$user->id));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame('en', app()->getLocale());
    }

    // =========================================================================
    // 3. The absence of validation
    // =========================================================================

    public function test_a_language_outside_the_available_list_is_applied_without_validation(): void
    {
        // CHARACTERIZATION TEST. SetGuestLanguage - unlike SetLocale's
        // ?lang= branch - does NOT check available_languages: whatever it
        // finds in the users.language column, it puts into the locale and
        // the session.
        //
        // Consequence: a language that the administrator has since removed
        // from the list (or never added), still takes effect, and persists
        // across further requests because of the session. In the absence of
        // a translation, the keys appear raw.
        $user = $this->invitedUser('sk', 'guest-lang-unknown@example.test');

        $this->runMiddleware(Request::create('/valami?id='.$user->id));

        $this->assertSame('sk', app()->getLocale(), 'A listán kívüli nyelv is beáll.');
        $this->assertSame('sk', session('language'));
    }

    public function test_any_id_in_the_query_string_is_accepted_not_just_the_route_parameter(): void
    {
        // The $request->id magic property looks at the route parameters
        // first, then the input - so the middleware does not bind itself to
        // the finish_registration route. Harmless today, because it is only
        // wired up there and that route is signed; but this needs to be
        // known before any future reuse.
        $user = $this->invitedUser('en', 'guest-lang-query@example.test');

        $this->runMiddleware(Request::create('/barmi?id='.$user->id));

        $this->assertSame('en', app()->getLocale());
    }
}
