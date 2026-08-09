<?php

namespace Tests\Feature\NewEmail;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 19 / 19.1: a `pending_user_emails` mért hiányosságai.
 *
 * FIGYELEM - EZ A FÁJL SZÁNDÉKOSAN A HIBÁS VISELKEDÉST RÖGZÍTI. Ugyanaz a
 * fegyelem, amit a TODO 14 alkalmazott a duplikált route-nevekre, és amit a
 * TODO 26 szándékosan átír: az állítás a MAI állapot, és a javítás pillanatában
 * ezeknek BUKNIUK KELL. Az a bukás a reviewálható diff, nem regresszió.
 *
 * A `pending_user_emails` táblát senki nem takarítja: nincs idegen kulcs, nincs
 * observer (az app/Observers/ nyolc observere közül egyik sem érinti), és a
 * User::anonymize() sem hívja a trait clearPendingEmail() metódusát.
 *
 * A javítás helye:
 *   1-3. TODO 33.2 (a GDPR-csomag in-house cseréje, Phase 3) - ott már úgyis
 *        a User::anonymize()-hoz nyúlunk, és a javítás független attól, mi lesz
 *        a protonemedia csomag sorsa.
 *   4-5. A TODO 19 döntésének végrehajtása.
 */
class PendingEmailKnownGapsTest extends FeatureTestCase
{
    private function userWithPendingEmail(string $current, string $pending): array
    {
        Mail::fake();

        $user = $this->createUser([
            'email' => $current,
            'role' => 'registered',
            'isAnonymized' => 0,
        ]);
        $user->newEmail($pending);

        $token = DB::table('pending_user_emails')
            ->where('user_id', $user->getKey())
            ->value('token');

        return [$user, $token];
    }

    // =========================================================================
    // 1. GDPR: az anonimizálás nem takarít
    // =========================================================================

    /** MEGFORDÍTVA a v1-patch B12 javításával. */
    public function test_anonymisation_clears_the_real_address_from_the_pending_table(): void
    {
        // A függő cím nem anonimizálódott magától: a pending_user_emails sor
        // külön táblában áll, nincs rá idegen kulcs, és a nyolc observer egyike
        // sem nyúlt hozzá. A users.email lecserélődött, miközben a felhasználó
        // VALÓDI címe határozatlan ideig bennmaradt a függő táblában -
        // pontosan az az adat, aminek a törlését kérte.
        [$user] = $this->userWithPendingEmail('gdpr-old@example.test', 'gdpr-wanted@example.test');

        $user->anonymize();

        $this->assertSame(1, (int) $user->fresh()->isAnonymized, 'Az anonimizálás lefutott.');

        $this->assertNull(
            DB::table('pending_user_emails')->where('user_id', $user->getKey())->value('email'),
            'A függő cím nem maradhat hátra.'
        );
    }

    public function test_a_blocked_anonymisation_leaves_the_pending_address_alone(): void
    {
        // Kontroll-kísérlet: a takarítás az anonimizálás RÉSZE, nem előfeltétele.
        // Ha az utódlási szabály (TODO 12.2) elutasítja a kérést, semmi nem
        // történhet - a függő cím sem tűnhet el.
        [$user] = $this->userWithPendingEmail('gdpr-kept@example.test', 'gdpr-kept-new@example.test');

        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'admin');

        $this->assertFalse($user->fresh()->anonymize(), 'Az egyetlen adminisztrátor nem anonimizálható.');

