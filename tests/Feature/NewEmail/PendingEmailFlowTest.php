<?php

namespace Tests\Feature\NewEmail;

use App\Mail\VerifyFirstEmail;
use App\Mail\VerifyNewEmail;
use App\Models\User;
use App\Notifications\UserEmailChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 19 / 19.1: the contract of the pending e-mail address flow.
 *
 * A csomagnak NULLA tesztje volt ebben a projektben. A 983-as szuitéből
 * egyetlen eset érintette közvetve (NotificationTriggerRegressionTest), plusz a
 * route-fixture egy sora. Emiatt a TODO 19 három opciójának (fork / in-house
 * csere / várakozás az upstreamre) nem volt elfogadási kritériuma - ez a fájl az.
 *
 * A tesztek a MAI viselkedést rögzítik, a csomaggal a helyén. Bármely opció
 * végrehajtása után ugyanezeknek zöldnek kell maradniuk; ami elmozdulhat, az a
 * PendingEmailKnownGapsTest-ben áll, külön, szándékosan.
 *
 * Egy mérés, ami a fájl egészét meghatározza: mindkét Mailable ShouldQueue,
 * ezért Mail::fake() mellett NEM assertSent(), hanem assertQueued() fog rájuk.
 * A queue driver a tesztekben `sync`, tehát élesben ugyanabban a kérésben
 * mennek ki, de a fake már a sorbatételnél elkapja őket.
 */
class PendingEmailFlowTest extends FeatureTestCase
{
    /** @return array<int, object> */
    private function pendingRows(User $user): array
    {
        return DB::table('pending_user_emails')
            ->where('user_type', User::class)
            ->where('user_id', $user->getKey())
            ->get()
            ->all();
    }

    // =========================================================================
    // 1. Amit a newEmail() ír
    // =========================================================================

    public function test_new_email_creates_a_pending_row_and_leaves_the_user_email_untouched(): void
    {
        Mail::fake();

        $user = $this->createUser(['email' => 'current@example.test']);

        $user->newEmail('wanted@example.test');

        $rows = $this->pendingRows($user);

        $this->assertCount(1, $rows, 'Egy függőben lévő cím tartozik egy felhasználóhoz.');
        $this->assertSame('wanted@example.test', $rows[0]->email);
        $this->assertNotEmpty($rows[0]->token);
        $this->assertSame(
            'current@example.test',
            $user->fresh()->email,
            'A users.email csak az aktiváláskor változik - ez a feature lényege.'
        );
    }

    public function test_a_verified_user_receives_the_verify_new_email_mailable_at_the_new_address(): void
    {
        Mail::fake();

        $user = $this->createUser([
            'email' => 'verified-owner@example.test',
            'email_verified_at' => now(),
        ]);

        $user->newEmail('brand-new@example.test');

        Mail::assertQueued(VerifyNewEmail::class, function ($mail) {
            return $mail->hasTo('brand-new@example.test');
        });
        Mail::assertNotQueued(VerifyFirstEmail::class);
    }

    public function test_an_unverified_user_receives_the_verify_first_email_mailable_instead(): void
    {
        Mail::fake();

        $user = $this->createUser([
            'email' => 'never-verified@example.test',
            'email_verified_at' => null,
        ]);

        $user->newEmail('first-try@example.test');

        Mail::assertQueued(VerifyFirstEmail::class, function ($mail) {
            return $mail->hasTo('first-try@example.test');
        });
        Mail::assertNotQueued(VerifyNewEmail::class);
    }

    public function test_a_second_request_replaces_the_first_pending_row(): void
    {
        Mail::fake();

        $user = $this->createUser(['email' => 'switcher@example.test']);

        $user->newEmail('first@example.test');
        $user->newEmail('second@example.test');

        $rows = $this->pendingRows($user);

        $this->assertCount(1, $rows, 'A createPendingUserEmailModel() előbb clearPendingEmail()-t hív.');
        $this->assertSame('second@example.test', $rows[0]->email);
    }

    public function test_new_email_returns_null_when_a_verified_user_asks_for_the_address_it_already_has(): void
    {
        Mail::fake();

        $user = $this->createUser([
            'email' => 'same@example.test',
            'email_verified_at' => now(),
        ]);

        $this->assertNull($user->newEmail('same@example.test'));
        $this->assertSame([], $this->pendingRows($user));
        Mail::assertNothingQueued();
    }

    // =========================================================================
    // 2. A profiloldali útvonal - ez az EGYETLEN newEmail() hívási hely
    // =========================================================================

