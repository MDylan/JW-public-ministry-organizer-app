<?php

namespace Tests\Feature\Gdpr;

use App\Models\AdminNewsletter;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12: naponta KÉT különböző anonimizáló fut, más-más szabályokkal.
 *
 *   0:00  gdpr:anonymizeInactiveUsers  (a Dialect csomag, saját providerből)
 *   7:00  gdpr:anonymize-inactive      (a projekté, TODO 06-ban kiemelve)
 *
 * TODO 12.2 ÓTA MEGVÁLTOZOTT, MI A KÜLÖNBSÉG. Az utódlási szabály a
 * User::anonymize()-ban ül, ezért MINDKÉT parancsra érvényes - a szerepalapú
 * divergencia megszűnt. A csomag parancsát ráadásul a projekt leszármazottja
 * (App\Console\Commands\PackageAnonymizeInactiveUsers) írja felül, mert az
 * eredeti a `$user->anonymize()` után külön is beállította az isAnonymized
 * jelzőt - és azt az őr nem tudta megakadályozni.
 *
 * Ami DIVERGENCIA MARADT, és a TODO 16 csomagcsere-döntésére tartozik:
 *   - a csomag parancsa nem bontja a csoporttagságokat,
 *   - és hét órával korábban fut.
 * Ez a két különbség együtt még mindig oda vezet, hogy egy anonimizált
 * felhasználó bent maradhat a hírlevél-címzettek között.
 */