        $this->assertSame(
            'gdpr-kept-new@example.test',
            DB::table('pending_user_emails')->where('user_id', $user->getKey())->value('email')
        );
    }

    /** MEGFORDÍTVA a v1-patch B12/B13 javításával. */
    public function test_a_live_link_can_no_longer_reverse_an_anonymisation(): void
    {
        // A kiküldött aláírt link a lejáratáig élt, a vendor
        // PendingUserEmail::activate() pedig nem nézett rá az isAnonymized
        // jelzőre: VISSZAÍRTA a valódi címet az anonimizált felhasználóra,
        // ráadásul verifikáltnak jelölve. Az anonimizálás így visszafordítható
        // volt egy e-mailben ülő linkkel.
        //
        // A B12 óta a sor már az anonimizáláskor eltűnik, tehát a token sem
        // található - a link az érvénytelen-link ágra fut.
        [$user, $token] = $this->userWithPendingEmail('gdpr-live-old@example.test', 'gdpr-live-wanted@example.test');

        $user->anonymize();

        $anonymizedEmail = $user->fresh()->email;
        $this->assertNotSame('gdpr-live-wanted@example.test', $anonymizedEmail);

        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]))
            ->assertRedirect(route('login'));

        $fresh = $user->fresh();

        $this->assertSame($anonymizedEmail, $fresh->email, 'A valódi cím nem kerülhet vissza.');
        $this->assertSame(1, (int) $fresh->isAnonymized);
    }

    public function test_the_model_guard_holds_even_if_the_pending_row_survives(): void
    {
        // A B13 önálló bizonyítéka. A B12 takarítása az egyik védelem, de nem az
        // egyetlen szükséges: egy sor túlélhet a takarítást (párhuzamos kérés,
        // régi adat, jövőbeli másik anonimizáló útvonal). Ezért a modell maga is
        // őrködik - ezt a sort ITT szándékosan az anonimizálás UTÁN hozzuk
        // vissza, hogy a guard önmagában legyen mérve.
        [$user, $token] = $this->userWithPendingEmail('gdpr-race-old@example.test', 'gdpr-race-new@example.test');

        $row = DB::table('pending_user_emails')->where('user_id', $user->getKey())->first();

        $user->anonymize();
        $anonymizedEmail = $user->fresh()->email;

        // A sor "visszatér", ahogy egy versenyhelyzetben is tenné.
        DB::table('pending_user_emails')->insert((array) $row);

        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]))
            ->assertRedirect(config('verify-new-email.redirect_to'));

        $fresh = $user->fresh();

        $this->assertSame($anonymizedEmail, $fresh->email, 'Az activate() nem írhat anonimizált felhasználóra.');
        $this->assertSame(1, (int) $fresh->isAnonymized);

        $this->assertNull(
            DB::table('pending_user_emails')->where('user_id', $user->getKey())->value('email'),
            'És a sort el is dobja, hogy a valódi cím ne maradjon bent.'
        );
    }

    /** HIBA - a javítás után ennek buknia kell. Javítás: TODO 33.2. */
    public function test_defect_deleting_a_user_orphans_the_pending_row(): void
    {
        [$user] = $this->userWithPendingEmail('deleted@example.test', 'deleted-wanted@example.test');

        $userId = $user->getKey();
        $user->delete();

        $this->assertNull(User::find($userId));
        $this->assertSame(
            1,
            DB::table('pending_user_emails')->where('user_id', $userId)->count(),
            'A morphs() nem hoz létre idegen kulcsot, és nincs observer sem - a sor árván marad.'
        );
    }

    // =========================================================================
    // 2. Az ütközés, amit senki nem kezel
    // =========================================================================

    /** HIBA - a csere során kezelendő. */
    public function test_defect_activation_fatals_when_the_address_was_taken_in_the_meantime(): void
    {
        [, $token] = $this->userWithPendingEmail('race-old@example.test', 'race-target@example.test');

        // Amíg a link kézbesítés alatt volt, valaki más regisztrált a címmel.
        $this->createUser(['email' => 'race-target@example.test']);

        // A validáció csak a kérés PILLANATÁBAN fut (Rule::unique a
        // UpdateUserProfileInformation-ben); az activate() csak beír és ment.
        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]));
    }

    // =========================================================================
    // 3. Lokalizáció
    // =========================================================================

    /** HIBA - a csere során pótolandó. */
    public function test_defect_the_first_verification_mail_view_is_not_localised(): void
    {
        $first = File::get(resource_path('views/vendor/verify-new-email/verifyFirstEmail.blade.php'));
        $new = File::get(resource_path('views/vendor/verify-new-email/verifyNewEmail.blade.php'));

        // A "másik" nézet a projekt saját fordítási kulcsait használja...
        $this->assertStringContainsString('@lang(', $new);
        $this->assertStringContainsString('email.verifyNewEmail.line_1', $new);

        // ...ez viszont a csomag angol stubja maradt, egy 22 lokálos alkalmazásban.
        // Elérhető ág: sendPendingEmailVerificationMail() ezt választja, ha a
        // felhasználó hasVerifiedEmail() hamis.
        $this->assertStringNotContainsString('@lang(', $first);
        $this->assertStringContainsString('Please click the button below', $first);
    }
}
