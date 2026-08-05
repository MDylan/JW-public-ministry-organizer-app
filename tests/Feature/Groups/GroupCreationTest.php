<?php

namespace Tests\Feature\Groups;

use App\Http\Livewire\Groups\ListGroups;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07.2: a csoportlétrehozás jogosultsági határai.
 *
 * A LivewireComponentInteractionTest:114 lefedi a happy path-et (a
 * groupCreator létrehoz egy csoportot és admin lesz benne), a köré épülő
 * jogosultsági határok viszont fedetlenek: a createGroup() abort(403)-ja
 * sosem fut le tesztben, és a name validáció sincs mérve.
 *
 * A requestGroupCreatorPrivilege() validációját a TODO 07 már lefedte
 * (tests/Feature/Livewire/ListGroupsTest.php:131-177); itt a levél
 * tartalmát rögzítjük, ami eddig kimaradt.
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
    // 1. A gate mint belépési feltétel
    // =========================================================================

    public function test_a_user_without_the_gate_cannot_create_a_group_even_by_calling_the_component_directly(): void
    {
        // A nézet elrejti a gombot a @can('is-groupcreator') mögé, de a
        // Livewire metódus a hálózatról közvetlenül is hívható - ezért van
        // a createGroup()-ban saját Gate::allows ellenőrzés (:204-206).
        // Ez az ág ma egyáltalán nem fut le tesztben.
        $user = $this->createUser(['role' => 'activated', 'email' => 'gc-denied@example.test']);

        $this->createAs($user, 'Tiltott csoport')->assertForbidden();

        $this->assertSame(0, Group::count());
    }

    /**
     * A groups.name oszlop encrypted cast alatt van, ezért az
     * assertDatabaseHas(['name' => ...]) sosem talál egyezést - a táblában
     * a titkosított sztring áll. A modellen keresztül kell olvasni.
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
        // Az is-groupcreator gate harmadik ága (AuthServiceProvider.php:38).
        // A szerep neve fordítási jogot sugall, mégis csoportot is létrehozhat.
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
    // 2. A name validáció
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
        // A validátor nem ír elő egyediséget (:208-210), és a name oszlop
        // encrypted, tehát adatbázis-szintű unique index sem lehet rajta.
        // Két azonos nevű csoport tehát megengedett - rögzítjük, mert ez
        // könnyen tűnhet hibának egy későbbi olvasónak.
        $creator = $this->createUser(['role' => 'groupCreator', 'email' => 'gc-dup@example.test']);

        $this->createAs($creator, 'Azonos név')->assertHasNoErrors();
        $this->createAs($creator, 'Azonos név')->assertHasNoErrors();

        $this->assertSame(2, $creator->fresh()->userGroups()->count());
    }

    // =========================================================================
    // 3. A létrehozás mellékhatásai
    // =========================================================================

    public function test_the_creator_becomes_an_accepted_admin_and_only_the_name_is_taken_from_the_state(): void
    {
        // A validátor csak a name-et engedi át, tehát a state többi kulcsa
        // (bármit is küld a kliens) nem kerül a groups táblába - a csoport
        // a migrációk alapértékeivel jön létre.
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
        // A létrehozás jogosultságot is termel: a friss admin tagságtól a
        // felhasználó átmegy az is-groupservant és is-groupadmin gate-eken,
        // amiken előtte nem ment át.
        $creator = $this->createUser(['role' => 'groupCreator', 'email' => 'gc-gates@example.test']);

        $this->assertFalse($creator->can('is-groupservant'));
        $this->assertFalse($creator->can('is-groupadmin'));

        $this->createAs($creator, 'Jogosultságot adó csoport')->assertHasNoErrors();

        $refreshed = $creator->fresh();
        $this->assertTrue($refreshed->can('is-groupservant'));
        $this->assertTrue($refreshed->can('is-groupadmin'));
    }

    // =========================================================================
    // 4. A jogosultság-igénylő levél tartalma
    // =========================================================================

    public function test_the_privilege_request_mail_goes_to_the_configured_address_and_replies_to_the_applicant(): void
    {
        // A ListGroupsTest a validációt és a sikeres lefutást már fedi; itt a
        // levél CÍMZÉSE a tárgy, mert az a Phase 4 (TODO 36) kockázata: a
        // Symfony Mailer szigorúbban validálja a címeket, mint a SwiftMailer,
        // és a replyTo felhasználói adatból jön.
        //
        // Mail::fake() itt nem használható: a MailFake::send() csak Mailable
        // példányt rögzít, a nyers Mail::send($view, $data, $closure) hívást
        // csendben eldobja. Ezért a phpunit.xml array-transportját olvassuk.
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
        // A congregation és a reason strip_tags()-en megy át (:78-79), mielőtt
        // a Blade-be kerül. A view {{ }}-t használ, tehát dupla védelem van;
        // a mérhető különbség az, hogy a címke nem escape-elve jelenik meg,
        // hanem el sem jut a Blade-ig.
        //
        // FONTOS: a strip_tags csak a CÍMKÉKET távolítja el, a tartalmukat nem.
        // Egy <script>alert(1)</script> beküldése után a levélben ott marad az
        // "alert(1)" szöveg - futtatható kód nélkül, de a tartalom megmarad.
        // Ez rögzítendő tény, nem hiba: aki erre később szanitizálást épít,
        // annak tudnia kell, hogy a strip_tags nem az.
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
     * A phpunit.xml MAIL_MAILER=array beállítású, így a kiment levelek az
     * ArrayTransport memóriájában maradnak.
     *
     * FIGYELEM: a visszaadott üzenetek Swift_Message példányok. A Laravel 9
     * Symfony Mailerre vált (TODO 36), ahol a getTo()/getReplyTo() Address
     * objektumok tömbjét adja, nem cím => név térképet - ezt a két assertiont
     * ott át kell írni. Ez a teszt épp ezért hasznos ott: jelezni fogja.
     */
    private function sentMessages()
    {
        return Mail::getSwiftMailer()->getTransport()->messages();
    }
}