class AnonymizeCommandDivergenceTest extends FeatureTestCase
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
     * A csomag parancsa nem bontja a tagságokat, ezért a forgatókönyveinek
     * akkor is életben kell maradniuk, ha a vizsgált felhasználó csoportadmin.
     * Ehhez kell egy második, valódi admin - különben az utódlási szabály
     * blokkolna, és nem a mért különbséget látnánk.
     */
    private function successorFor(\App\Models\Group $group, string $email = 'successor@example.test'): User
    {
        $successor = $this->createUser(['email' => $email]);
        $this->attachUserToGroup($successor, $group, 'admin');

        return $successor;
    }

    // =========================================================================
    // 1. A projekt leszármazottja írja felül a csomag parancsát
    // =========================================================================

    public function test_the_package_command_is_served_by_the_project_subclass(): void
    {
        // A felülírás REGISZTRÁCIÓS SORRENDEN múlik: a csomag a providerből,
        // Artisan::starting() callbackben regisztrál, a Kernel $commands tömbje
        // viszont később oldódik fel, és az azonos nevű parancs felülírja az
        // előzőt. Pont az a fajta framework-verziófüggő út, amit egy 8 -> 13
        // ugrás megzavarhat, ezért itt van rá közvetlen állítás.
        $resolved = Artisan::all()['gdpr:anonymizeInactiveUsers'] ?? null;

        $this->assertInstanceOf(Command::class, $resolved);
        $this->assertInstanceOf(
            \App\Console\Commands\PackageAnonymizeInactiveUsers::class,
            $resolved,
            'A csomag parancsát a projekt leszármazottjának kell kiszolgálnia.'
        );
    }

    // =========================================================================
    // 2. Az utódlási szabály mindkét parancsra érvényes
    // =========================================================================

    public function test_both_commands_apply_the_same_succession_rule(): void
    {
        // Korábban itt állt a legélesebb ellentmondás: a csomag parancsa
        // anonimizálta a főadmint, a projekté védte. Ma mindkettőt ugyanaz a
        // feltétel köti, tehát ugyanazt a döntést hozzák.
        User::where('email', 'owner@example.test')->update(['role' => 'activated']);

        $sole = $this->inactiveUser('sole-admin@example.test', ['role' => 'mainAdmin']);

        $this->artisan('gdpr:anonymizeInactiveUsers')->assertExitCode(0);
        $this->assertSame('sole-admin@example.test', User::find($sole->id)->email);

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);
        $this->assertSame('sole-admin@example.test', User::find($sole->id)->email);
    }

    public function test_the_package_command_no_longer_flags_a_protected_user(): void
    {
        // A csomag eredeti törzse `$user->anonymize()` UTÁN külön is beállította
        // az isAnonymized jelzőt. Az őr az elsőt megállította, a másodikat nem -
        // a védett felhasználó adatai megmaradtak volna, de eltűnt volna minden
        // csoportlistából (Group::groupUsers(), ::users() szűri a jelzőt) és
        // semmilyen levelet nem kapott volna (User::routeNotificationFor()).
        $user = $this->inactiveUser('flag-guard@example.test');
        $group = $this->createGroup(['name' => 'Jelzővédelem']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->artisan('gdpr:anonymizeInactiveUsers')->assertExitCode(0);

        $fresh = User::find($user->id);

        $this->assertSame(0, (int) $fresh->isAnonymized, 'A jelző nem kerülhet rá egy védett felhasználóra.');
        $this->assertSame('flag-guard@example.test', $fresh->email);
    }

    // =========================================================================
    // 3. A megmaradt divergencia: a tagságok sorsa
    // =========================================================================

    public function test_the_package_command_leaves_group_memberships_intact(): void
    {
        $user = $this->inactiveUser('member@example.test');
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'admin');
        $this->successorFor($group);

        $this->artisan('gdpr:anonymizeInactiveUsers');

        $this->assertSame(1, (int) User::find($user->id)->isAnonymized, 'Anonimizálódnia kellett.');
        $this->assertDatabaseHas('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'deleted_at' => null,
        ]);
    }

    public function test_the_project_command_detaches_the_memberships_first(): void
    {
        $user = $this->inactiveUser('detached@example.test');
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'admin');
        $this->successorFor($group);

        $this->artisan('gdpr:anonymize-inactive');

        $this->assertDatabaseMissing('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'deleted_at' => null,
        ]);
    }

    // =========================================================================
    // 4. A divergencia következménye: az anonimizált felhasználó címzett marad
    // =========================================================================

    public function test_an_anonymized_group_admin_is_still_a_newsletter_recipient(): void
    {
        // A csomag parancsa anonimizál, de a tagságot meghagyja - a
        // User::userGroupsDeletable() relációja pedig NEM szűri az
        // isAnonymized-et (a Group::groupUsers() és ::users() igen, ezért
        // máshol nem látszik a probléma).
        //
        // Így a newsletters:send-due 'groupAdmins' célcsoportja tartalmazza az
        // anonimizált felhasználót, akinek az e-mail mezője ekkor már egy
        // 10 karakteres token - nem cím.
        $admin = $this->inactiveUser('newsletter-admin@example.test');
        $group = $this->createGroup();
        $this->attachUserToGroup($admin, $group, 'admin');
        $this->successorFor($group);

        $this->artisan('gdpr:anonymizeInactiveUsers');

        $anonymized = User::find($admin->id);
        $this->assertSame(1, (int) $anonymized->isAnonymized);

        $recipients = User::whereHas('userGroupsDeletable')->pluck('id');

        $this->assertContains(
            $anonymized->id,
            $recipients->all(),
            'Az anonimizált csoportadmin továbbra is hírlevél-címzett.'
        );
    }

    public function test_the_notification_is_dispatched_but_the_fake_hides_the_routing_guard(): void
    {
        // FIGYELEM, MÓDSZERTANI CSAPDA: a Notification::fake() a NOTIFIABLE-t
        // jegyzi fel, nem a címet - a User::routeNotificationFor() pedig soha
        // nem hívódik meg. Egy fake-re épülő állítás tehát olyan küldést is
        // "igazol", ami a valóságban el sem indul.
        //
        // Ez a teszt ezért csak annyit rögzít, hogy a parancs eljut az
        // anonimizált felhasználóig és kiadja neki az értesítést. Hogy a levél
        // tényleg elmegy-e, a következő teszt méri - fake nélkül.
        Notification::fake();

        $admin = $this->inactiveUser('token-address@example.test');
        $group = $this->createGroup();
        $this->attachUserToGroup($admin, $group, 'admin');
        $this->successorFor($group);

        $this->artisan('gdpr:anonymizeInactiveUsers');

        $newsletter = AdminNewsletter::factory()->create([
            'date' => today(),
            'send_newsletter' => 1,
            'status' => 1,
            'sent_time' => null,
            'send_to' => 'groupAdmins',
        ]);

        $this->artisan('newsletters:send-due')->assertExitCode(0);

        $anonymized = User::find($admin->id);

        $this->assertStringNotContainsString('@', $anonymized->email);
        Notification::assertSentTo($anonymized, \App\Notifications\Newsletter::class);
        $this->assertNotNull($newsletter->fresh()->sent_time);
    }

    public function test_no_mail_actually_leaves_for_an_anonymized_user(): void
    {
        // ÉS ITT ZÁRUL A LÁNC. A címzettlistában bent marad, az értesítést meg
        // is kapja - de levél mégsem indul, mert a User::routeNotificationFor()
        // (:310) null-t ad vissza minden anonimizált felhasználóra, és minden
        // olyan címre, ami nem megy át a FILTER_VALIDATE_EMAIL-en.
        //
        // Ez a metódus tehát KETTŐS védelem, és teljesen dokumentálatlan. A
        // TODO 16-nak (a csomag cseréje) meg kell őriznie: nélküle a
        // 10 karakteres token kimenne a mailerhez, amit a SwiftMailer még
        // elnyelne, a Symfony Mailer (Phase 4) viszont RFC-hibával eldobna.
        $admin = $this->inactiveUser('real-send@example.test');
        $group = $this->createGroup();
        $this->attachUserToGroup($admin, $group, 'admin');
        $this->successorFor($group);

        $this->artisan('gdpr:anonymizeInactiveUsers');

        AdminNewsletter::factory()->create([
            'date' => today(),
            'send_newsletter' => 1,
            'status' => 1,
            'sent_time' => null,
            'send_to' => 'groupAdmins',
        ]);

        $this->artisan('newsletters:send-due')->assertExitCode(0);

        $messages = app('mailer')->getSwiftMailer()->getTransport()->messages();
        $recipients = collect($messages)
            ->flatMap(fn ($message) => array_keys($message->getTo() ?? []))
            ->all();

        $this->assertNotContains(
            User::find($admin->id)->email,
            $recipients,
            'Anonimizált felhasználóhoz nem indulhat levél.'
        );
    }

    public function test_the_routing_guard_rejects_both_an_invalid_address_and_an_anonymized_flag(): void
    {
        // A védelem két külön feltétele külön-külön is elég. Fontos, mert a
        // TODO 16 könnyen csak az egyiket vinné tovább.
        $valid = $this->createUser(['email' => 'routable@example.test', 'isAnonymized' => 0]);
        $this->assertSame('routable@example.test', $valid->routeNotificationFor('mail'));

        $flagged = $this->createUser(['email' => 'flagged@example.test', 'isAnonymized' => 1]);
        $this->assertNull($flagged->routeNotificationFor('mail'), 'Az anonimizált jelző önmagában zár.');

        $tokenAddress = $this->createUser(['email' => 'nem-cim', 'isAnonymized' => 0]);
        $this->assertNull($tokenAddress->routeNotificationFor('mail'), 'Az érvénytelen cím önmagában zár.');
    }
}
