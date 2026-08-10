<?php

namespace Tests\Feature\Gdpr;

use App\Models\User;
use App\Notifications\GroupUserLogoutNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12: user-initiated data deletion (GDPR Article 17).
 *
 * The deletePersonalDataController consists of two steps: asktodelete sends a
 * signed link by email (triggering this was already covered by
 * NotificationTriggerRegressionTest), while deletePersonalData behind the link
 * actually anonymizes the user and logs them out of every group. The latter was
 * uncovered until now.
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
        // Order matters: the controller anonymizes FIRST, only afterwards does it detach
        // the memberships. GroupUserMoves::detach() (:131), however, only notifies the
        // user about being removed if they are not anonymized - so this notification
        // never goes out via this path.
        //
        // This is actually correct: at this point the email is already a token, not an
        // address. But the behavior hinges on an accident of ordering, not an explicit decision.
        Notification::fake();

        $user = $this->createUser(['email' => 'farewell@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member');

        $this->actingAs($user)->get($this->deletionUrl($user));

        Notification::assertNotSentTo($user, GroupUserLogoutNotification::class);
    }

    // =========================================================================
    // Authorization
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
        // The route has signed middleware, so the raw URL is not enough - without
        // the signature sent in the email, a 403 is returned.
        $user = $this->createUser(['email' => 'unsigned@example.test']);

        $this->actingAs($user)
            ->get(route('user.deletepersonaldata', ['id' => $user->id]))
            ->assertForbidden();

        $this->assertSame('unsigned@example.test', User::find($user->id)->email);
    }
}
