<?php

namespace Tests\Feature\Groups;

use App\Http\Livewire\Groups\ListUsers;
use App\Models\Group;
use App\Models\User;
use App\Notifications\FinishRegistration;
use App\Notifications\GroupUserAddedNotification;
use App\Notifications\UserProfileRenewalAdminNotification;
use App\Notifications\UserProfileRenewalNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 11: a meghívás és a profilmegújítás kiváltó tesztjei.
 *
 * A NotificationRegressionTest mind a 26 értesítésre ellenőrzi a
 * LEVÉLSZERZŐDÉST (queue-e, van-e mail csatorna, épül-e MailMessage), de azt
 * nem, hogy kiváltódik-e egyáltalán, és kinek megy ki. Három osztály -
 * FinishRegistration, UserProfileRenewalNotification,
 * UserProfileRenewalAdminNotification - eddig CSAK azzal a szerződéssel volt
 * lefedve, tehát egy elnémult diszpécs nyomtalanul átcsúszott volna az
 * upgrade-en.
 *
 * Mindhármat a Groups\ListUsers váltja ki. Az értesítés viszont csak a
 * belépési pont: a köré épülő üzleti logika (felhasználó-létrehozás aláírt
 * linkkel, a több címes meghívás, a megújítás időküszöbe) ugyanígy
 * lefedetlen volt.
 */
