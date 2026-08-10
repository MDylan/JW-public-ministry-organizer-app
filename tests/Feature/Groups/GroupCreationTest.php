<?php

namespace Tests\Feature\Groups;

use App\Http\Livewire\Groups\ListGroups;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07.2: the authorization boundaries of group creation.
 *
 * LivewireComponentInteractionTest:114 covers the happy path (the
 * groupCreator creates a group and becomes its admin), but the authorization
 * boundaries built around it are uncovered: createGroup()'s abort(403)
 * never runs in a test, and the name validation is not measured either.
 *
 * TODO 07 already covered the validation of requestGroupCreatorPrivilege()
 * (tests/Feature/Livewire/ListGroupsTest.php:131-177); here we record the
 * content of the email, which was missing until now.
 */
class GroupCreationTest extends FeatureTestCase
{
    private function createAs(User $user, string $name)
    {
        return Livewire::actingAs($user)
            ->test(ListGroups::class)
            ->set('state.name', $name)
            ->call('createGroup');
    }

    // =========================================================================
    // 1. The gate as an entry condition
    // =========================================================================

    public function test_a_user_without_the_gate_cannot_create_a_group_even_by_calling_the_component_directly(): void
    {
        // The view hides the button behind @can('is-groupcreator'), but the
        // Livewire method can also be called directly over the network - that's
        // why createGroup() has its own Gate::allows check (:204-206).
        // This branch currently doesn't run in any test at all.
        $user = $this->createUser(['role' => 'activated', 'email' => 'gc-denied@example.test']);

        $this->createAs($user, 'Tiltott csoport')->assertForbidden();

        $this->assertSame(0, Group::count());
    }

    /**
     * The groups.name column is under an encrypted cast, so
     * assertDatabaseHas(['name' => ...]) never finds a match - the table
     * holds the encrypted string. It must be read through the model.
     */
    private function lastGroupOf(User $user): Group
    {
        return $user->fresh()->userGroups()->orderByDesc('groups.id')->firstOrFail();
    }

    public function test_a_registered_user_cannot_create_a_group(): void
    {
        $user = $this->createUser(['role' => 'registered', 'email' => 'gc-registered@example.test']);

        $this->createAs($user, 'Regisztrált csoport')->assertForbidden();
    }

    public function test_a_translator_can_create_a_group(): void
    {
        // The third branch of the is-groupcreator gate (AuthServiceProvider.php:38).
        // The role's name suggests a translation privilege, yet it can also create a group.
        $translator = $this->createUser(['role' => 'translator', 'email' => 'gc-translator@example.test']);

        $this->createAs($translator, 'Fordítói csoport')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('hide-modal');

        $this->assertSame('Fordítói csoport', $this->lastGroupOf($translator)->name);
    }

    public function test_a_main_admin_can_create_a_group(): void
    {
        $admin = $this->createUser(['role' => 'mainAdmin', 'email' => 'gc-mainadmin@example.test']);

        $this->createAs($admin, 'Admin csoport')->assertHasNoErrors();

        $this->assertSame('Admin csoport', $this->lastGroupOf($admin)->name);
    }

    // =========================================================================
    // 2. The name validation
    // =========================================================================

    public function test_the_group_name_is_required(): void
    {
        $creator = $this->createUser(['role' => 'groupCreator', 'email' => 'gc-req@example.test']);

        Livewire::actingAs($creator)
            ->test(ListGroups::class)
            ->set('state.name', '')
            ->call('createGroup')
            ->assertHasErrors(['name']);
    }

    public function test_the_group_name_must_be_at_least_two_characters(): void
    {
        $creator = $this->createUser(['role' => 'groupCreator', 'email' => 'gc-min@example.test']);

        $this->createAs($creator, 'A')->assertHasErrors(['name']);
        $this->createAs($creator, 'AB')->assertHasNoErrors();
    }

    public function test_the_group_name_must_not_exceed_fifty_characters(): void
    {
        $creator = $this->createUser(['role' => 'groupCreator', 'email' => 'gc-max@example.test']);

        $this->createAs($creator, str_repeat('a', 51))->assertHasErrors(['name']);
        $this->createAs($creator, str_repeat('b', 50))->assertHasNoErrors();
    }

    public function test_duplicate_group_names_are_allowed(): void
    {
        // The validator does not require uniqueness (:208-210), and the name
        // column is encrypted, so a database-level unique index isn't possible
        // on it either. Two identically named groups are therefore allowed - we
        // record this because it can easily look like a bug to a later reader.
        $creator = $this->createUser(['role' => 'groupCreator', 'email' => 'gc-dup@example.test']);

        $this->createAs($creator, 'Azonos név')->assertHasNoErrors();
        $this->createAs($creator, 'Azonos név')->assertHasNoErrors();

        $this->assertSame(2, $creator->fresh()->userGroups()->count());
    }

    // =========================================================================
    // 3. The side effects of creation
    // =========================================================================

