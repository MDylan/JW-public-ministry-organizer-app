<?php

namespace Tests\Feature\Groups;

use App\Http\Livewire\Groups\ListUsers;
use App\Models\Group;
use App\Models\User;
use App\Notifications\LoginData;
use App\Notifications\UserProfileChangedNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07.2: role assignment within a group.
 *
 * Groups\ListUsers::updateUser() (:201-320) is roughly 120 lines of
 * authorization logic, which today is touched only by a "the route returns
 * 200" smoke test. This code decides who can promote or demote whom in a
 * group.
 *
 * The roadmap refers to this method as saveUser() - no such method exists in
 * the component; editing goes through the editUser() -> updateUser() pair.
 *
 * Four rules live in it, and ALL THREE validator rules only run if the role
 * actually changes (:236). This creates an overlap with the first rule -
 * see section 4.
 */
class GroupRoleAssignmentTest extends FeatureTestCase
{
    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
    }

    private function member(string $email, string $role = 'member', array $attributes = []): User
    {
        $user = $this->createUser(array_merge(['email' => $email], $attributes));
        $this->attachUserToGroup($user, $this->group, $role);

        return $user->fresh();
    }

    private function edit(User $actor, User $target, ?Group $group = null)
    {
        return Livewire::actingAs($actor)
            ->test(ListUsers::class, ['group' => ($group ?? $this->group)->id])
            ->call('editUser', $target->id);
    }

    private function roleOf(User $user, ?Group $group = null): string
    {
        return ($group ?? $this->group)
            ->groupUsers()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->pivot
            ->group_role;
    }

    // =========================================================================
    // 1. Entry authorization
    // =========================================================================

    public function test_a_plain_member_cannot_open_the_user_editor(): void
    {
        $actor = $this->member('ra-member@example.test', 'member');
        $target = $this->member('ra-target1@example.test', 'member');

        $this->edit($actor, $target)->assertForbidden();
    }

    public function test_a_helper_cannot_open_the_user_editor_either(): void
    {
        // CHARACTERIZATION TEST: isNotHelper() (:796-798) is literally
        // identical to isNotEditor() (:792-794) - both only allow
        // ['admin','roler']. So the 'helper' role is not a helper in the
        // sense of the method.
        //
        // Consequence: maxRoles() (:808-816) can in practice only return two
        // values, ['member','helper','roler'] (roler) or all four (admin);
        // the 'member' and 'helper' branches are unreachable.
        $actor = $this->member('ra-helper@example.test', 'helper');
        $target = $this->member('ra-target2@example.test', 'member');

        $this->edit($actor, $target)->assertForbidden();
    }

    public function test_a_roler_can_open_the_user_editor(): void
    {
        $actor = $this->member('ra-roler@example.test', 'roler');
        $target = $this->member('ra-target3@example.test', 'member');

        $this->edit($actor, $target)->assertSet('selected_user.id', $target->id);
    }

    public function test_an_outsider_gets_a_403(): void
    {
        // TODO 10 fixed getRole() (:800-806): previously it ended with
        // ->first()->toArray(), so without a membership row it threw a
        // fatal, BEFORE isNotHelper() could have given a 403.
        //
        // The groupMember middleware is present on the route, so this was
        // not an active security hole - but a 500 error kept the component
        // closed, which is not protection. Now getRole() itself closes it.
        //
        // It matters why leaving the role at null is not the correct fix:
        // render() does not check authorization, it only computes $editor,
        // so the outsider would render the member list as a non-editor.
        $outsider = $this->createUser(['email' => 'ra-outsider@example.test']);

        Livewire::actingAs($outsider)
            ->test(ListUsers::class, ['group' => $this->group->id])
            ->assertForbidden();
    }

    // =========================================================================
    // 2. The state built by editUser() - the input contract of updateUser()
    // =========================================================================

    public function test_edit_user_builds_the_full_state_the_updater_expects(): void
    {
        // updateUser() assumes every key is present; the validator's rules
        // (hidden => required, finish_guest_registration => Rule::In) behave
        // differently for a missing key. The shape of state is therefore a
        // contract between the two methods.
        $actor = $this->member('ra-state-actor@example.test', 'admin');
        $target = $this->member('ra-state-target@example.test', 'roler', [
            'name'         => 'Cél Elek',
            'phone_number' => '36301112222',
            'congregation' => 'Példa',
        ]);

        $this->edit($actor, $target)
            ->assertSet('state', $this->editUserState($target, ['group_role' => 'roler']))
            ->assertDispatchedBrowserEvent('show-modal');
    }

    // =========================================================================
    // 3. First rule: silent reset for a role outside of scope
    // =========================================================================

    public function test_a_roler_granting_the_admin_role_is_silently_reset_without_any_error(): void
    {
        // THIS IS THE MOST DANGEROUS RULE. maxRoles() (:808-816) walks the
        // ['member','helper','roler','admin'] list and stops at the caller's
        // own role - so a roler cannot grant the admin role.
        //
        // The implementation, however, does NOT reject: :211-214 silently
        // resets the submitted value to the target's CURRENT role, then the
        // save runs without error. The user gets a "saved" confirmation
        // while nothing actually happened.
        //
        // During an upgrade this can break in a way where the validation
        // stays green, only the reset is dropped - and from then on anyone
        // can grant any role. Hence the dedicated test.
        $actor = $this->member('ra-silent-actor@example.test', 'roler');
        $target = $this->member('ra-silent-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.group_role', 'admin')
            ->call('updateUser')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('hide-modal');

        $this->assertSame('member', $this->roleOf($target), 'A szerep némán változatlan maradt.');
    }

    public function test_a_roler_can_grant_roles_up_to_its_own_level(): void
    {
        $actor = $this->member('ra-uplevel-actor@example.test', 'roler');
        $target = $this->member('ra-uplevel-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.group_role', 'roler')
            ->call('updateUser')
            ->assertHasNoErrors();

        $this->assertSame('roler', $this->roleOf($target));
    }

    public function test_an_admin_can_grant_the_admin_role(): void
    {
        $actor = $this->member('ra-admin-actor@example.test', 'admin');
        $target = $this->member('ra-admin-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.group_role', 'admin')
            ->call('updateUser')
            ->assertHasNoErrors();

        $this->assertSame('admin', $this->roleOf($target));
    }

    // =========================================================================
    // 4. Second rule: protection of the last administrator
    // =========================================================================

    public function test_the_last_admin_cannot_step_down(): void
    {
        $actor = $this->member('ra-lastadmin@example.test', 'admin');

        $this->edit($actor, $actor)
            ->set('state.group_role', 'roler')
            ->call('updateUser')
            ->assertHasErrors(['users']);

        $this->assertSame('admin', $this->roleOf($actor));
    }

    public function test_an_admin_can_step_down_when_another_admin_remains(): void
    {
        $actor = $this->member('ra-stepdown@example.test', 'admin');
        $this->member('ra-otheradmin@example.test', 'admin');

        $this->edit($actor, $actor)
            ->set('state.group_role', 'roler')
            ->call('updateUser')
            ->assertHasNoErrors();

        $this->assertSame('roler', $this->roleOf($actor));
    }

    public function test_the_admin_check_also_walks_the_child_groups(): void
    {
        // pwbs_check_group_other_admins() (helpers.php:24-61) also checks
        // the child groups besides the parent, and asks whether there is
        // ANOTHER administrator who is admin in EVERY group
        // (array_search($total_group, $main_admins, true)).
        //
        // Here the second admin is admin only in the parent group, not in
        // the child - so the demotion is forbidden, even though the parent
        // group alone would still have an administrator. This branch was
        // uncovered until now.
        $child = $this->createChildGroup($this->group);

        $actor = $this->member('ra-child-actor@example.test', 'admin');
        $target = $this->member('ra-child-target@example.test', 'admin');

        $this->attachUserToGroup($actor, $child, 'member');
        $this->attachUserToGroup($target, $child, 'admin');

        $this->edit($actor, $target)
            ->set('state.group_role', 'roler')
            ->call('updateUser')
            ->assertHasErrors(['users']);

        $this->assertSame('admin', $this->roleOf($target));
    }

    public function test_the_child_group_check_passes_when_the_other_admin_covers_every_group(): void
    {
        $child = $this->createChildGroup($this->group);

        $actor = $this->member('ra-child2-actor@example.test', 'admin');
        $target = $this->member('ra-child2-target@example.test', 'admin');

        $this->attachUserToGroup($actor, $child, 'admin');
        $this->attachUserToGroup($target, $child, 'admin');

        $this->edit($actor, $target)
            ->set('state.group_role', 'roler')
            ->call('updateUser')
            ->assertHasNoErrors();

        $this->assertSame('roler', $this->roleOf($target));
    }

    // =========================================================================
    // 5. Third and fourth rule - and the asymmetry between them
    // =========================================================================

    public function test_a_roler_cannot_take_the_admin_role_away_from_someone(): void
    {
        // The third rule (:243-247) IS REACHABLE, because the demotion's
        // target value ('roler') is WITHIN the roler's own scope - so the
        // first rule does not reset it, the role would actually change, and
        // the validator's after() block runs.
        //
        // The second administrator is needed so that the second rule
        // (error_no_admin_user) does not mask this error.
        $actor = $this->member('ra-r3-actor@example.test', 'roler');
        $target = $this->member('ra-r3-target@example.test', 'admin');
        $this->member('ra-r3-other-admin@example.test', 'admin');

        $component = $this->edit($actor, $target)
            ->set('state.group_role', 'roler')
            ->call('updateUser')
            ->assertHasErrors(['users']);

        $this->assertSame(
            [__('group.error_no_right_to_remove_admin')],
            $component->lastErrorBag->get('users')
        );
        $this->assertSame('admin', $this->roleOf($target));
    }

    public function test_the_no_right_to_grant_admin_rule_is_unreachable(): void
    {
        // CHARACTERIZATION TEST: the fourth rule (:248-252,
        // group.error_no_right) is DEAD CODE.
        //
        // For it to run, a non-admin would have to submit the 'admin' role.
        // But 'admin' is not in the roler's maxRoles(), so the first rule
        // (:211-214) resets the value to the target's current role first -
        // so the :236 condition (pivot != state) becomes false, and the
        // whole after() block is skipped.
        //
        // Same pattern as the TODO 07.1 approval ceiling: the protection
        // works, but not through the gate the code's intent suggests - and
        // with a different (here: no) error message.
        $actor = $this->member('ra-r4-actor@example.test', 'roler');
        $target = $this->member('ra-r4-target@example.test', 'member');

        $component = $this->edit($actor, $target)
            ->set('state.group_role', 'admin')
            ->call('updateUser')
            ->assertHasNoErrors();

        $this->assertEmpty($component->lastErrorBag->get('users'));
        $this->assertSame('member', $this->roleOf($target));
    }

    // =========================================================================
    // 6. The remaining validated fields
    // =========================================================================

    public function test_the_pivot_fields_are_saved_together_with_the_role(): void
    {
        $actor = $this->member('ra-fields-actor@example.test', 'admin');
        $target = $this->member('ra-fields-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.group_role', 'helper')
            ->set('state.note', 'Megjegyzés a taghoz')
            ->set('state.hidden', 1)
            ->set('state.message_use', 2)
            ->set('state.message_send_priority', 1)
            ->call('updateUser')
            ->assertHasNoErrors();

        $pivot = $this->group->groupUsers()->where('user_id', $target->id)->firstOrFail()->pivot;

        $this->assertSame('helper', $pivot->group_role);
        $this->assertSame('Megjegyzés a taghoz', $pivot->note);
        $this->assertSame(1, (int) $pivot->hidden);
        $this->assertSame(2, (int) $pivot->message_use);
        $this->assertSame(1, (int) $pivot->message_send_priority);
    }

    public function test_the_note_is_stored_encrypted_in_the_pivot_table(): void
    {
        // group_user.note is under an encrypted cast (GroupUser.php:31-32),
        // and the save goes through syncWithoutDetaching() - meaning the
        // cast is applied via the pivot class. Also the subject of TODO 13.
        $actor = $this->member('ra-note-actor@example.test', 'admin');
        $target = $this->member('ra-note-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.note', 'Titkos jegyzet')
            ->call('updateUser')
            ->assertHasNoErrors();

        $raw = \Illuminate\Support\Facades\DB::table('group_user')
            ->where('group_id', $this->group->id)
            ->where('user_id', $target->id)
            ->value('note');

        $this->assertNotSame('Titkos jegyzet', $raw);
        $this->assertSame(
            'Titkos jegyzet',
            $this->group->groupUsers()->where('user_id', $target->id)->firstOrFail()->pivot->note
        );
    }

    public function test_the_note_is_limited_to_fifty_characters(): void
    {
        $actor = $this->member('ra-notelen-actor@example.test', 'admin');
        $target = $this->member('ra-notelen-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.note', str_repeat('a', 51))
            ->call('updateUser')
            ->assertHasErrors(['note']);
    }

    public function test_an_out_of_range_message_use_is_rejected(): void
    {
        $actor = $this->member('ra-mu-actor@example.test', 'admin');
        $target = $this->member('ra-mu-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.message_use', 3)
            ->call('updateUser')
            ->assertHasErrors(['message_use']);
    }

    public function test_an_unknown_role_is_silently_discarded_rather_than_rejected(): void
    {
        // Rule::In(self::$group_roles) only gets a say if the value
        // survived the first rule's reset - but a completely unknown role
        // never appears in maxRoles() either, so the silent reset wins here
        // too. The validation error therefore does NOT occur.
        $actor = $this->member('ra-unknown-actor@example.test', 'admin');
        $target = $this->member('ra-unknown-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.group_role', 'superadmin')
            ->call('updateUser')
            ->assertHasNoErrors();

        $this->assertSame('member', $this->roleOf($target));
    }

    public function test_the_finish_guest_registration_flag_never_reaches_the_pivot_table(): void
    {
        // CONFIRMED by the v1-patch B15 fix. The observable behaviour does
        // not change - the key never reached the pivot table before either -
        // but the WHY does, and that is why this test is worth reading.
        //
        // Previously updateUser() passed the FULL $validatedData to
        // syncWithoutDetaching(), and finish_guest_registration was always
        // in it (editUser() sets it). But the group_user table has no such
        // column. The save only did not fail because GroupUser is a custom
        // Pivot class with a $fillable list: on the
        // updateExistingPivotUsingCustomClass() branch, Laravel's fill()
        // SILENTLY dropped the unknown key. This is a path that depends on
        // the framework version - exactly the kind of thing an 8->13 jump
        // disturbs -, and on a raw update/insert fallback it would have
        // failed with an unknown-column error.
        //
        // The key is now stripped from the payload by updateUser() itself,
        // so the correct behaviour no longer depends on the Pivot class's
        // side effect.
        $actor = $this->member('ra-fgr-actor@example.test', 'admin');
        $target = $this->member('ra-fgr-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.note', 'Marad')
            ->call('updateUser')
            ->assertHasNoErrors();

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('group_user');
        $this->assertNotContains('finish_guest_registration', $columns);

        $this->assertSame(
            'Marad',
            $this->group->groupUsers()->where('user_id', $target->id)->firstOrFail()->pivot->note,
            'A többi pivot-mező viszont megérkezett.'
        );
    }

    public function test_the_updater_strips_the_flag_before_it_reaches_the_pivot_writer(): void
    {
        // The point of B15: cleaning the payload is the component's
        // responsibility, not a side effect of the GroupUser Pivot's
        // $fillable list. If this filtering is missing, a raw pivot write
        // fails with an "Unknown column" error - so we assert the source
        // too, not just the end result.
        $source = file_get_contents(app_path('Http/Livewire/Groups/ListUsers.php'));

        $this->assertStringContainsString(
            "unset(\$validatedData['finish_guest_registration']);",
            $source,
            'A kulcsot expliciten ki kell venni a pivot payloadból.'
        );
    }

    // =========================================================================
    // 7. Guest activation
    // =========================================================================

    private function guest(string $email): User
    {
        $user = $this->createUser([
            'email'             => $email,
            'role'              => 'registered',
            'email_verified_at' => null,
        ]);
        $this->attachUserToGroup($user, $this->group, 'member', false);

        return $user->fresh();
    }

    public function test_activating_a_guest_sets_the_role_verifies_the_email_and_accepts_the_membership(): void
    {
        Notification::fake();

        $actor = $this->member('ra-guest-actor@example.test', 'admin');
        $guest = $this->guest('ra-guest@example.test');
        $originalPassword = $guest->password;

        $this->edit($actor, $guest)
            ->set('state.finish_guest_registration', 1)
            ->call('updateUser')
            ->assertHasNoErrors();

        $activated = $guest->fresh();

        $this->assertSame('activated', $activated->role);
        $this->assertNotNull($activated->email_verified_at);
        $this->assertNotSame($originalPassword, $activated->password);
        $this->assertNotNull(
            $this->group->groupUsers()->where('user_id', $guest->id)->firstOrFail()->pivot->accepted_at,
            'A GroupUserMoves::acceptInvitation() elfogadta a tagságot.'
        );

        Notification::assertSentTo($activated, LoginData::class);
    }

    public function test_a_verified_user_cannot_be_run_through_the_guest_activation(): void
    {
        // Rule::In($finish_guest) (:216-220, :228-230) only allows the 1 if
        // the target's email_verified_at is null.
        $actor = $this->member('ra-verified-actor@example.test', 'admin');
        $target = $this->member('ra-verified-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.finish_guest_registration', 1)
            ->call('updateUser')
            ->assertHasErrors(['finish_guest_registration']);
    }

    public function test_the_role_switch_only_happens_for_registered_users(): void
    {
        // :293-294 ties two conditions together: the checkbox AND the
        // 'registered' role. For an already activated but not
        // email-verified user, the validator lets the 1 through, but the
        // side effect is skipped - password, role and membership stay
        // unchanged.
        Notification::fake();

        $actor = $this->member('ra-actrole-actor@example.test', 'admin');
        $target = $this->createUser([
            'email'             => 'ra-actrole-target@example.test',
            'role'              => 'activated',
            'email_verified_at' => null,
        ]);
        $this->attachUserToGroup($target, $this->group, 'member', false);
        $originalPassword = $target->password;

        $this->edit($actor, $target->fresh())
            ->set('state.finish_guest_registration', 1)
            ->call('updateUser')
            ->assertHasNoErrors();

        $after = $target->fresh();

        $this->assertSame('activated', $after->role);
        $this->assertNull($after->email_verified_at);
        $this->assertSame($originalPassword, $after->password);

        Notification::assertNothingSent();
    }

    // =========================================================================
    // 8. Profile modification and notification
    // =========================================================================

    public function test_changing_the_profile_updates_the_encrypted_columns_and_notifies_the_user(): void
    {
        Notification::fake();

        $actor = $this->member('ra-profile-actor@example.test', 'admin');
        $target = $this->member('ra-profile-target@example.test', 'member', [
            'name'         => 'Régi Név',
            'phone_number' => '36301111111',
            'congregation' => 'Régi Gyülekezet',
        ]);

        $this->edit($actor, $target)
            ->set('state.user.name', 'Új Név')
            ->set('state.user.congregation', 'Új Gyülekezet')
            ->call('updateUser')
            ->assertHasNoErrors();

        $updated = $target->fresh();

        $this->assertSame('Új Név', $updated->name);
        $this->assertSame('Új Gyülekezet', $updated->congregation);
        $this->assertSame('36301111111', $updated->phone_number);

        Notification::assertSentTo($updated, UserProfileChangedNotification::class);
    }

    public function test_no_notification_is_sent_when_the_profile_is_unchanged(): void
    {
        Notification::fake();

        $actor = $this->member('ra-nochange-actor@example.test', 'admin');
        $target = $this->member('ra-nochange-target@example.test', 'member', [
            'name' => 'Változatlan Név',
        ]);

        $this->edit($actor, $target)
            ->set('state.note', 'Csak a jegyzet változik')
            ->call('updateUser')
            ->assertHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_a_rejected_profile_leaves_no_partial_save_behind(): void
    {
        // REVERSED by the v1-patch B5 fix.
        //
        // updateUser() saved in two stages: the pivot data was already in
        // the database by the time the profile fields' validation ran, with
        // a SEPARATE Validator::make() and no transaction. An invalid name
        // therefore left a partial save behind - the note and the role got
        // saved, the profile did not -, and the user only saw an error
        // message, with no way of knowing what had stuck.
        //
        // Now all validation runs BEFORE the first write, and the rest
        // happens in a single transaction: either everything gets saved, or
        // nothing does.
        $actor = $this->member('ra-partial-actor@example.test', 'admin');
        $target = $this->member('ra-partial-target@example.test', 'member', [
            'name' => 'Eredeti Név',
        ]);

        $this->edit($actor, $target)
            ->set('state.note', 'Ez NEM mentődhet el')
            ->set('state.user.name', 'X')
            ->call('updateUser')
            ->assertHasErrors(['name']);

        $this->assertSame('Eredeti Név', $target->fresh()->name, 'A profil nem változott.');
        $this->assertNull(
            $this->group->groupUsers()->where('user_id', $target->id)->firstOrFail()->pivot->note,
            'És a pivot-adat sem - a mentés atomi.'
        );
    }

    public function test_a_valid_save_still_writes_both_halves(): void
    {
        // B5's control experiment: the transaction must not break the happy
        // path.
        $actor = $this->member('ra-atomic-actor@example.test', 'admin');
        $target = $this->member('ra-atomic-target@example.test', 'member', [
            'name' => 'Eredeti Név',
        ]);

        $this->edit($actor, $target)
            ->set('state.note', 'Ez elmentődik')
            ->set('state.user.name', 'Új Név')
            ->call('updateUser')
            ->assertHasNoErrors();

        $this->assertSame('Új Név', $target->fresh()->name);
        $this->assertSame(
            'Ez elmentődik',
            $this->group->groupUsers()->where('user_id', $target->id)->firstOrFail()->pivot->note
        );
    }

    public function test_a_non_numeric_phone_number_is_rejected(): void
    {
        $actor = $this->member('ra-phone-actor@example.test', 'admin');
        $target = $this->member('ra-phone-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.user.phone_number', 'nem-szam')
            ->call('updateUser')
            ->assertHasErrors(['phone_number']);
    }
}