    public function test_the_profile_update_creates_the_pending_row_and_notifies_the_old_address(): void
    {
        Mail::fake();
        Notification::fake();

        $user = $this->createUser(['email' => 'old-address@example.test']);

        $this->actingAs($user)
            ->put(route('user-profile-information.update'), [
                'name' => 'Updated Name',
                'email' => 'requested@example.test',
                'phone_number' => '36201234567',
                'congregation' => 'Congregation',
            ])
            ->assertStatus(302);

        $rows = $this->pendingRows($user);

        $this->assertCount(1, $rows);
        $this->assertSame('requested@example.test', $rows[0]->email);

        // A figyelmeztetés a RÉGI címre megy, hogy egy eltulajdonított fiók
        // tulajdonosa értesüljön róla. UpdateUserProfileInformation.php:53.
        Notification::assertSentTo($user->fresh(), UserEmailChangedNotification::class);
    }

    public function test_the_profile_update_does_not_write_the_new_address_into_the_users_table(): void
    {
        Mail::fake();
        Notification::fake();

        $user = $this->createUser(['email' => 'stays@example.test']);

        $this->actingAs($user)
            ->put(route('user-profile-information.update'), [
                'name' => 'Renamed User',
                'email' => 'not-yet@example.test',
                'phone_number' => '36201234567',
                'congregation' => 'Congregation',
            ])
            ->assertStatus(302);

        $fresh = $user->fresh();

        $this->assertSame('stays@example.test', $fresh->email);
        $this->assertSame('Renamed User', $fresh->name, 'A többi mező viszont azonnal mentődik.');

        // A kért cím nem tűnik el, csak várakozik - enélkül az állítás akkor is
        // zöld lenne, ha a newEmail() hívás egyszerűen kikerülne a kódból.
        $this->assertSame('not-yet@example.test', $fresh->getPendingEmail());
    }

    // =========================================================================
    // 3. getPendingEmail() és a két nézet, ami használja
    // =========================================================================

    public function test_get_pending_email_returns_the_address_and_null_when_there_is_none(): void
    {
        Mail::fake();

        $user = $this->createUser(['email' => 'reader@example.test']);

        $this->assertNull($user->getPendingEmail());

        $user->newEmail('shown@example.test');

        $this->assertSame('shown@example.test', $user->getPendingEmail());
    }

    public function test_the_profile_page_shows_the_pending_address_badge(): void
    {
        Mail::fake();

        $user = $this->createUser(['email' => 'badge@example.test']);
        $user->newEmail('badge-pending@example.test');

        $this->actingAs($user)
            ->get(route('user.profile'))
            ->assertStatus(200)
            ->assertSee('badge-pending@example.test', false);
    }

    public function test_the_layout_banner_shows_the_pending_address_outside_the_profile_page(): void
    {
        Mail::fake();

        $user = $this->createUser(['email' => 'banner@example.test']);
        $user->newEmail('banner-pending@example.test');

        // layouts/app.blade.php:45 - a sávot a `!request()->routeIs('user.profile')`
        // feltétel zárja ki a profiloldalon, ahol a badge áll helyette.
        $this->actingAs($user)
            ->get(route('home.home'))
            ->assertStatus(200)
            ->assertSee('banner-pending@example.test', false);
    }

    // =========================================================================
    // 4. Újraküldés
    // =========================================================================

    public function test_resend_sends_a_fresh_mail_and_rotates_the_token(): void
    {
        Mail::fake();

        $user = $this->createUser(['email' => 'resend@example.test']);
        $user->newEmail('resend-target@example.test');

        $firstToken = $this->pendingRows($user)[0]->token;

        $this->actingAs($user)
            ->get(route('user.resendNewEmailVerification'))
            ->assertRedirect(route('user.profile'))
            ->assertSessionHas('success');

        $rows = $this->pendingRows($user);

        $this->assertCount(1, $rows, 'Az újraküldés is a newEmail()-en megy át, tehát cserél, nem halmoz.');
        $this->assertSame('resend-target@example.test', $rows[0]->email);
        $this->assertNotSame($firstToken, $rows[0]->token, 'Új token - a régi link érvénytelenné válik.');

        Mail::assertQueued(VerifyNewEmail::class, function ($mail) {
            return $mail->hasTo('resend-target@example.test');
        });
    }

    public function test_resend_without_a_pending_address_flashes_the_not_pending_message(): void
    {
        Mail::fake();

        $user = $this->createUser(['email' => 'nothing-pending@example.test']);

        $this->actingAs($user)
            ->get(route('user.resendNewEmailVerification'))
            ->assertRedirect(route('user.profile'))
            ->assertSessionHas('profile_message')
            ->assertSessionMissing('success');

        Mail::assertNothingQueued();
    }
}
