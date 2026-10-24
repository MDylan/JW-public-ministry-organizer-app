<?php

namespace Tests\Feature\Security;

use App\Models\GroupNews;
use App\Models\GroupUser;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 70 (ported to the v1 line by TODO 70.1): leaving a group and deleting a news item are no longer
 * GET requests.
 *
 * Both were GET routes behind password.confirm, so within the confirmation
 * window a link from another site (a top-level navigation carries the
 * SameSite=Lax session cookie) made the user leave a group - with its child
 * groups - or delete a news item. They are POST and DELETE now, with CSRF;
 * an expired confirmation returns to the page the action belongs to, not to
 * the action, which the confirmation would replay as a GET.
 */
class StateChangingRoutesTest extends FeatureTestCase
{
    public function test_a_get_request_no_longer_leaves_a_group_or_deletes_news(): void
    {
        $group = $this->createGroup();
        $admin = $this->createUser(['email' => 'sc-admin@example.test']);
        $this->attachUserToGroup($admin, $group, 'admin');
        $this->attachUserToGroup($this->createUser(['email' => 'sc-admin2@example.test']), $group, 'admin');
        $news = $this->createGroupNews($group, $admin);

        $this->actingAs($admin)->withSession($this->passwordConfirmedSession())
            ->get('/groups/'.$group->id.'/logout')
            ->assertStatus(405);
        $this->actingAs($admin)->withSession($this->passwordConfirmedSession())
            ->get('/groups/'.$group->id.'/news/delete/'.$news->id)
            ->assertStatus(405);

        $this->assertSame(1, GroupUser::where('group_id', $group->id)->where('user_id', $admin->id)->count());
        $this->assertNotNull(GroupNews::find($news->id));
    }

    public function test_an_unconfirmed_exit_returns_to_the_groups_page(): void
    {
        $group = $this->createGroup();
        $member = $this->createUser(['email' => 'sc-member@example.test']);
        $this->attachUserToGroup($member, $group);

        $this->actingAs($member)
            ->post(route('groups.logout', ['group' => $group->id]))
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas('url.intended', route('groups'));

        $this->assertSame(1, GroupUser::where('group_id', $group->id)->where('user_id', $member->id)->count());
    }

    public function test_an_unconfirmed_deletion_returns_to_the_news_items_page(): void
    {
        $group = $this->createGroup();
        $admin = $this->createUser(['email' => 'sc-news@example.test']);
        $this->attachUserToGroup($admin, $group, 'admin');
        $news = $this->createGroupNews($group, $admin);

        $this->actingAs($admin)
            ->delete(route('groups.news_delete', ['group' => $group->id, 'new' => $news->id]))
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas('url.intended', route('groups.news_edit', ['group' => $group->id, 'new' => $news->id]));

        $this->assertNotNull(GroupNews::find($news->id));
    }

    // --- after the confirmation, the page offers the action again (TODO 70.1) ---
    //
    // Tested at 1.2.1: the password page returned to the groups page and
    // nothing said the exit had not happened. The page now reopens its own
    // confirmation dialog, once.

    public function test_the_groups_page_reopens_the_exit_dialog_after_the_password(): void
    {
        $group = $this->createGroup();
        $member = $this->createUser(['email' => 'sc-resume@example.test']);
        $this->attachUserToGroup($member, $group);

        $this->actingAs($member)->post(route('groups.logout', ['group' => $group->id]))
            ->assertRedirect(route('password.confirm'));
        $this->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect(route('groups'));

        $this->get(route('groups'))
            ->assertOk()
            ->assertSee("new CustomEvent('show-logout-confirmation', { detail: { groupId: ".$group->id.' } })', false);

        // Once: a reload is an ordinary groups page again.
        $this->get(route('groups'))->assertDontSee("new CustomEvent('show-logout-confirmation'", false);
        $this->assertSame(1, GroupUser::where('group_id', $group->id)->where('user_id', $member->id)->count());
    }

    public function test_the_exit_dialog_is_not_reopened_for_the_last_admin(): void
    {
        $group = $this->createGroup();
        $admin = $this->createUser(['email' => 'sc-resume-admin@example.test']);
        $this->attachUserToGroup($admin, $group, 'admin');

        $this->actingAs($admin)->post(route('groups.logout', ['group' => $group->id]));
        $this->post(route('password.confirm.store'), ['password' => 'password']);

        $this->get(route('groups'))->assertDontSee("new CustomEvent('show-logout-confirmation'", false);
    }

    public function test_an_abandoned_confirmation_reopens_nothing(): void
    {
        $group = $this->createGroup();
        $member = $this->createUser(['email' => 'sc-resume-abandoned@example.test']);
        $this->attachUserToGroup($member, $group);

        // The exit asked for the password, which was never given.
        $this->actingAs($member)->post(route('groups.logout', ['group' => $group->id]));

        $this->get(route('groups'))->assertDontSee("new CustomEvent('show-logout-confirmation'", false);
    }

    public function test_the_news_form_reopens_the_delete_dialog_after_the_password(): void
    {
        $group = $this->createGroup();
        $admin = $this->createUser(['email' => 'sc-resume-news@example.test']);
        $this->attachUserToGroup($admin, $group, 'admin');
        $news = $this->createGroupNews($group, $admin);
        $other = $this->createGroupNews($group, $admin);

        $this->actingAs($admin)->delete(route('groups.news_delete', ['group' => $group->id, 'new' => $news->id]));
        $this->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect(route('groups.news_edit', ['group' => $group->id, 'new' => $news->id]));

        // Another item's page does not take it...
        $this->get(route('groups.news_edit', ['group' => $group->id, 'new' => $other->id]))
            ->assertDontSee("new CustomEvent('show-newsDelete-confirmation')", false);
    }

    public function test_the_news_form_reopens_the_dialog_on_its_own_page(): void
    {
        $group = $this->createGroup();
        $admin = $this->createUser(['email' => 'sc-resume-news2@example.test']);
        $this->attachUserToGroup($admin, $group, 'admin');
        $news = $this->createGroupNews($group, $admin);

        $this->actingAs($admin)->delete(route('groups.news_delete', ['group' => $group->id, 'new' => $news->id]));
        $this->post(route('password.confirm.store'), ['password' => 'password']);

        $this->get(route('groups.news_edit', ['group' => $group->id, 'new' => $news->id]))
            ->assertSee("new CustomEvent('show-newsDelete-confirmation')", false);
        $this->assertNotNull(GroupNews::find($news->id));
    }

    public function test_the_user_list_says_to_click_again_after_the_password(): void
    {
        $admin = $this->createUser(['email' => 'sc-resume-imp@example.test', 'role' => 'mainAdmin']);
        $target = $this->createUser(['email' => 'sc-resume-target@example.test', 'name' => 'Target Person']);

        $this->actingAs($admin)->post(route('admin.users.login', ['user' => $target->id]))
            ->assertRedirect(route('password.confirm'));
        $this->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect(route('admin.users'));

        $this->get(route('admin.users'))
            ->assertSee(__('user.login_to_retry', ['name' => 'Target Person']));
        $this->assertSame($admin->id, auth()->id(), 'The identity must not have switched.');
    }
}
