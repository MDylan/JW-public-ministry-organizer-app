<?php

namespace Tests\Feature\Gdpr;

use App\Models\AdminNewsletter;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\FeatureTestCase;

/**
 * The nightly anonymizer. Formerly AnonymizeCommandDivergenceTest.
 *
 * Until TODO 33.2 there were TWO of them, and the file was named after the gap
 * between them:
 *
 *   00:00  gdpr:anonymizeInactiveUsers  (Dialect package, its own provider)
 *   07:00  gdpr:anonymize-inactive      (this project, extracted in TODO 06)
 *
 * TODO 12.2 had already moved the succession rule into User::anonymize(), so
 * both commands obeyed it, and it neutralized the package command with a
 * same-named project subclass. What survived that was the part no guard could
 * reach: the package command ran seven hours earlier and never detached group
 * memberships, which is how an anonymized user stayed on the newsletter
 * recipient list. Removing the package removes the second command, so the whole
 * divergence is retired - the first two tests below are what proves it.
 *
 * The routing guard tests stay exactly as they were. They pin the one thing
 * that kept mail away from anonymized users all along, and TODO 16 flagged it
 * as the single most likely casualty of the package replacement.
 */
class AnonymizeCommandTest extends FeatureTestCase
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
     * The succession rule blocks the only admin of a group, so any scenario
     * about a group admin needs a second, genuine admin - otherwise the rule
     * stops the run and the test measures the block instead of the behaviour.
     */
    private function successorFor(\App\Models\Group $group, string $email = 'successor@example.test'): User
    {
        $successor = $this->createUser(['email' => $email]);
        $this->attachUserToGroup($successor, $group, 'admin');

        return $successor;
    }

    // =========================================================================
    // 1. There is only one anonymizer now
    // =========================================================================

    public function test_the_package_command_no_longer_exists(): void
    {
        // The command used to be registered by the package's service provider
        // through an Artisan::starting() callback, and shadowed by a project
        // subclass listed in Kernel::$commands - which only won because
        // Kernel::getArtisan() resolves $commands afterwards. That ordering was
        // load-bearing for a data-protection guarantee and is exactly the kind
        // of framework internal an 8 -> 13 upgrade disturbs. It is gone.
        $this->assertArrayNotHasKey('gdpr:anonymizeInactiveUsers', Artisan::all());
        $this->assertArrayHasKey('gdpr:anonymize-inactive', Artisan::all());
    }

    public function test_the_remaining_command_applies_the_succession_rule(): void
    {
        // The rule lives in User::anonymize(), not in the command, because
        // several code paths anonymize a user. Blocking the last main admin is
        // the sharpest case: role is in $gdprAnonymizableFields, so
        // anonymization would also demote them to `registered` and leave the
        // site with no administrator at all.
        User::where('email', 'owner@example.test')->update(['role' => 'activated']);

        $sole = $this->inactiveUser('sole-admin@example.test', ['role' => 'mainAdmin']);

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $this->assertSame('sole-admin@example.test', User::find($sole->id)->email);
    }

    public function test_the_command_detaches_the_memberships(): void
    {
        $user = $this->inactiveUser('detached@example.test');
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'admin');
        $this->successorFor($group);

        $this->artisan('gdpr:anonymize-inactive');

        $this->assertSame(1, (int) User::find($user->id)->isAnonymized);
        $this->assertDatabaseMissing('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'deleted_at' => null,
        ]);
    }

    public function test_the_nightly_run_no_longer_leaves_a_newsletter_recipient_behind(): void
    {
        // This is the consequence the divergence used to produce, asserted from
        // the other side. The package command left memberships in place, and
        // User::userGroupsDeletable() does not filter isAnonymized - unlike
        // Group::groupUsers() and ::users(), which is why the problem was
        // invisible everywhere else - so newsletters:send-due kept selecting
        // the anonymized user. The surviving command detaches first, so the
        // recipient list no longer contains them at all.
        $admin = $this->inactiveUser('newsletter-admin@example.test');
        $group = $this->createGroup();
        $this->attachUserToGroup($admin, $group, 'admin');
        $this->successorFor($group);

        $this->artisan('gdpr:anonymize-inactive');

        $anonymized = User::find($admin->id);
        $this->assertSame(1, (int) $anonymized->isAnonymized);

        $recipients = User::whereHas('userGroupsDeletable')->pluck('id');

        $this->assertNotContains(
            $anonymized->id,
            $recipients->all(),
            'The detach is what takes the anonymized admin off the newsletter list.'
        );
    }

    // =========================================================================
    // 2. The routing guard - the last line, and still the only one
    // =========================================================================

    public function test_no_mail_leaves_for_an_anonymized_user_who_kept_a_membership(): void
    {
        // Detaching is not the whole defence, because not every path detaches.
        // DeleteGroupDataProcess and the one-off backfill migration both call
        // $user->anonymize() on the model and leave memberships alone, which is
        // the state built here. What stops the mail then is
        // User::routeNotificationFor(): it returns null for any anonymized user
        // AND for any address failing FILTER_VALIDATE_EMAIL - and the
        // anonymized address is a 10-character token, not an address.
        //
        // METHOD NOTE, worth keeping: Notification::fake() cannot measure this.
        // The fake records the NOTIFIABLE and never resolves the address, so a
        // faked assertion happily "proves" a send that would never leave. This
        // test therefore runs without a fake and reads the transport.
        $admin = $this->inactiveUser('real-send@example.test');
        $group = $this->createGroup();
        $this->attachUserToGroup($admin, $group, 'admin');
        $this->successorFor($group);

        $admin->fresh()->anonymize();

        $this->assertSame(1, (int) User::find($admin->id)->isAnonymized);
        $this->assertDatabaseHas('group_user', [
            'user_id' => $admin->id,
            'group_id' => $group->id,
            'deleted_at' => null,
        ]);

        AdminNewsletter::factory()->create([
            'date' => today(),
            'send_newsletter' => 1,
            'status' => 1,
            'sent_time' => null,
            'send_to' => 'groupAdmins',
        ]);

        $this->artisan('newsletters:send-due')->assertExitCode(0);

        // REWRITTEN BY TODO 34. Laravel 9 replaced SwiftMailer with Symfony
        // Mailer, so `getSwiftMailer()` is gone: the transport is reached
        // through getSymfonyTransport(), it hands back SentMessage envelopes
        // rather than mails, and getTo() returns Address objects instead of an
        // address => name map. The measurement itself is unchanged - it is
        // still the list of addresses the transport was asked to deliver to.
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $recipients = collect($messages)
            ->flatMap(fn ($sent) => $sent->getOriginalMessage()->getTo())
            ->map(fn ($address) => $address->getAddress())
            ->all();

        $this->assertNotContains(
            User::find($admin->id)->email,
            $recipients,
            'No mail may start towards an anonymized user.'
        );
    }

    public function test_the_routing_guard_rejects_both_an_invalid_address_and_an_anonymized_flag(): void
    {
        // Either condition is enough on its own. That matters because a
        // replacement is far more likely to carry one of them across than both.
        $valid = $this->createUser(['email' => 'routable@example.test', 'isAnonymized' => 0]);
        $this->assertSame('routable@example.test', $valid->routeNotificationFor('mail'));

        $flagged = $this->createUser(['email' => 'flagged@example.test', 'isAnonymized' => 1]);
        $this->assertNull($flagged->routeNotificationFor('mail'), 'The flag alone closes it.');

        $tokenAddress = $this->createUser(['email' => 'nem-cim', 'isAnonymized' => 0]);
        $this->assertNull($tokenAddress->routeNotificationFor('mail'), 'The invalid address alone closes it.');
    }
}
