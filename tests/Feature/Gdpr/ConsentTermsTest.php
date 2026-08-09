<?php

namespace Tests\Feature\Gdpr;

use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12: a GDPR-hozzájárulás felülete - és hogy miért nem működik.
 *
 * A funkció három darabból állna: a RedirectIfUnansweredTerms middleware
 * terelné oda a választ még nem adó felhasználót, a gdpr-terms oldal mutatná a
 * szöveget, a két POST pedig rögzítené a döntést. Ebből
 *
 *   - a middleware NINCS regisztrálva (UnansweredTermsMiddlewareTest),
 *   - az oldal 500-zal elszáll,
 *   - a szövege máig a csomag Lorem ipsuma,
 *
 * a két POST viszont hibátlanul működik. A funkció tehát nem elromlott, hanem
 * soha nem lett befejezve - fontos bemenet a TODO 16 csomagdöntéséhez.
 *
 * Ezt eddig egy assertContains($status, [200, 500]) takarta el a
 * RouteAdditionalBehaviorRegressionTest-ben.
 */
class ConsentTermsTest extends FeatureTestCase
{
    private function user(?bool $accepted = null): User
    {
        return $this->createUser([
            'email' => 'consent@example.test',
            'accepted_gdpr' => $accepted,
        ]);
    }

    public function test_the_terms_page_is_broken(): void
    {
        $this->actingAs($this->user())
            ->get(route('gdpr-terms'))
            ->assertStatus(500);
    }

    public function test_the_reason_is_a_missing_layout(): void
    {
        // A resources/views/gdpr/message.blade.php a csomag publikált nézete,
        // és @extends('base')-t ír - 'base' nevű layout viszont nincs a
        // projektben. A csomag saját példa-elrendezésére hivatkozik.
        $this->withoutExceptionHandling();

        // A kivétel osztályára szándékosan nem állítunk: a Laravel 8-on az
        // Ignition Facade\Ignition\Exceptions\ViewException-be csomagolja, ami
        // a Phase 5 után eltűnik. Az üzenet viszont marad.
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('View [base] not found.');

        $this->actingAs($this->user())->get(route('gdpr-terms'));
    }

    public function test_nothing_in_the_application_links_to_the_terms_page(): void
    {
        // Ezért nem tűnt fel: az oldalra egyedül a nem regisztrált middleware
        // irányítana. Kézzel beírt URL-lel érhető csak el.
        $this->assertSame(
            0,
            $this->countReferencesOutsideTheViewItself(),
            'Ha ez megváltozik, a törött oldal elérhetővé vált a felületről.'
        );
    }

    private function countReferencesOutsideTheViewItself(): int
    {
        $hits = 0;

        foreach ([resource_path('views'), app_path()] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());

                if (str_contains($path, 'views/gdpr/message.blade.php')
                    || str_contains($path, 'Middleware/RedirectIfUnansweredTerms.php')) {
                    continue;
                }

                if (str_contains(file_get_contents($file->getPathname()), "'gdpr-terms'")) {
                    $hits++;
                }
            }
        }

        return $hits;
    }

    // =========================================================================
    // A működő fele
    // =========================================================================

    public function test_accepting_and_denying_both_persist(): void
    {
        // A két POST nem renderel nézetet, ezért nem érinti a hiányzó layout.
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('gdpr-terms-accepted'))
            ->assertRedirect('/');

        $this->assertTrue((bool) User::find($user->id)->accepted_gdpr);

        $this->actingAs($user)
            ->post(route('gdpr-terms-denied'))
            ->assertRedirect('/');

        $this->assertFalse((bool) User::find($user->id)->accepted_gdpr);
    }

    public function test_denying_has_no_further_consequence(): void
    {
        // KARAKTERIZÁLÁS: az elutasítás csak egy oszlopot ír át. Nem zárja ki a
        // felhasználót, nem indít törlést, nem korlátoz semmit - a döntésnek ma
        // nincs következménye.
        $user = $this->user();

        $this->actingAs($user)->post(route('gdpr-terms-denied'));

        $fresh = User::find($user->id);

        $this->assertSame(0, (int) $fresh->isAnonymized);
        $this->assertSame('consent@example.test', $fresh->email);
        $this->actingAs($fresh)->get(route('home.home'))->assertStatus(200);
    }

    public function test_all_four_routes_require_authentication(): void
    {
        $this->get(route('gdpr-terms'))->assertRedirect(route('login'));
        $this->post(route('gdpr-terms-accepted'))->assertRedirect(route('login'));
        $this->post(route('gdpr-terms-denied'))->assertRedirect(route('login'));
        $this->post(route('gdpr-download'), ['password' => 'x'])->assertRedirect(route('login'));
    }
}
