<?php

namespace Tests\Feature\Gdpr;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12.2: az anonimizálás feltétele az UTÓDLÁS, nem a szerep.
 *
 * Korábban a gdpr:anonymize-inactive egy szerepliszttel dolgozott
 * (whereNotIn('role', ['mainAdmin','groupCreator'])). Ez két irányban tévedett:
 * védte azt a groupCreator-t, akinek minden csoportját ellátja más, és nem
 * védte azt a csoportadmint, aki az egyetlen a csoportjában.
 *
 * Az új szabály:
 *   1. mainAdmin csak akkor anonimizálható, ha marad másik, nem anonimizált
 *      mainAdmin.
 *   2. csoportadmin csak akkor, ha MINDEN csoportjára igaz a
 *      pwbs_check_group_other_admins() - ugyanaz a feltétel, amit a
 *      csoportelhagyás is kikényszerít.
 *
 * A szabály a User::anonymize()-ban él, ezért mindhárom úton érvényes: a
 * projekt parancsán, a csomag 00:00-s parancsán és a profiloldali GDPR-kérésen.
 */
class AnonymizationSuccessionTest extends FeatureTestCase
{
    private int $ttl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ttl = (int) config('gdpr.settings.ttl');
        config(['gdpr.enabled' => true]);
    }

    private function inactiveUser(string $email, array $attributes = []): User
    {
        return $this->createUser(array_merge([
            'email' => $email,
            'role' => 'registered',
            'isAnonymized' => 0,
            'last_activity' => now()->subMonths($this->ttl + 1),
        ], $attributes));
    }

    /**
     * A FeatureTestCase::setUp() létrehoz egy owner@example.test mainAdmin-t,
     * tehát alapból MINDIG van egy második főadmin. Az 1. szabály blokkolt
     * eseteihez ezt előbb el kell tüntetni.
     */
    private function removeTheDefaultMainAdmin(): void
    {
        User::where('email', 'owner@example.test')->update(['role' => 'activated']);
    }

    private function assertAnonymized(User $user, string $message = ''): void
    {
        $fresh = User::find($user->id);

        $this->assertSame(1, (int) $fresh->isAnonymized, $message ?: 'A felhasználót anonimizálni kellett volna.');
    }

    private function assertNotAnonymized(User $user, string $message = ''): void
    {
        $fresh = User::find($user->id);

        $this->assertSame(0, (int) $fresh->isAnonymized, $message ?: 'A felhasználót nem lett volna szabad anonimizálni.');
    }

    // =========================================================================
    // 1. szabály - a főadmin utódlása
    // =========================================================================

    public function test_the_last_main_admin_is_not_anonymized(): void
    {
        // Ez az eset ma átmegy a szerepszűrőn (a mainAdmin ki van zárva), de
        // rossz okból: nem azért, mert ő az utolsó, hanem mert főadmin. Az új
        // szabálynak akkor is zárnia kell, ha a szerepszűrő már nincs ott.
        $this->removeTheDefaultMainAdmin();

        $sole = $this->inactiveUser('sole-main-admin@example.test', ['role' => 'mainAdmin']);

        $sole->anonymize();

        $this->assertNotAnonymized($sole);
        $this->assertSame('mainAdmin', User::find($sole->id)->role, 'A szerepét sem veszítheti el.');
        $this->assertSame('sole-main-admin@example.test', User::find($sole->id)->email);
    }

    public function test_a_main_admin_with_an_active_successor_is_anonymized(): void
    {
        // A setUp() owner@example.test főadminja marad utódnak - és ez a
        // viselkedésváltozás lényege: a szerep önmagában nem véd többé.
        $inactive = $this->inactiveUser('replaceable-admin@example.test', ['role' => 'mainAdmin']);

        $inactive->anonymize();

        $this->assertAnonymized($inactive);
        $this->assertSame('registered', User::find($inactive->id)->role);
    }

    public function test_an_already_anonymized_main_admin_is_not_a_successor(): void
    {
        // A csomag 00:00-s parancsa pont ilyet gyárt: anonimizál, de a sort
        // meghagyja. Ha ez utódnak számítana, a láncreakció a főadmin szinten
        // is működne.
        $this->removeTheDefaultMainAdmin();

        $this->createUser([
            'email' => 'ghost-admin@example.test',
            'role' => 'mainAdmin',
            'isAnonymized' => 1,
        ]);

        $sole = $this->inactiveUser('real-admin@example.test', ['role' => 'mainAdmin']);

        $sole->anonymize();

        $this->assertNotAnonymized($sole, 'Egy anonimizált főadmin nem utód.');
    }

    public function test_a_batch_of_inactive_main_admins_keeps_exactly_one(): void
    {
        // A napi parancs ciklusban megy végig a felhasználókon. Két inaktív
        // főadminnál az elsőnél még van utód (a másik), tehát anonimizálódik -
        // a másodiknál viszont már nincs, tehát megmarad. Sorrendfüggő, de a
        // kimenet garantált: nem marad a rendszer főadmin nélkül.
        $this->removeTheDefaultMainAdmin();

        $first = $this->inactiveUser('batch-admin-1@example.test', ['role' => 'mainAdmin']);
        $second = $this->inactiveUser('batch-admin-2@example.test', ['role' => 'mainAdmin']);

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $survivors = User::whereIn('id', [$first->id, $second->id])
            ->where('isAnonymized', 0)
            ->count();

        $this->assertSame(1, $survivors, 'Pontosan egy főadminnak kell megmaradnia.');
        $this->assertSame(
            1,
            User::where('role', 'mainAdmin')->where('isAnonymized', 0)->count(),
            'Az oldal nem maradhat aktív főadmin nélkül.'
        );
    }

    // =========================================================================
    // 2. szabály - a csoportadmin utódlása
    // =========================================================================

    public function test_the_only_admin_of_a_group_is_not_anonymized(): void
    {
        // A mai szerepszűrő ezt az esetet ÁTENGEDI: a felhasználó szerepe
        // 'registered', a csoportbeli admin volta nem érdekli a szűrőt. A
        // csoport gazdátlanul marad.
        $user = $this->inactiveUser('sole-group-admin@example.test');
        $group = $this->createGroup(['name' => 'Gazdátlan csoport']);
        $this->attachUserToGroup($user, $group, 'admin');

        $user->anonymize();

        $this->assertNotAnonymized($user);
    }

    public function test_a_second_active_admin_unblocks_the_anonymization(): void
    {
        $user = $this->inactiveUser('handover@example.test');
        $group = $this->createGroup(['name' => 'Átadható csoport']);
        $this->attachUserToGroup($user, $group, 'admin');

        $successor = $this->createUser(['email' => 'successor@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $user->anonymize();

        $this->assertAnonymized($user);
    }

    public function test_an_anonymized_second_admin_is_not_a_successor(): void
    {
        // EZ A LÁNCREAKCIÓ. A Group::groupAdmins() nem szűrte az isAnonymized-et,
        // így egy már anonimizált admin utódnak számított - és mivel a csomag
        // parancsa a tagságot meghagyja, a csoport összes valódi adminja
        // egymás után kiüríthető lett volna.
        $user = $this->inactiveUser('next-in-line@example.test');
        $group = $this->createGroup(['name' => 'Láncreakció']);
        $this->attachUserToGroup($user, $group, 'admin');

        $ghost = $this->createUser(['email' => 'ghost-member@example.test', 'isAnonymized' => 1]);
        $this->attachUserToGroup($ghost, $group, 'admin');

        $user->anonymize();

        $this->assertNotAnonymized($user, 'Egy anonimizált csoportadmin nem utód.');
    }

    public function test_a_pending_second_admin_is_not_a_successor(): void
    {
        // Az el nem fogadott meghívás még nem tagság: a meghívott sosem lépett
        // be a csoportba, tehát nem lehet átadni neki.
        $user = $this->inactiveUser('pending-handover@example.test');
        $group = $this->createGroup(['name' => 'Függő meghívás']);
        $this->attachUserToGroup($user, $group, 'admin');

        $invited = $this->createUser(['email' => 'invited@example.test']);
        $this->attachUserToGroup($invited, $group, 'admin', false);

        $user->anonymize();

        $this->assertNotAnonymized($user, 'A függő meghívás nem utódlás.');
    }

    public function test_an_admin_present_only_in_the_parent_group_does_not_unblock(): void
    {
        // A pwbs_check_group_other_admins() ismert tulajdonsága (TODO 07.2):
        // a gyermekcsoportokat is átnézi, és olyan admint követel, aki MINDEN
        // csoportot lefed. Szándékosan marad így - a csoportelhagyás is ezt
        // kényszeríti, a kettő nem sodródhat el.
        $user = $this->inactiveUser('parent-and-child@example.test');
        $parent = $this->createGroup(['name' => 'Szülő csoport']);
        $child = $this->createChildGroup($parent, ['name' => 'Gyermek csoport']);

        $this->attachUserToGroup($user, $parent, 'admin');
        $this->attachUserToGroup($user, $child, 'admin');

        $partial = $this->createUser(['email' => 'parent-only@example.test']);
        $this->attachUserToGroup($partial, $parent, 'admin');

        $user->anonymize();

        $this->assertNotAnonymized($user, 'Csak a szülőt lefedő admin nem old fel.');
    }

    public function test_a_plain_member_is_never_blocked(): void
    {
        $user = $this->inactiveUser('plain-member@example.test');
        $group = $this->createGroup(['name' => 'Sima tagság']);
        $this->attachUserToGroup($user, $group, 'member');

        $user->anonymize();

        $this->assertAnonymized($user);
    }

    public function test_a_roler_is_never_blocked(): void
    {
        // A roler szerkesztheti a csoportot, de nem ő a felelőse - az
        // utódlási szabály csak az admin szerepre vonatkozik, ahogy a
        // csoportelhagyásnál is.
        $user = $this->inactiveUser('roler@example.test');
        $group = $this->createGroup(['name' => 'Roler csoportja']);
        $this->attachUserToGroup($user, $group, 'roler');

        $successor = $this->createUser(['email' => 'group-owner@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $user->anonymize();

        $this->assertAnonymized($user);
    }

    public function test_a_group_creator_without_groups_is_now_anonymizable(): void
    {
        // SZÁNDÉKOS VISELKEDÉSVÁLTOZÁS. A groupCreator szerep korábban
        // önmagában védett; mostantól csak az számít, van-e csoport, amit át
        // kell adni. Aki csoportot hoz létre, admin lesz benne
        // (ListGroups::createGroup()), tehát a 2. szabály úgyis fedi.
        $creator = $this->inactiveUser('idle-creator@example.test', ['role' => 'groupCreator']);

        $creator->anonymize();

        $this->assertAnonymized($creator);
        $this->assertSame('registered', User::find($creator->id)->role);
    }

    public function test_a_group_creator_with_an_unmanned_group_is_blocked(): void
    {
        $creator = $this->inactiveUser('busy-creator@example.test', ['role' => 'groupCreator']);
        $group = $this->createGroup(['name' => 'Alapított csoport']);
        $this->attachUserToGroup($creator, $group, 'admin');

        $creator->anonymize();

        $this->assertNotAnonymized($creator);
        $this->assertSame('groupCreator', User::find($creator->id)->role);
    }

    public function test_every_group_must_have_a_successor_not_just_one(): void
    {
        // Két különálló csoport: az egyikben van utód, a másikban nincs. A
        // szabály minden csoportra vonatkozik, tehát ez blokkolt eset.
        $user = $this->inactiveUser('two-groups@example.test');

        $covered = $this->createGroup(['name' => 'Ellátott csoport']);
        $this->attachUserToGroup($user, $covered, 'admin');
        $successor = $this->createUser(['email' => 'covers-one@example.test']);
        $this->attachUserToGroup($successor, $covered, 'admin');

        $uncovered = $this->createGroup(['name' => 'Ellátatlan csoport']);
        $this->attachUserToGroup($user, $uncovered, 'admin');

        $user->anonymize();

        $this->assertNotAnonymized($user, 'Egyetlen fedetlen csoport is blokkol.');
    }

    // =========================================================================
    // Mindhárom útvonal ugyanazt a szabályt látja
    // =========================================================================

    public function test_the_project_command_respects_the_rule(): void
    {
        $user = $this->inactiveUser('project-path@example.test');
        $group = $this->createGroup(['name' => 'Projekt parancs']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $this->assertNotAnonymized($user);
    }

    public function test_the_package_command_respects_the_rule(): void
    {
        // A CSOMAG parancsa 00:00-kor fut, hét órával a projekté előtt, és
        // semmilyen szűrése nincs. Ezért kellett az őrnek a User::anonymize()-ba
        // kerülnie: parancsba tett szabályt ez a futás megkerülné.
        $user = $this->inactiveUser('package-path@example.test');
        $group = $this->createGroup(['name' => 'Csomag parancs']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->artisan('gdpr:anonymizeInactiveUsers')->assertExitCode(0);

        $this->assertNotAnonymized($user);
        $this->assertSame('package-path@example.test', User::find($user->id)->email);
    }

    public function test_the_project_command_does_not_detach_a_blocked_user(): void
    {
        // SORRENDI KÖVETELMÉNY. A parancs eredetileg ELŐBB bontotta a
        // tagságokat, és csak utána anonimizált. Ha az őr utólag zárna, a
        // felhasználó tagság nélkül, de anonimizálatlanul maradna - és épp az
        // utódlás bizonyítéka veszne el.
        $user = $this->inactiveUser('keep-membership@example.test');
        $group = $this->createGroup(['name' => 'Megmaradó tagság']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $this->assertDatabaseHas('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'deleted_at' => null,
        ]);
    }

    public function test_the_project_command_still_detaches_an_eligible_user(): void
    {
        $user = $this->inactiveUser('eligible@example.test');
        $group = $this->createGroup(['name' => 'Bontható tagság']);
        $this->attachUserToGroup($user, $group, 'admin');
        $successor = $this->createUser(['email' => 'takes-over@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $this->assertAnonymized($user);
        $this->assertDatabaseMissing('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'deleted_at' => null,
        ]);
    }

    // =========================================================================
    // A profiloldali GDPR-kérés
    // =========================================================================

    public function test_the_profile_request_is_blocked_with_an_explanation(): void
    {
        // A GDPR-kérés nem tűnhet el csendben: a felhasználónak meg kell tudnia,
        // mit kell tennie (adja át a csoportját).
        $user = $this->createUser(['email' => 'blocked-request@example.test']);
        $group = $this->createGroup(['name' => 'Átadandó csoport']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->actingAs($user);

        $response = $this->getWithPasswordConfirmation(route('user.askToDelete'));

        $response->assertRedirect(route('user.profile'));

        // Nem elég a puszta jelenlétre nézni: a profileFull middleware is
        // ugyanezt a kulcsot használja ugyanerre az átirányításra. A csoport
        // neve teszi egyértelművé, hogy az utódlási szabály szólalt meg.
        $this->assertStringContainsString(
            'Átadandó csoport',
            (string) session('profile_message')
        );

        $this->assertNotAnonymized($user);
    }

    public function test_the_signed_deletion_link_is_blocked_too(): void
    {
        // Az aláírt link 60 órán át érvényes, közben változhat az állapot -
        // ezért a második lépésnek is ellenőriznie kell.
        $user = $this->createUser(['email' => 'blocked-link@example.test']);
        $group = $this->createGroup(['name' => 'Link ág']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->actingAs($user)
            ->get($this->signedRoute('user.deletepersonaldata', ['id' => $user->id]))
            ->assertRedirect(route('user.profile'));

        $this->assertNotAnonymized($user);
        $this->assertAuthenticated('web');
        $this->assertDatabaseHas('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'deleted_at' => null,
        ]);
    }

    public function test_an_eligible_user_can_still_delete_their_data(): void
    {
        $user = $this->createUser(['email' => 'allowed-request@example.test']);
        $group = $this->createGroup(['name' => 'Ellátott csoport']);
        $this->attachUserToGroup($user, $group, 'admin');
        $successor = $this->createUser(['email' => 'stays@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $this->actingAs($user)
            ->get($this->signedRoute('user.deletepersonaldata', ['id' => $user->id]))
            ->assertRedirect('login');

        $this->assertAnonymized($user);
        $this->assertGuest();
    }

    // =========================================================================
    // A szabály forrása: ugyanaz, amit a csoportelhagyás használ
    // =========================================================================

    public function test_the_rule_uses_the_same_helper_as_leaving_a_group(): void
    {
        // Ha a kettő elsodródik, a felhasználó azt kapja, hogy kilépni nem tud
        // a csoportból, de az adatait törölni igen (vagy fordítva). Ezért
        // ugyanaz a helper dönt mindkét helyen.
        $user = $this->inactiveUser('same-rule@example.test');
        $group = $this->createGroup(['name' => 'Közös szabály']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->assertFalse(
            pwbs_check_group_other_admins($group->id, $user->id),
            'A csoportelhagyás is tiltaná.'
        );

        $user->anonymize();
        $this->assertNotAnonymized($user);

        $successor = $this->createUser(['email' => 'unblocks-both@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $this->assertTrue(
            pwbs_check_group_other_admins($group->id, $user->id),
            'A csoportelhagyás is engedné.'
        );

        $user->fresh()->anonymize();
        $this->assertAnonymized($user);
    }

    public function test_a_withdrawn_membership_no_longer_blocks(): void
    {
        // A soft-deletelt tagság nem tagság: aki már kilépett a csoportból,
        // annak nincs mit átadnia.
        $user = $this->inactiveUser('withdrawn@example.test');
        $group = $this->createGroup(['name' => 'Elhagyott csoport']);
        $this->attachUserToGroup($user, $group, 'admin');

        GroupUser::where('user_id', $user->id)->where('group_id', $group->id)->delete();

        $user->fresh()->anonymize();

        $this->assertAnonymized($user);
    }

    public function test_the_blocked_user_stays_in_the_batch_for_the_next_run(): void
    {
        // A blokkolás nem véglegesít semmit: amint megérkezik az utód, a
        // következő futás elvégzi az anonimizálást. Fontos, mert a GDPR-igény
        // nem szűnik meg attól, hogy ma nem teljesíthető.
        $user = $this->inactiveUser('deferred@example.test');
        $group = $this->createGroup(['name' => 'Halasztott']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive');
        $this->assertNotAnonymized($user);

        $successor = $this->createUser(['email' => 'arrives-later@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive');
        $this->assertAnonymized($user);
    }

    public function test_the_command_reports_the_skipped_users(): void
    {
        $blocked = $this->inactiveUser('reported@example.test');
        $group = $this->createGroup(['name' => 'Jelentett csoport']);
        $this->attachUserToGroup($blocked, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive')
            ->expectsOutput('Anonymized 0 inactive user(s).')
            ->expectsOutput('Skipped 1 user(s) with no successor.')
            ->assertExitCode(0);
    }

    // =========================================================================
    // Az utódlási feltétel a Group-relációból
    // =========================================================================

    public function test_the_active_admins_relation_filters_anonymized_and_pending(): void
    {
        $group = $this->createGroup(['name' => 'Szűrt adminok']);

        $active = $this->createUser(['email' => 'active-admin@example.test']);
        $this->attachUserToGroup($active, $group, 'admin');

        $anonymized = $this->createUser(['email' => 'anonymized-admin@example.test', 'isAnonymized' => 1]);
        $this->attachUserToGroup($anonymized, $group, 'admin');

        $pending = $this->createUser(['email' => 'pending-admin@example.test']);
        $this->attachUserToGroup($pending, $group, 'admin', false);

        $ids = Group::find($group->id)->activeAdmins()->pluck('users.id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($anonymized->id, $ids, 'Anonimizált admin nem aktív admin.');
        $this->assertNotContains($pending->id, $ids, 'Függő meghívás nem aktív admin.');

        // A groupAdmins() maga NEM változik: tíz további hívási helye a saját
        // jogosultságot ellenőrzi (wherePivot('user_id', Auth::id())).
        $this->assertCount(3, Group::find($group->id)->groupAdmins()->get());
    }
}
