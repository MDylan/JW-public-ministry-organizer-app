<?php

namespace Tests\Feature\Gdpr;

use App\Models\User;
use App\Notifications\GroupUserLogoutNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12: a felhasználó által kezdeményezett adattörlés (GDPR 17. cikk).
 *
 * A deletePersonalDataController két lépésből áll: az asktodelete aláírt
 * linket küld e-mailben (ennek a kiváltását a NotificationTriggerRegressionTest
 * már fedte), a link mögötti deletePersonalData pedig ténylegesen anonimizál és
 * kilépteti a felhasználót minden csoportból. Ez utóbbi eddig fedetlen volt.
 */
class PersonalDataDeletionTest extends FeatureTestCase
{
    private function deletionUrl(User $user): string
    {
        return $this->signedRoute('user.deletepersonaldata', ['id' => $user->id]);
    }

    public function test_the_signed_link_anonymizes_the_owner(): void
    {
        $user = $this->createUser(['email' => 'self-delete@example.test']);

        $this->actingAs($user)
            ->get($this->deletionUrl($user))
            ->assertRedirect('login');

        $fresh = User::find($user->id);

        $this->assertSame(1, (int) $fresh->isAnonymized);
        $this->assertSame('Anonym', $fresh->name);
        $this->assertNotSame('self-delete@example.test', $fresh->email);
    }

    public function test_the_user_is_logged_out_afterwards(): void
    {
        $user = $this->createUser(['email' => 'logout@example.test']);

        $this->actingAs($user)->get($this->deletionUrl($user));

        $this->assertGuest();
    }

    public function test_every_group_membership_is_detached(): void
    {
        $user = $this->createUser(['email' => 'memberships@example.test']);
        $first = $this->createGroup(['name' => 'Első']);
        $second = $this->createGroup(['name' => 'Második']);
        $this->attachUserToGroup($user, $first, 'member');
        $this->attachUserToGroup($user, $second, 'roler');

        $this->actingAs($user)->get($this->deletionUrl($user));

        foreach ([$first, $second] as $group) {
            $this->assertDatabaseMissing('group_user', [
                'user_id' => $user->id,
                'group_id' => $group->id,
                'deleted_at' => null,
            ]);
        }
    }

    public function test_the_farewell_notification_is_suppressed_by_the_ordering(): void
    {
        // A sorrend számít: a kontroller ELŐBB anonimizál, csak utána bontja a
        // tagságokat. A GroupUserMoves::detach() (:131) viszont csak akkor
        // értesíti a felhasználót a kiléptetésről, ha nem anonimizált - így ez
        // az értesítés soha nem megy ki ezen az úton.
        //
        // Ez helyes is: az e-mail ekkor már token, nem cím. De a viselkedés
        // egy sorrendi véletlenen múlik, nem kimondott döntésen.
        Notification::fake();

        $user = $this->createUser(['email' => 'farewell@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member');

        $this->actingAs($user)->get($this->deletionUrl($user));

        Notification::assertNotSentTo($user, GroupUserLogoutNotification::class);
    }

    // =========================================================================
    // A jogosultság
    // =========================================================================

    public function test_another_users_id_is_rejected(): void
    {
        $owner = $this->createUser(['email' => 'owner-of-data@example.test']);
        $attacker = $this->createUser(['email' => 'attacker@example.test']);

        $this->actingAs($attacker)
            ->get($this->deletionUrl($owner))
            ->assertForbidden();

        $this->assertSame(
            'owner-of-data@example.test',
            User::find($owner->id)->email,
            'Idegen adatát nem lehet törölni.'
        );
    }

    public function test_an_unsigned_link_is_rejected(): void
    {
        // A route-on signed middleware van, tehát a nyers URL nem elég - a
        // levélben kiküldött aláírás nélkül 403 jár.
        $user = $this->createUser(['email' => 'unsigned@example.test']);

        $this->actingAs($user)
            ->get(route('user.deletepersonaldata', ['id' => $user->id]))
            ->assertForbidden();

        $this->assertSame('unsigned@example.test', User::find($user->id)->email);
    }
}
