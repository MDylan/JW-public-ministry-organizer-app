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
 * TODO 07.2: a csoporton belüli szerepkiosztás.
 *
 * A Groups\ListUsers::updateUser() (:201-320) nagyjából 120 sor jogosultsági
 * logika, amit ma csak egy "a route 200-at ad" füstteszt érint. Ez a kód
 * dönti el, ki kit léptethet elő vagy vissza egy csoportban.
 *
 * A roadmap ezt a metódust saveUser() néven említi - olyan metódus nincs a
 * komponensben; a szerkesztés editUser() -> updateUser() páron megy.
 *
 * Négy szabály él benne, és MINDHÁROM validátor-szabály csak akkor fut le,
 * ha a szerep ténylegesen változik (:236). Ez fedési viszonyt teremt az
 * első szabállyal - lásd a 4. szakaszt.
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
    // 1. Belépési jogosultság
    // =========================================================================

    public function test_a_plain_member_cannot_open_the_user_editor(): void
    {
        $actor = $this->member('ra-member@example.test', 'member');
        $target = $this->member('ra-target1@example.test', 'member');

        $this->edit($actor, $target)->assertForbidden();
    }

    public function test_a_helper_cannot_open_the_user_editor_either(): void
    {
        // KARAKTERIZÁLÓ TESZT: az isNotHelper() (:796-798) szó szerint azonos
        // az isNotEditor()-ral (:792-794) - mindkettő csak ['admin','roler']-t
        // enged. A 'helper' szerep tehát nem helper a metódus értelmében.
        //
        // Következmény: a maxRoles() (:808-816) a gyakorlatban csak két
        // értéket adhat vissza, ['member','helper','roler'] (roler) vagy
        // mind a négy (admin); a 'member' és a 'helper' ága elérhetetlen.
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
        // TODO 10 javította a getRole()-t (:800-806): korábban
        // ->first()->toArray()-jel zárt, tehát tagsági sor nélkül fatalt
        // dobott, MÉG MIELŐTT az isNotHelper() 403-at adhatott volna.
        //
        // A route-on ott van a groupMember middleware, tehát ez nem volt
        // aktív biztonsági rés - de egy 500-as hiba tartotta zárva a
        // komponenst, ami nem védelem. Most a getRole() maga zár.
        //
        // Fontos, hogy miért nem a role null-ra hagyása a helyes megoldás: a
        // render() nem ellenőriz jogosultságot, csak $editor-t számol, tehát
        // a kívülálló lerenderelné a taglistát nem-szerkesztőként.
        $outsider = $this->createUser(['email' => 'ra-outsider@example.test']);

        Livewire::actingAs($outsider)
            ->test(ListUsers::class, ['group' => $this->group->id])
            ->assertForbidden();
    }

    // =========================================================================
    // 2. Az editUser() által épített state - az updateUser() bemeneti szerződése
    // =========================================================================

    public function test_edit_user_builds_the_full_state_the_updater_expects(): void
    {
        // Az updateUser() feltételezi, hogy minden kulcs jelen van; a
        // validátor szabályai (hidden => required, finish_guest_registration
        // => Rule::In) hiányzó kulcsra máshogy viselkednek. A state alakja
        // tehát szerződés a két metódus között.
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
    // 3. Első szabály: csendes visszaállítás a hatókörön kívüli szerepnél
    // =========================================================================

    public function test_a_roler_granting_the_admin_role_is_silently_reset_without_any_error(): void
    {
        // EZ A LEGVESZÉLYESEBB SZABÁLY. A maxRoles() (:808-816) végigmegy a
        // ['member','helper','roler','admin'] listán, és megáll a hívó saját
        // szerepénél - egy roler tehát nem adhat admin szerepet.
        //
        // A megvalósítás viszont NEM utasít el: a :211-214 némán
        // visszaállítja a beküldött értéket a célszemély JELENLEGI
        // szerepére, majd a mentés hiba nélkül lefut. A felhasználó
        // "mentve" visszajelzést kap, miközben nem történt semmi.
        //
        // Egy upgrade során ez úgy törhet el, hogy a validáció zöld marad,
        // csak a visszaállítás marad el - és onnantól bárki bármilyen
        // szerepet adhat. Ezért van rá önálló teszt.
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
    // 4. Második szabály: az utolsó adminisztrátor védelme
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
        // A pwbs_check_group_other_admins() (helpers.php:24-61) a szülő
        // mellett a gyermekcsoportokat is átnézi, és azt kérdezi, van-e
        // olyan MÁSIK adminisztrátor, aki MINDEGYIK csoportban admin
        // (array_search($total_group, $main_admins, true)).
        //
        // Itt a második admin csak a szülőcsoportban admin, a gyermekben
        // nem - ezért a visszaminősítés tiltott, pedig a szülőcsoportot
        // önmagában nézve maradna adminisztrátor. Ez az ág eddig
        // lefedetlen volt.
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
    // 5. Harmadik és negyedik szabály - és a köztük lévő aszimmetria
    // =========================================================================

    public function test_a_roler_cannot_take_the_admin_role_away_from_someone(): void
    {
        // A harmadik szabály (:243-247) ELÉRHETŐ, mert a lefokozás célértéke
        // ('roler') a roler saját hatókörén BELÜL van - így az első szabály
        // nem állítja vissza, a szerep ténylegesen változna, és a validátor
        // after() blokkja lefut.
        //
        // A második adminisztrátor azért kell, hogy a második szabály
        // (error_no_admin_user) ne fedje el ezt a hibát.
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
        // KARAKTERIZÁLÓ TESZT: a negyedik szabály (:248-252,
        // group.error_no_right) HOLT KÓD.
        //
        // Ahhoz, hogy lefusson, egy nem-adminnak 'admin' szerepet kellene
        // beküldenie. De az 'admin' nincs benne a roler maxRoles()-ában,
        // ezért az első szabály (:211-214) előbb visszaállítja az értéket a
        // célszemély jelenlegi szerepére - így a :236 feltétele
        // (pivot != state) hamis lesz, és az egész after() blokk kimarad.
        //
        // Ugyanaz a mintázat, mint a TODO 07.1 jóváhagyási plafonjánál: a
        // védelem működik, de nem azon a kapun, amelyiken a kód szándéka
        // szerint - és más (itt: semmilyen) hibaüzenettel.
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
    // 6. A többi validált mező
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
        // A group_user.note encrypted cast alatt van (GroupUser.php:31-32),
        // és a mentés a syncWithoutDetaching() útján megy - vagyis a cast a
        // pivot osztályon keresztül érvényesül. TODO 13 tárgya is.
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
        // A Rule::In(self::$group_roles) csak akkor kap szót, ha az érték
        // túlélte az első szabály visszaállítását - egy teljesen ismeretlen
        // szerep viszont sosem szerepel a maxRoles()-ban, ezért itt is a
        // csendes visszaállítás nyer. A validációs hiba tehát NEM jön elő.
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
        // MEGERŐSÍTVE a v1-patch B15 javításával. A megfigyelhető viselkedés
        // nem változik - a kulcs eddig sem került a pivot táblába -, de az OK
        // igen, és ezért érdemes ezt a tesztet elolvasni.
        //
        // Korábban az updateUser() a TELJES $validatedData-t adta a
        // syncWithoutDetaching()-nek, és a finish_guest_registration mindig
        // benne volt (az editUser() beállítja). A group_user táblában viszont
        // nincs ilyen oszlop. A mentés kizárólag azért nem hasalt el, mert a
        // GroupUser egyedi Pivot osztály $fillable listával: a Laravel az
        // updateExistingPivotUsingCustomClass() ágon fill()-lel CSENDBEN
        // eldobta az ismeretlen kulcsot. Ez keretrendszer-verziótól függő
        // útvonal - pontosan az a fajta, amit egy 8->13 ugrás megpiszkál -, és
        // egy nyers update/insert fallbacken ismeretlen oszlop hibával járt
        // volna.
        //
        // A kulcsot most az updateUser() maga veszi ki a payloadból, tehát a
        // helyes viselkedés nem a Pivot osztály mellékhatásán múlik.
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
        // A B15 lényege: a payload tisztítása a komponens felelőssége, nem a
        // GroupUser Pivot $fillable listájának mellékhatása. Ha ez a szűrés
        // elmarad, egy nyers pivot-írás "Unknown column" hibával hasal el -
        // ezért a forrást is állítjuk, nem csak a végeredményt.
        $source = file_get_contents(app_path('Http/Livewire/Groups/ListUsers.php'));

        $this->assertStringContainsString(
            "unset(\$validatedData['finish_guest_registration']);",
            $source,
            'A kulcsot expliciten ki kell venni a pivot payloadból.'
        );
    }

    // =========================================================================
    // 7. Vendégaktiválás
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
        // A Rule::In($finish_guest) (:216-220, :228-230) csak akkor engedi
        // az 1-et, ha a célszemély email_verified_at-je null.
        $actor = $this->member('ra-verified-actor@example.test', 'admin');
        $target = $this->member('ra-verified-target@example.test', 'member');

        $this->edit($actor, $target)
            ->set('state.finish_guest_registration', 1)
            ->call('updateUser')
            ->assertHasErrors(['finish_guest_registration']);
    }

    public function test_the_role_switch_only_happens_for_registered_users(): void
    {
        // A :293-294 két feltételt köt össze: a jelölőnégyzet ÉS a
        // 'registered' szerep. Egy már aktivált, de e-mailt nem igazolt
        // felhasználónál a validátor átengedi az 1-et, a mellékhatás
        // viszont elmarad - jelszó, szerep és tagság változatlan.
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
    // 8. Profilmódosítás és értesítés
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
        // MEGFORDÍTVA a v1-patch B5 javításával.
        //
        // Az updateUser() két lépcsőben mentett: a pivot-adatok már az
        // adatbázisban voltak, mire a profilmezők validációja lefutott, KÜLÖN
        // Validator::make()-kel és tranzakció nélkül. Egy hibás név ezért
        // részleges mentést hagyott maga után - a jegyzet és a szerep
        // elmentődött, a profil nem -, a felhasználó pedig csak egy
        // hibaüzenetet látott, és nem tudhatta, mi maradt bent.
        //
        // Most minden validáció az első írás ELŐTT fut, a maradék pedig egyetlen
        // tranzakcióban: vagy minden elmentődik, vagy semmi.
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
        // A B5 kontroll-kísérlete: a tranzakció nem ronthatja el a jó utat.
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
