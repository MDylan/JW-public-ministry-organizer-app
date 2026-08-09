<?php

namespace Tests\Feature\Gdpr;

use App\Http\Middleware\RedirectIfUnansweredTerms;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12: a RedirectIfUnansweredTerms - létező, de sehol nem regisztrált
 * middleware.
 *
 * A Dialect csomag publikálja az app/Http/Middleware alá, a Kernel viszont nem
 * ismeri. A TODO 16 (a csomag sorsa) szempontjából számít, hogy mit örökölne,
 * aki aktiválja - ezért itt közvetlen hívással mérjük, nem route-on át.
 */
class UnansweredTermsMiddlewareTest extends FeatureTestCase
{
    private function passThrough(RedirectIfUnansweredTerms $middleware)
    {
        return $middleware->handle(Request::create('/home'), fn () => new Response('tovabb'));
    }

    public function test_it_is_not_registered_anywhere(): void
    {
        // Ha ez a teszt elhasal, valaki aktiválta a middleware-t - és akkor az
        // alábbi vendég-ág élesben is elérhetővé válik.
        $kernel = app(\App\Http\Kernel::class);

        $reflection = new \ReflectionClass($kernel);

        $groups = $reflection->getProperty('middlewareGroups');
        $groups->setAccessible(true);

        $aliases = $reflection->hasProperty('routeMiddleware')
            ? $reflection->getProperty('routeMiddleware')
            : $reflection->getProperty('middlewareAliases');
        $aliases->setAccessible(true);

        $registered = json_encode([
            $groups->getValue($kernel),
            $aliases->getValue($kernel),
        ]);

        $this->assertStringNotContainsString('RedirectIfUnansweredTerms', $registered);
    }

    public function test_an_unanswered_user_is_redirected_to_the_terms(): void
    {
        $user = $this->createUser(['email' => 'unanswered@example.test', 'accepted_gdpr' => null]);
        $this->actingAs($user);

        $response = $this->passThrough(new RedirectIfUnansweredTerms());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('gdpr-terms'), $response->headers->get('Location'));
    }

    public function test_an_answered_user_passes_through(): void
    {
        // Bármelyik válasz megteszi: az elutasítás (false) ugyanúgy "válasz",
        // mint az elfogadás - a feltétel csak a null-t nézi.
        foreach ([true, false] as $answer) {
            $user = $this->createUser([
                'email' => 'answered-'.var_export($answer, true).'@example.test',
                'accepted_gdpr' => $answer,
            ]);
            $this->actingAs($user);

            $this->assertSame('tovabb', $this->passThrough(new RedirectIfUnansweredTerms())->getContent());
        }
    }

    public function test_a_guest_hits_a_fatal_error(): void
    {
        // Ugyanaz a hibacsalád, mint a TODO 11.1 és 11.2: az Auth::user()
        // olvasása null-ellenőrzés nélkül. Ma nem érhető el, mert a
        // middleware nincs regisztrálva - de aki bekapcsolja a 'web' csoportba,
        // az minden vendég-kérésre 500-at kap.
        $this->expectException(\ErrorException::class);

        $this->passThrough(new RedirectIfUnansweredTerms());
    }
}
