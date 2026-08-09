<?php

namespace Tests\Feature\Commands;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Notifications\UserWillBeAnonymizeNotification;
use App\Notifications\UserWillBeAnyonimizeAdminNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 06: the two GDPR scheduler closures, now runnable commands.
 *
 * These were the largest and least visible closures in Console\Kernel - roughly
 * 90 lines including a four-way raw join - and they delete or anonymize real
 * user data daily. They had no coverage whatsoever.
 *
 * Note: .env.testing sets GDPR_ENABLED=false, so every test that expects work
 * to happen must enable it explicitly.
 */
class GdprCommandsTest extends FeatureTestCase
{
    private int $ttl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ttl = (int) config('gdpr.settings.ttl'); // alapból 6 hónap
    }

    private function enableGdpr(): void
    {
        config(['gdpr.enabled' => true]);
    }

    /**
     * A szerkesztői értesítés ablaka szűk és dátum-szintű: a parancs
     * whereBetween-t használ Y-m-d formátumú határokkal, ahol az alsó határ
     * (ttl - 6 nap) és a felső (ttl - 7 nap) is éjfélre kerekedik. Egy
     * napon belüli időpont a felső határon már kiesik, ezért a nap közepét
     * célozzuk meg.
     */
    private function editorWarningWindowDate(): \Carbon\Carbon
    {
        return now()->subMonths($this->ttl)->addDays(6)->startOfDay()->addHours(12);
    }

    private function inactiveUser(array $attributes = []): User
    {
        return $this->createUser(array_merge([
            'email' => 'inactive-'.uniqid().'@example.test',
            'role' => 'registered',
            'isAnonymized' => 0,
            'last_activity' => now()->subMonths($this->ttl + 1),
        ], $attributes));
    }

    // --- gdpr:anonymize-inactive ---

    public function test_anonymize_is_a_no_op_when_gdpr_is_disabled(): void
    {
        config(['gdpr.enabled' => false]);
        $user = $this->inactiveUser(['email' => 'still-here@example.test']);

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $this->assertSame('still-here@example.test', User::find($user->id)->email);
    }

    public function test_anonymize_processes_users_past_the_retention_period(): void
    {
        $this->enableGdpr();
        $user = $this->inactiveUser(['email' => 'to-anonymize@example.test']);

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $fresh = User::find($user->id);
        $this->assertNotNull($fresh, 'The row must survive; only its data is anonymized.');
        $this->assertSame(1, (int) $fresh->isAnonymized);
        $this->assertNotSame('to-anonymize@example.test', $fresh->email);
    }

    public function test_anonymize_detaches_group_memberships_first(): void
    {
        $this->enableGdpr();
        $group = $this->createGroup();
        $user = $this->inactiveUser();
        $this->attachUserToGroup($user, $group);

        $this->artisan('gdpr:anonymize-inactive');

        $this->assertSame(0, GroupUser::where('user_id', $user->id)->count());
    }

    public function test_anonymize_skips_recently_active_users(): void
    {
        $this->enableGdpr();
        $active = $this->inactiveUser([
            'email' => 'active@example.test',
            'last_activity' => now()->subDay(),
        ]);

        $this->artisan('gdpr:anonymize-inactive');

        $this->assertSame('active@example.test', User::find($active->id)->email);
    }

    /**
     * TODO 12.2 óta a szerep önmagában NEM véd - az utódlás dönt.
     *
     * Ez a teszt korábban azt rögzítette, hogy a parancs kihagyja a mainAdmin
     * és a groupCreator szerepet. Az a szabály két irányban tévedett: védte azt
     * a groupCreator-t, akinek minden csoportját ellátja más, és nem védte azt
     * a csoportadmint, aki az egyetlen a csoportjában. A részletes utódlási
     * eseteket a tests/Feature/Gdpr/AnonymizationSuccessionTest.php méri; itt
     * csak azt rögzítjük, hogy a parancs a szereplistát tényleg elengedte.
     */
    public function test_anonymize_no_longer_protects_by_role_alone(): void
    {
        $this->enableGdpr();
        // A FeatureTestCase::setUp() owner@example.test főadminja marad
        // utódnak, a groupCreator-nak pedig nincs csoportja - mindkettő
        // anonimizálható.
        $admin = $this->inactiveUser(['email' => 'admin@example.test', 'role' => 'mainAdmin']);
        $creator = $this->inactiveUser(['email' => 'creator@example.test', 'role' => 'groupCreator']);

        $this->artisan('gdpr:anonymize-inactive');

        $this->assertNotSame('admin@example.test', User::find($admin->id)->email);
        $this->assertNotSame('creator@example.test', User::find($creator->id)->email);
        $this->assertSame('registered', User::find($admin->id)->role);
    }

    public function test_anonymize_protects_a_user_who_has_nobody_to_hand_over_to(): void
    {
        $this->enableGdpr();

        $creator = $this->inactiveUser(['email' => 'creator@example.test', 'role' => 'groupCreator']);
        $group = Group::factory()->create(['parent_group_id' => null]);
        $this->attachUserToGroup($creator, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive');

        $this->assertSame('creator@example.test', User::find($creator->id)->email);
        $this->assertSame(0, (int) User::find($creator->id)->isAnonymized);
    }

    public function test_anonymize_skips_already_anonymized_users(): void
    {
        $this->enableGdpr();
        $already = $this->inactiveUser(['email' => 'already@example.test', 'isAnonymized' => 1]);

        $this->artisan('gdpr:anonymize-inactive');

        // Változatlanul marad, nem esik át újabb anonimizáláson.
        $this->assertSame('already@example.test', User::find($already->id)->email);
    }

    // --- gdpr:notify-anonymization ---

    public function test_notify_is_a_no_op_when_gdpr_is_disabled(): void
    {
        Notification::fake();
        config(['gdpr.enabled' => false]);
        $this->inactiveUser(['last_activity' => now()->subMonths($this->ttl)->addDays(10)]);

        $this->artisan('gdpr:notify-anonymization')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_notify_warns_users_approaching_the_retention_limit(): void
    {
        Notification::fake();
        $this->enableGdpr();

        // A parancs a ttl - 15 nap küszöbnél régebbi utolsó aktivitást keresi.
        $user = $this->inactiveUser(['last_activity' => now()->subMonths($this->ttl)->addDays(10)]);

        $this->artisan('gdpr:notify-anonymization')->assertExitCode(0);

        Notification::assertSentTo($user, UserWillBeAnonymizeNotification::class);
    }

    public function test_notify_leaves_recently_active_users_alone(): void
    {
        Notification::fake();
        $this->enableGdpr();

        $recent = $this->inactiveUser(['last_activity' => now()->subDays(3)]);

        $this->artisan('gdpr:notify-anonymization');

        Notification::assertNotSentTo($recent, UserWillBeAnonymizeNotification::class);
    }

    /**
     * TODO 12.2: a figyelmeztetés ugyanazt az alkalmassági feltételt követi,
     * mint az anonimizálás.
     *
     * Ez fontos mindkét irányban. Aki anonimizálható lesz, annak MEG KELL
     * kapnia az előzetes értesítést - a régi szereplista mellett egy
     * groupCreator figyelmeztetés nélkül tűnt volna el. Akit viszont az
     * utódlás blokkol, annak nem szabad kapnia: a lekérdezés nem ablak, hanem
     * küszöb (last_activity <= ttl - 15 nap), tehát a blokkolt felhasználó
     * NAPONTA kapna levelet egy soha be nem következő törlésről.
     */
    public function test_notify_warns_a_group_creator_who_will_be_anonymized(): void
    {
        Notification::fake();
        $this->enableGdpr();

        $creator = $this->inactiveUser(['role' => 'groupCreator']);

        $this->artisan('gdpr:notify-anonymization');

        Notification::assertSentTo($creator, UserWillBeAnonymizeNotification::class);
    }

    public function test_notify_stays_silent_for_a_user_the_succession_rule_blocks(): void
    {
        Notification::fake();
        $this->enableGdpr();

        $group = Group::factory()->create(['parent_group_id' => null]);
        $soleAdmin = $this->inactiveUser();
        $this->attachUserToGroup($soleAdmin, $group, 'admin');

        $this->artisan('gdpr:notify-anonymization');

        Notification::assertNotSentTo($soleAdmin, UserWillBeAnonymizeNotification::class);
    }

    public function test_notify_alerts_group_editors_about_members_in_the_warning_window(): void
    {
        Notification::fake();
        $this->enableGdpr();

        // A szerkesztői értesítés szűk, 6-7 napos ablakot használ a
        // megőrzési idő letelte előtt.
        $group = Group::factory()->create(['parent_group_id' => null]);
        $member = $this->inactiveUser([
            'last_activity' => $this->editorWarningWindowDate(),
        ]);
        $editor = $this->createUser(['email' => 'editor@example.test']);

        $this->attachUserToGroup($member, $group);
        $this->attachUserToGroup($editor, $group, 'admin');

        $this->artisan('gdpr:notify-anonymization')->assertExitCode(0);

        Notification::assertSentTo($editor, UserWillBeAnyonimizeAdminNotification::class);
    }

    public function test_notify_decrypts_names_from_the_raw_join(): void
    {
        Notification::fake();
        $this->enableGdpr();

        // A szerkesztői ág nyers DB lekérdezést használ, ezért a titkosított
        // User.name és Group.name oszlopokat kézzel fejti vissza. Ha ez
        // elromlik, a parancs DecryptException-nel bukik, nem csendben.
        $group = Group::factory()->create(['parent_group_id' => null, 'name' => 'Titkos Csoport']);
        $member = $this->inactiveUser([
            'name' => 'Titkos Tag',
            'last_activity' => $this->editorWarningWindowDate(),
        ]);
        $editor = $this->createUser(['email' => 'editor2@example.test']);

        $this->attachUserToGroup($member, $group);
        $this->attachUserToGroup($editor, $group, 'roler');

        $this->artisan('gdpr:notify-anonymization')->assertExitCode(0);

        Notification::assertSentTo(
            $editor,
            UserWillBeAnyonimizeAdminNotification::class,
            function ($notification) use ($member) {
                $rendered = $notification->toMail($member)->render();

                return str_contains($rendered, 'Titkos Tag');
            }
        );
    }

    public function test_notify_ignores_child_groups_for_the_editor_alert(): void
    {
        Notification::fake();
        $this->enableGdpr();

        // A join whereNull('G.parent_group_id') feltétellel szűr: az
        // alcsoportok szerkesztői nem kapnak értesítést.
        $parent = Group::factory()->create(['parent_group_id' => null]);
        $child = Group::factory()->asChildOf($parent)->create();

        $member = $this->inactiveUser([
            'last_activity' => $this->editorWarningWindowDate(),
        ]);
        $childEditor = $this->createUser(['email' => 'child-editor@example.test']);

        $this->attachUserToGroup($member, $child);
        $this->attachUserToGroup($childEditor, $child, 'admin');

        $this->artisan('gdpr:notify-anonymization');

        Notification::assertNotSentTo($childEditor, UserWillBeAnyonimizeAdminNotification::class);
    }
}
