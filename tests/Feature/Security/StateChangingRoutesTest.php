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
}