    public function test_the_creator_becomes_an_accepted_admin_and_only_the_name_is_taken_from_the_state(): void
    {
        // The validator only lets name through, so the rest of the state's
        // keys (whatever the client sends) do not make it into the groups
        // table - the group is created with the migrations' defaults.
        $creator = $this->createUser(['role' => 'groupCreator', 'email' => 'gc-defaults@example.test']);

        Livewire::actingAs($creator)
            ->test(ListGroups::class)
            ->set('state.name', 'Alapértelmezett csoport')
            ->set('state.max_publishers', 99)
            ->set('state.need_approval', 1)
            ->call('createGroup')
            ->assertHasNoErrors();

        $group = $creator->fresh()->userGroups()->orderByDesc('groups.id')->first();

        $this->assertSame('Alapértelmezett csoport', $group->name);
        $this->assertSame('admin', $group->pivot->group_role);
        $this->assertNotNull($group->pivot->accepted_at);

        $fresh = Group::find($group->id);
        $this->assertNotSame(99, $fresh->max_publishers);
        $this->assertNotSame(1, (int) $fresh->need_approval);
    }

    public function test_the_new_group_makes_its_creator_a_group_servant_and_group_admin(): void
    {
        // Creation also produces authorization: with the fresh admin
        // membership the user now passes the is-groupservant and
        // is-groupadmin gates, which they did not pass before.
        $creator = $this->createUser(['role' => 'groupCreator', 'email' => 'gc-gates@example.test']);

        $this->assertFalse($creator->can('is-groupservant'));
        $this->assertFalse($creator->can('is-groupadmin'));

        $this->createAs($creator, 'Jogosultságot adó csoport')->assertHasNoErrors();

        $refreshed = $creator->fresh();
        $this->assertTrue($refreshed->can('is-groupservant'));
        $this->assertTrue($refreshed->can('is-groupadmin'));
    }

    // =========================================================================
    // 4. The content of the privilege-request email
    // =========================================================================

    public function test_the_privilege_request_mail_goes_to_the_configured_address_and_replies_to_the_applicant(): void
    {
        // ListGroupsTest already covers the validation and the successful run;
        // here the ADDRESSING of the email is the subject, because that is the
        // Phase 4 (TODO 36) risk: Symfony Mailer validates addresses more
        // strictly than SwiftMailer, and replyTo comes from user-supplied data.
        //
        // Mail::fake() cannot be used here: MailFake::send() only records
        // Mailable instances, it silently drops the raw
        // Mail::send($view, $data, $closure) call. So we read the phpunit.xml
        // array transport instead.
        $applicant = $this->createUser([
            'role'         => 'activated',
            'email'        => 'applicant@example.test',
            'phone_number' => '36301234567',
        ]);

        Livewire::actingAs($applicant)
            ->test(ListGroups::class)
            ->set('state.congregation', 'Példa Gyülekezet')
            ->set('state.reason', 'Szeretnék új csoportot létrehozni a városrészünkben.')
            ->call('requestGroupCreatorPrivilege')
            ->assertHasNoErrors();

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);

        $message = $messages->first();

        $this->assertArrayHasKey(config('mail.from.address'), $message->getTo());
        $this->assertArrayHasKey('applicant@example.test', $message->getReplyTo());
        $this->assertSame(__('group.requestMail.subject'), $message->getSubject());
    }

    public function test_the_privilege_request_mail_strips_tags_from_user_supplied_text(): void
    {
        // congregation and reason go through strip_tags() (:78-79) before
        // reaching the Blade template. The view uses {{ }}, so there is double
        // protection; the measurable difference is that the tag doesn't appear
        // un-escaped, it never even reaches Blade.
        //
        // IMPORTANT: strip_tags only removes the TAGS, not their content.
        // After submitting <script>alert(1)</script>, the email still contains
        // the "alert(1)" text - without executable code, but the content remains.
        // This is a fact to record, not a bug: whoever later builds sanitization
        // on top of this needs to know that strip_tags is not that.
        $applicant = $this->createUser([
            'role'         => 'activated',
            'email'        => 'applicant-xss@example.test',
            'phone_number' => '36301234567',
        ]);

        Livewire::actingAs($applicant)
            ->test(ListGroups::class)
            ->set('state.congregation', '<b>Vastag</b> Gyülekezet')
            ->set('state.reason', '<script>alert(1)</script> Ez egy elég hosszú indoklás.')
            ->call('requestGroupCreatorPrivilege')
            ->assertHasNoErrors();

        $body = $this->sentMessages()->first()->getBody();

        $this->assertStringContainsString('Vastag Gyülekezet', $body);
        $this->assertStringNotContainsString('<b>', $body);
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('alert(1)', $body);
    }

    /**
     * phpunit.xml is configured with MAIL_MAILER=array, so sent emails
     * remain in the ArrayTransport's memory.
     *
     * ATTENTION: the returned messages are Swift_Message instances. Laravel 9
     * switches to Symfony Mailer (TODO 36), where getTo()/getReplyTo() returns
     * an array of Address objects, not an address => name map - the two
     * assertions must be rewritten there. This test is useful there for
     * exactly this reason: it will flag it.
     */
    private function sentMessages()
    {
        return Mail::getSwiftMailer()->getTransport()->messages();
    }
}