class GroupUserInviteTest extends FeatureTestCase
{
    private Group $group;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->admin = $this->member('invite-admin@example.test', 'admin');
    }

    private function member(string $email, string $role = 'member', array $attributes = []): User
    {
        $user = $this->createUser(array_merge(['email' => $email], $attributes));
        $this->attachUserToGroup($user, $this->group, $role);

        return $user->fresh();
    }

    private function listUsers(?User $actor = null, ?Group $group = null)
    {
        return Livewire::actingAs($actor ?? $this->admin)
            ->test(ListUsers::class, ['group' => ($group ?? $this->group)->id]);
    }

    private function invite(string $emails, ?User $actor = null, ?Group $group = null)
    {
        return $this->listUsers($actor, $group)
            ->set('new_users', $emails)
            ->call('createUser');
    }

    /**
     * A notification $data property-je privát, ezért reflexióval olvassuk -
     * ugyanaz a minta, amit az ObserverCauserTest is használ.
     */
    private function notificationPayload($notification): array
    {
        $property = new \ReflectionProperty($notification, 'data');
        $property->setAccessible(true);

        return $property->getValue($notification);
    }

    // =========================================================================
    // 1. Meghívás - createUser()
    // =========================================================================

    public function test_inviting_an_unknown_address_creates_the_user_and_sends_the_finish_registration_mail(): void
    {
        Notification::fake();

        $this->invite('invited@example.test')->assertHasNoErrors();

        $invited = User::where('email', 'invited@example.test')->firstOrFail();

        $this->assertNull($invited->email_verified_at, 'A meghívott még nem igazolt.');
        $this->assertSame('hu', $invited->language, 'Az email_language a mount()-ból jön.');
        $this->assertNotNull(
            $this->group->groupUsers()->where('user_id', $invited->id)->first(),
            'A GroupUserMoves::attach() be is lépteti a csoportba.'
        );

        Notification::assertSentTo($invited, FinishRegistration::class);
    }

    public function test_the_invitation_carries_a_signed_finish_registration_link_for_that_user(): void
    {
        Notification::fake();

        $this->invite('invited-link@example.test')->assertHasNoErrors();

        $invited = User::where('email', 'invited-link@example.test')->firstOrFail();

        Notification::assertSentTo(
            $invited,
            FinishRegistration::class,
            function ($notification) use ($invited) {
                $payload = $this->notificationPayload($notification);

                // Az {id} útvonal-szegmens, nem query paraméter.
                return str_contains($payload['url'], 'signature=')
                    && str_contains($payload['url'], '/finish-registration/'.$invited->id)
                    && $payload['groupAdmin'] === $this->admin->name
                    && $payload['userMail'] === $invited->email;
            }
        );
    }

    public function test_an_unverified_invitee_does_not_also_get_the_group_added_notification(): void
    {
        // A GroupUserMoves::attach() (:45, :54) az email_verified_at-hez köti
        // a GroupUserAddedNotification-t. Egy frissen meghívott vendégnek az
        // még null, tehát ő CSAK a FinishRegistration-t kapja - különben két
        // levelet kapna ugyanarról az egy eseményről.
        Notification::fake();

        $this->invite('invited-single@example.test')->assertHasNoErrors();

        $invited = User::where('email', 'invited-single@example.test')->firstOrFail();

        Notification::assertSentTo($invited, FinishRegistration::class);
        Notification::assertNotSentTo($invited, GroupUserAddedNotification::class);
    }

    public function test_several_addresses_can_be_invited_in_one_submission(): void
    {
        // A createUser() a preg_split("/\R/")-rel bontja sorokra a mezőt
        // (:97), és a \R a CRLF-et is kezeli - a böngészőből így érkezik.
        Notification::fake();

        $this->invite("first@example.test\r\nsecond@example.test\n\nthird@example.test")
            ->assertHasNoErrors();

        foreach (['first', 'second', 'third'] as $prefix) {
            $user = User::where('email', $prefix.'@example.test')->first();

            $this->assertNotNull($user, $prefix.'@example.test létrejött.');
            Notification::assertSentTo($user, FinishRegistration::class);
        }

        $this->assertSame(
            3,
            $this->group->groupUsers()->whereIn('email', [
                'first@example.test', 'second@example.test', 'third@example.test',
            ])->count(),
            'Az üres sorokat a ciklus átugorja, tehát pontosan három tag jön létre.'
        );
    }

    public function test_inviting_an_existing_user_attaches_them_without_a_finish_registration_mail(): void
    {
        // A User::where(...)->firstOr(closure) closure-je nem fut le, ha a
        // cím már létezik - a meghívó levél tehát elmarad, a csatolás nem.
        Notification::fake();

        $existing = $this->createUser(['email' => 'already-registered@example.test']);

        $this->invite($existing->email)->assertHasNoErrors();

        Notification::assertNotSentTo($existing, FinishRegistration::class);
        Notification::assertSentTo($existing, GroupUserAddedNotification::class);
        $this->assertNotNull(
            $this->group->groupUsers()->where('user_id', $existing->id)->first()
        );
    }

    public function test_inviting_a_current_member_is_a_no_op(): void
    {
        // A :148 continue-ja miatt a már benne lévő tagnál semmi nem történik.
        Notification::fake();

        $member = $this->member('already-member@example.test');

        $this->invite($member->email)->assertHasNoErrors();

        Notification::assertNotSentTo($member, FinishRegistration::class);
        Notification::assertNotSentTo($member, GroupUserAddedNotification::class);
    }

    public function test_an_invalid_address_stops_the_whole_submission(): void
    {
        Notification::fake();

        $this->invite("valid-one@example.test\nnem-email")
            ->assertHasErrors('email.1');

        $this->assertNull(
            User::where('email', 'valid-one@example.test')->first(),
            'A validátor a teljes tömbre fut, tehát a jó cím sem jön létre.'
        );
    }

    public function test_a_plain_member_cannot_invite(): void
    {
        $member = $this->member('invite-outsider@example.test', 'member');

        $this->invite('blocked@example.test', $member)->assertForbidden();
    }

    public function test_a_child_group_rejects_the_invitation_with_a_visible_message(): void
    {
        // MEGFORDÍTVA a v1-patch B7 javításával.
        //
        // Gyerekcsoportba nem lehet közvetlenül tagot felvenni - a tagságot a
        // szülőcsoport adja -, de az őr PUSZTA return-nel lépett ki: se
        // hibaüzenet, se modal-visszajelzés. Az adminisztrátor abban a hitben
        // maradt, hogy elküldte a meghívót. A tiltás maga helyes; csak a
        // hallgatás nem volt az.
        Notification::fake();

        $child = $this->createChildGroup($this->group);
        $childAdmin = $this->createUser(['email' => 'child-admin@example.test']);
        $this->attachUserToGroup($childAdmin, $child, 'admin');

        $this->invite('never-invited@example.test', $childAdmin, $child)
            ->assertHasErrors(['new_users']);

        $this->assertNull(
            User::where('email', 'never-invited@example.test')->first(),
            'A felhasználó továbbra sem jön létre.'
        );
        Notification::assertNothingSent();
    }

    public function test_the_child_group_rejection_message_exists_in_every_maintained_locale(): void
    {
        // A hibaüzenet csak akkor ér valamit, ha nem nyers kulcsként jelenik
        // meg. A projekt három karbantartott lokálja a hu, az en és a de.
        foreach (['hu', 'en', 'de'] as $locale) {
            $this->assertNotSame(
                'group.user.add.error_this_is_child',
                __('group.user.add.error_this_is_child', [], $locale),
                $locale.': hiányzik a fordítás.'
            );
        }
    }

    // =========================================================================
    // 2. Profilmegújítás - userRenewal()
    // =========================================================================

    private function inactive(string $email): User
    {
        // A küszöb: last_activity < now() - ttl hónap + 14 nap (:461).
        // A gdpr.settings.ttl 6, tehát a határ nagyjából 5,5 hónap.
        return $this->member($email, 'member', [
            'last_activity' => now()->subMonths(7),
        ]);
    }

    private function renew(User $target, ?User $actor = null)
    {
        return $this->listUsers($actor)
            ->call('openUserRenewalModal', $target->id)
            ->call('userRenewal');
    }

    public function test_renewing_an_inactive_user_refreshes_the_activity_and_notifies_both_sides(): void
    {
        Notification::fake();

        $editor = $this->member('renewal-editor@example.test', 'roler');
        $target = $this->inactive('renewal-target@example.test');

        $this->renew($target)->assertHasNoErrors();

        // A last_activity nincs castolva a User modellen, tehát nyers string.
        $this->assertGreaterThan(
            now()->subMinute()->getTimestamp(),
            strtotime($target->fresh()->last_activity),
            'A last_activity mostra frissül.'
        );

        Notification::assertSentTo($target, UserProfileRenewalNotification::class);
        Notification::assertSentTo($editor, UserProfileRenewalAdminNotification::class);
        Notification::assertSentTo($this->admin, UserProfileRenewalAdminNotification::class);
    }

    public function test_the_renewal_mail_names_the_acting_admin(): void
    {
        Notification::fake();

        $target = $this->inactive('renewal-payload@example.test');

        $this->renew($target)->assertHasNoErrors();

        Notification::assertSentTo(
            $target,
            UserProfileRenewalNotification::class,
            function ($notification) use ($target) {
                $payload = $this->notificationPayload($notification);

                return $payload['adminName'] === $this->admin->name
                    && $payload['userName'] === $target->name;
            }
        );
    }

    public function test_a_plain_member_is_not_notified_about_someone_elses_renewal(): void
    {
        // Az adminok értesítője a group->editors relációra megy (:475), ami
        // csak a 'roler' és 'admin' pivot-szerepeket tartalmazza.
        Notification::fake();

        $bystander = $this->member('renewal-bystander@example.test', 'member');
        $target = $this->inactive('renewal-target2@example.test');

        $this->renew($target)->assertHasNoErrors();

        Notification::assertNotSentTo($bystander, UserProfileRenewalAdminNotification::class);
    }

    public function test_an_active_user_cannot_be_renewed(): void
    {
        // A :461 küszöbe alatt az $error = true ág fut: se írás, se levél,
        // csak egy böngésző-esemény.
        Notification::fake();

        $target = $this->member('renewal-active@example.test', 'member', [
            'last_activity' => now()->subMonth(),
        ]);
        $before = $target->fresh()->last_activity;

        $this->renew($target)->assertHasNoErrors();

        $this->assertSame(
            $before,
            $target->fresh()->last_activity,
            'A last_activity érintetlen marad.'
        );

        Notification::assertNotSentTo($target, UserProfileRenewalNotification::class);
        Notification::assertNotSentTo($this->admin, UserProfileRenewalAdminNotification::class);
    }

    public function test_a_plain_member_cannot_open_the_renewal_modal(): void
    {
        $member = $this->member('renewal-outsider@example.test', 'member');
        $target = $this->inactive('renewal-target3@example.test');

        $this->listUsers($member)
            ->call('openUserRenewalModal', $target->id)
            ->assertForbidden();
    }
}
