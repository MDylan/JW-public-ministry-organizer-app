<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\GroupDelete;
use App\Http\Livewire\Groups\NewsEdit;
use App\Models\Group;
use App\Models\GroupNews;
use App\Models\GroupUser;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 70 (ported to the v1 line by TODO 70.1): news are created, edited and deleted only in the group
 * whose page the editor is on, by a current editor of that group.
 *
 * Each case was an attack that worked before (a throwaway probe measured
 * it): the news form deleted whatever state.id named, and its group, item
 * and file lists were the client's to rewrite; the delete route looked the
 * item up in every group; a refused group deletion still detached the
 * group's children.
 */
class NewsAuthorizationTest extends FeatureTestCase
{
    private Group $own;
    private Group $other;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->own = $this->createGroup();
        $this->other = $this->createGroup();
        $this->admin = $this->createUser(['email' => 'na-admin@example.test']);
        $this->attachUserToGroup($this->admin, $this->own, 'admin');
    }

    private function foreignNews(): GroupNews
    {
        return $this->createGroupNews($this->other, $this->createUser(['email' => 'na-author-'.uniqid().'@example.test']));
    }

    private function form(GroupNews $news)
    {
        return Livewire::actingAs($this->admin)->test(NewsEdit::class, ['group' => $this->own->id, 'new' => $news->id]);
    }

    public function test_the_forms_group_item_and_file_lists_cannot_be_written_by_the_client(): void
    {
        $own = $this->createGroupNews($this->own, $this->admin);

        foreach (['groupId' => $this->other->id, 'newId' => 1, 'removed_files' => [['id' => 1, 'file' => 'x']], 'attached_files' => []] as $property => $value) {
            $this->form($own)->set($property, $value)->assertForbidden();
        }
    }

    public function test_the_form_deletes_its_own_item_not_the_one_state_id_names(): void
    {
        $own = $this->createGroupNews($this->own, $this->admin);
        $foreign = $this->foreignNews();

        $this->form($own)->set('state.id', $foreign->id)->call('deleteConfirmed');

        $this->assertNotNull(GroupNews::find($foreign->id));
        $this->assertNull(GroupNews::find($own->id));
    }

    public function test_a_language_key_cannot_move_the_item_to_another_group(): void
    {
        $own = $this->createGroupNews($this->own, $this->admin);

        $this->form($own)
            ->set('state.lang.group_id', $this->other->id)
            ->call('editNews');

        $this->assertSame($this->own->id, (int) $own->fresh()->group_id);
    }

    public function test_a_demoted_editor_is_refused_on_the_next_action(): void
    {
        $own = $this->createGroupNews($this->own, $this->admin);
        $form = $this->form($own);

        GroupUser::where('group_id', $this->own->id)->where('user_id', $this->admin->id)->update(['group_role' => 'member']);

        $form->call('editNews')->assertForbidden();
    }

    public function test_the_form_does_not_open_for_another_group(): void
    {
        Livewire::actingAs($this->admin)->test(NewsEdit::class, ['group' => $this->other->id])->assertForbidden();
    }

    public function test_the_delete_route_finds_news_only_in_its_own_group(): void
    {
        $foreign = $this->foreignNews();
        $own = $this->createGroupNews($this->own, $this->admin);

        $this->actingAs($this->admin)->withSession($this->passwordConfirmedSession())
            ->delete(route('groups.news_delete', ['group' => $this->own->id, 'new' => $foreign->id]))
            ->assertNotFound();
        $this->assertNotNull(GroupNews::find($foreign->id));

        $this->actingAs($this->admin)->withSession($this->passwordConfirmedSession())
            ->delete(route('groups.news_delete', ['group' => $this->own->id, 'new' => $own->id]))
            ->assertRedirect(route('groups.news', ['group' => $this->own->id]));
        $this->assertNull(GroupNews::find($own->id));
    }

    public function test_a_refused_group_deletion_leaves_the_child_groups_attached(): void
    {
        $roler = $this->createUser(['email' => 'na-roler@example.test']);
        $this->attachUserToGroup($roler, $this->own, 'roler');
        $child = $this->createChildGroup($this->own);

        $this->actingAs($roler);
        (new GroupDelete())->index($this->own->id);

        $this->assertNotNull(Group::find($this->own->id));
        $this->assertSame($this->own->id, (int) $child->fresh()->parent_group_id);
    }
}
