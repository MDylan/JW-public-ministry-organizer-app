<?php

namespace Tests\Feature\NewEmail;

use App\Mail\VerifyFirstEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 19 / 19.1: the measured gaps of `pending_user_emails` - ALL CLOSED.
 *
 * This file DELIBERATELY pinned the broken behaviour to begin with, with the
 * same discipline TODO 14 applied to the duplicated route names: the assertion
 * described the state of the day, and it had to fail the moment the defect was
 * fixed. That failure is the reviewable diff, not a regression.
 *
 * Today the file pins the FIXED behaviour and guards it as a tripwire. Where
 * each gap was closed:
 *
 *   1-2. Anonymization and the live link - `v1-patch` B12/B13.
 *        `User::anonymize()` calls `clearPendingEmail()`, and
 *        `PendingUserEmail::activate()` guards as well. TODO 33.2 carried this
 *        over to the in-house GDPR code, TODO 33.5 to the in-house model.
 *     3. The row orphaned by a deleted user - TODO 33.5,
 *        `UserObserver::deleted()`.
 *     4. The collision that produced a 500 during a signed-link GET -
 *        TODO 33.5, `PendingUserEmail::activate()`.
 *     5. The untranslated first confirmation mail - TODO 33.5,
 *        `resources/views/emails/verifyFirstEmail.blade.php`.
 *
 * Every reversed case keeps a description of the original defect: that is what
 * explains WHY the assertion is here.
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

        // REVERSED by TODO 33.5. The vendor activate() returned silently and
        // the controller went to the SUCCESS page regardless: an anonymized
        // user's link produced a "confirmed" screen although nothing had
        // happened. The in-house code distinguishes three outcomes, so this
        // path now lands where an expired link lands.
        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]))
            ->assertRedirect(route('login'))
            ->assertSessionHas('profile_message');

        $fresh = $user->fresh();

        $this->assertSame($anonymizedEmail, $fresh->email, 'Az activate() nem írhat anonimizált felhasználóra.');
        $this->assertSame(1, (int) $fresh->isAnonymized);

        $this->assertNull(
            DB::table('pending_user_emails')->where('user_id', $user->getKey())->value('email'),
            'És a sort el is dobja, hogy a valódi cím ne maradjon bent.'
        );
    }

    /** REVERSED by TODO 33.5 (defect 3). */
    public function test_deleting_a_user_clears_the_pending_row(): void
    {
        // The row is attached through `morphs()`, so there is NO foreign key,
        // and until TODO 33.5 none of the observers touched it: a deleted
        // user's pending address - a real, never-confirmed e-mail address -
        // stayed in the table indefinitely. The cleanup now happens in
        // UserObserver::deleted().
        [$user] = $this->userWithPendingEmail('deleted@example.test', 'deleted-wanted@example.test');

        $userId = $user->getKey();
        $user->delete();

        $this->assertNull(User::find($userId));
        $this->assertSame(
            0,
            DB::table('pending_user_emails')->where('user_id', $userId)->count(),
            'The delete takes the pending address with it.'
        );
    }

    public function test_deleting_a_user_leaves_another_users_pending_row_alone(): void
    {
        // Control experiment: the cleanup hangs off a single predicate
        // (forUser), and without this the sibling assertion would stay green
        // even if the delete emptied the WHOLE table. Same discipline the
        // TODO 33.2 backfill migration followed.
        [$doomed] = $this->userWithPendingEmail('doomed@example.test', 'doomed-wanted@example.test');
        [$bystander] = $this->userWithPendingEmail('bystander@example.test', 'bystander-wanted@example.test');

        $doomed->delete();

        $this->assertSame(
            'bystander-wanted@example.test',
            DB::table('pending_user_emails')->where('user_id', $bystander->getKey())->value('email')
        );
    }

    // =========================================================================
    // 2. The collision
    // =========================================================================

    /** REVERSED by TODO 33.5 (defect 4). */
    public function test_activation_reports_the_collision_instead_of_fataling(): void
    {
        // Validation runs only at the MOMENT of the request (Rule::unique in
        // UpdateUserProfileInformation). If somebody else registers the same
        // address while the mail is in flight, the vendor activate() simply
        // wrote and saved: SQLSTATE[23000] during a signed-link GET, i.e. a 500
        // page with no way out.
        [$user, $token] = $this->userWithPendingEmail('race-old@example.test', 'race-target@example.test');

        $this->createUser(['email' => 'race-target@example.test']);

        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]))
            ->assertRedirect(route('login'))
            ->assertSessionHas('profile_message');

        $this->assertSame(
            'race-old@example.test',
            $user->fresh()->email,
            'A failed activation must not change the user address.'
        );
    }

    public function test_a_logged_in_visitor_is_sent_back_to_the_profile_on_a_collision(): void
    {
        // The link is usually opened on another device, but not always.
        // Somebody logged in goes back to the profile - that is where the
        // pending address is shown and where a different one can be entered.
        [$user, $token] = $this->userWithPendingEmail('race-in@example.test', 'race-in-target@example.test');

        $this->createUser(['email' => 'race-in-target@example.test']);

        $this->actingAs($user)
            ->get($this->signedRoute('pendingEmail.verify', ['token' => $token]))
            ->assertRedirect(route('user.profile'))
            ->assertSessionHas('profile_message');
    }

    public function test_the_collision_leaves_the_pending_row_in_place_so_the_user_can_retry(): void
    {
        // The collision is neither the user's fault nor final: the row stays,
        // so the profile page still shows the pending address and a resend is
        // possible. A successful activation, by contrast, deletes it.
        [$user, $token] = $this->userWithPendingEmail('race-keep@example.test', 'race-keep-target@example.test');

        $this->createUser(['email' => 'race-keep-target@example.test']);

        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]));

        $this->assertSame('race-keep-target@example.test', $user->fresh()->getPendingEmail());
    }

    // =========================================================================
    // 3. Lokalizáció
    // =========================================================================

    /** REVERSED by TODO 33.5 (defect 5). */
    public function test_both_verification_mail_views_are_localised(): void
    {
        // `verifyFirstEmail` was the package's ENGLISH stub in a 22-locale
        // application, while its sibling had been translated all along. A
        // reachable path, not a theoretical one:
        // sendPendingEmailVerificationMail() picks exactly this one whenever
        // hasVerifiedEmail() is false.
        //
        // The views moved from the vendor path into the project's own emails/
        // directory, because the release hook deletes the old location from
        // deployed hosts.
        $first = File::get(resource_path('views/emails/verifyFirstEmail.blade.php'));
        $new = File::get(resource_path('views/emails/verifyNewEmail.blade.php'));

        $this->assertStringContainsString('@lang(', $new);
        $this->assertStringContainsString('email.verifyNewEmail.line_1', $new);

        $this->assertStringContainsString('@lang(', $first);
        $this->assertStringContainsString('email.verifyFirstEmail.line_1', $first);

        $this->assertDirectoryDoesNotExist(
            resource_path('views/vendor/verify-new-email'),
            'The published vendor views go away with the package.'
        );
    }

    public function test_the_first_verification_mail_body_carries_no_untranslated_stub_text(): void
    {
        // Having the keys is not enough: the stub's English sentences must not
        // leak into the rendered mail. Checked on the Hungarian locale, where
        // every key really is translated.
        //
        // Mail::fake() is DELIBERATELY absent: MailFake cannot render(). The row
        // is therefore created through the non-sending half of the flow.
        $this->app->setLocale('hu');

        $user = $this->createUser([
            'email' => 'first-verify@example.test',
            'email_verified_at' => null,
        ]);
        $pending = $user->createPendingUserEmailModel('first-verify-new@example.test');

        $body = (new VerifyFirstEmail($pending))->render();

        $this->assertStringNotContainsString('Please click the button below', $body);
        $this->assertStringContainsString(__('email.verifyFirstEmail.line_1'), $body);
    }
}
