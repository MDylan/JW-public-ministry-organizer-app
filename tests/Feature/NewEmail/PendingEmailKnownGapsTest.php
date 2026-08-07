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

    /** HIBA - a javítás után ennek buknia kell. Javítás: TODO 33.2. */
    public function test_defect_anonymisation_leaves_the_real_address_in_the_pending_table(): void
    {
        [$user] = $this->userWithPendingEmail('gdpr-old@example.test', 'gdpr-wanted@example.test');

        $user->anonymize();

        $this->assertSame(1, (int) $user->fresh()->isAnonymized, 'Az anonimizálás lefutott.');

        $this->assertSame(
            'gdpr-wanted@example.test',
            DB::table('pending_user_emails')->where('user_id', $user->getKey())->value('email'),
            'MA bennmarad a valódi cím. A User::anonymize()-nak clearPendingEmail()-t kellene hívnia.'
        );
    }

    /** HIBA - a javítás után ennek buknia kell. Javítás: TODO 33.2. */
    public function test_defect_a_live_link_restores_the_real_address_onto_an_anonymized_user(): void
    {
        [$user, $token] = $this->userWithPendingEmail('gdpr-live-old@example.test', 'gdpr-live-wanted@example.test');

        $user->anonymize();

        $anonymizedEmail = $user->fresh()->email;
        $this->assertNotSame('gdpr-live-wanted@example.test', $anonymizedEmail);

        // A link a lejáratáig él, és a PendingUserEmail::activate() nem néz
        // rá az isAnonymized jelzőre.
        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]))
            ->assertRedirect(config('verify-new-email.redirect_to'));

        $fresh = $user->fresh();

        $this->assertSame(
            'gdpr-live-wanted@example.test',
            $fresh->email,
            'MA az anonimizálás visszafordul: a valódi cím visszakerül a felhasználóra.'
        );
        $this->assertNotNull($fresh->email_verified_at, 'Ráadásul verifikáltan.');
        $this->assertSame(
            1,
            (int) $fresh->isAnonymized,
            'Az isAnonymized jelző 1 marad, tehát a rekord "anonimizáltnak" látszik valódi címmel.'
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
