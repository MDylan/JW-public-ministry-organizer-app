<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Groups\ListGroups;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07: the uncovered half of Groups\ListGroups.
 *
 * The existing LivewireComponentInteractionTest covers accepting and rejecting
 * invitations plus the group-creation happy path. This file covers the rest:
 * leaving a group, deleting one, and requesting the group-creator privilege.
 *
 * The authorization boundaries around createGroup() belong to TODO 07.2.
 */
class ListGroupsTest extends FeatureTestCase
{
    private Group $group;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->admin = $this->createUser(['email' => 'lg-admin@example.test', 'role' => 'groupCreator']);
        $this->attachUserToGroup($this->admin, $this->group, 'admin');
        $this->actingAs($this->admin);
    }

    // --- kilépés a csoportból ---

    public function test_leaving_is_refused_when_no_other_admin_remains(): void
    {
        // A pwbs_check_group_other_admins védelme: az utolsó adminisztrátor
        // nem hagyhatja magára a csoportot.
        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('confirmLogoutModal', $this->group->id)
            ->assertDispatchedBrowserEvent('sweet-error')
            ->assertSet('groupBeeingLogout', null);
    }

    public function test_leaving_is_offered_when_another_admin_exists(): void
    {
        $secondAdmin = $this->createUser(['email' => 'lg-admin2@example.test']);
        $this->attachUserToGroup($secondAdmin, $this->group, 'admin');

        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('confirmLogoutModal', $this->group->id)
            ->assertDispatchedBrowserEvent('show-logout-confirmation')
            ->assertSet('groupBeeingLogout', $this->group->id);
    }

    public function test_confirming_the_exit_detaches_the_membership(): void
    {
        $secondAdmin = $this->createUser(['email' => 'lg-admin3@example.test']);
        $this->attachUserToGroup($secondAdmin, $this->group, 'admin');

        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('confirmLogoutModal', $this->group->id)
            ->call('logoutConfirmed')
            ->assertDispatchedBrowserEvent('success');

        $this->assertSame(
            0,
            GroupUser::where('group_id', $this->group->id)->where('user_id', $this->admin->id)->count()
        );
    }

    public function test_confirming_the_exit_is_re_checked_and_refused_without_another_admin(): void
    {
        // A védelem a megerősítéskor is lefut, nem csak a modal nyitásakor -
        // így egy közben eltávolított társadmin sem nyit rést.
        $secondAdmin = $this->createUser(['email' => 'lg-admin4@example.test']);
        $this->attachUserToGroup($secondAdmin, $this->group, 'admin');

        $component = Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('confirmLogoutModal', $this->group->id);

        // A másik admin időközben kilép.
        GroupUser::where('group_id', $this->group->id)->where('user_id', $secondAdmin->id)->delete();

        $component->call('logoutConfirmed')->assertDispatchedBrowserEvent('sweet-error');

        $this->assertSame(
            1,
            GroupUser::where('group_id', $this->group->id)->where('user_id', $this->admin->id)->count()
        );
    }

    // --- csoport törlése ---

    public function test_group_deletion_is_not_available_from_this_component(): void
    {
        // A confirmGroupRemoval() és deleteGroup() metódusok ki vannak
        // kommentelve (ListGroups.php:26-49), a $listeners tömbben a
        // 'deleteGroup' bejegyzés szintén. A törlés a Groups\DeleteGroup
        // komponensen keresztül történik, amit a GroupComponentsTest fed.
        //
        // Ez a teszt rögzíti a jelenlegi állapotot: ha valaki visszakapcsolja
        // a metódusokat, itt derül ki, hogy a viselkedést újra kell gondolni.
        $this->expectException(\Livewire\Exceptions\MethodNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('confirmGroupRemoval', $this->group->id);
    }

    // --- csoportlétrehozói jogosultság igénylése ---

    public function test_requesting_the_creator_privilege_opens_an_empty_form(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->set('state.congregation', 'valami')
            ->call('askGroupCreatorPrivilege')
            ->assertSet('state', [])
            ->assertDispatchedBrowserEvent('show-modal');
    }

    public function test_the_privilege_request_validates_its_fields(): void
    {
        Mail::fake();
        $this->admin->update(['phone_number' => '36301234567']);

        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->set('state.congregation', 'ab')
            ->set('state.reason', 'rovid')
            ->call('requestGroupCreatorPrivilege')
            ->assertHasErrors(['congregation', 'reason']);

        Mail::assertNothingSent();
    }

    public function test_the_privilege_request_succeeds_and_closes_the_modal(): void
    {
        $this->admin->update(['phone_number' => '36301234567']);

        // Nem Mail::fake(): a metódus nyers Mail::send()-et hív egy view-val,
        // nem Mailable osztályt, ezért a MailFake assertSent() nem tudja
        // elkapni. A phpunit.xml MAIL_MAILER=array beállítású, tehát a levél
        // sehova nem megy ki - itt azt igazoljuk, hogy a küldés hiba nélkül
        // lefut és a modal bezárul.
        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->set('state.congregation', 'Példa Gyülekezet')
            ->set('state.reason', 'Szeretnék új csoportot létrehozni a városrészünkben.')
            ->call('requestGroupCreatorPrivilege')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('hide-modal');
    }

    public function test_the_privilege_request_requires_a_numeric_phone_number(): void
    {
        Mail::fake();
        // A telefonszám nem a formból jön, hanem a profilból - üres profil
        // esetén a kérés elbukik a validáción.
        $this->admin->update(['phone_number' => null]);

        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->set('state.congregation', 'Példa Gyülekezet')
            ->set('state.reason', 'Szeretnék új csoportot létrehozni a városrészünkben.')
            ->call('requestGroupCreatorPrivilege')
            ->assertHasErrors(['phone']);
    }

    // --- modal segéd ---

    public function test_open_modal_dispatches_the_browser_event(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('openModal', 'createGroup')
            ->assertDispatchedBrowserEvent('show-modal');
    }
}
