<?php

namespace Tests\Feature\NewEmail;

use App\Models\PendingUserEmail;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 33.5, follow-up: the interleavings the first cut of the replacement did
 * not survive, found by an external audit of that change set.
 *
 * Every case here is a state the ordinary tests cannot reach, because a feature
 * test runs one request at a time. They are reproduced by writing the competing
 * state directly - through the query builder, or from a model event that fires
 * inside the window being measured - which is what a second process would have
 * done in production.
 *
 * The three defects, and what closed them:
 *
 *   1. `activate()` decided on the user instance the relation had already
 *      loaded, then saved it. A concurrent anonymization landing in between was
 *      undone, and silently: `save()` writes only the DIRTY attributes, so the
 *      row kept `isAnonymized = 1` while getting the real address back. Closed
 *      by a transaction that re-reads the user under `lockForUpdate()`.
 *   2. `UserObserver::deleted()` was the only cleanup path, and two live routes
 *      delete users through the query builder, which fires no model events.
 *      Closed by deleting one instance at a time on both.
 *   3. The clear-then-create of a pending row was not atomic and no unique index
 *      stood behind it. Closed by the same transaction-plus-lock rule and by
 *      `pending_user_emails_user_unique`.
 */
class PendingEmailIntegrityTest extends FeatureTestCase
{
    private function userWithPendingEmail(string $current, string $pending): array
    {
        Mail::fake();

        $user = $this->createUser([
            'email' => $current,
            'role' => 'registered',
            'isAnonymized' => 0,
        ]);
        $user->newEmail($pending);

        $token = DB::table('pending_user_emails')
            ->where('user_id', $user->getKey())
            ->value('token');

        return [$user, $token];
    }

    // =========================================================================
    // 1. The anonymization race
    // =========================================================================

    public function test_an_anonymisation_landing_after_the_user_was_loaded_still_wins(): void
    {
        [$user] = $this->userWithPendingEmail('race-gdpr@example.test', 'race-gdpr-new@example.test');

        $pending = PendingUserEmail::forUser($user)->firstOrFail();

        // Load the relation, which is what the controller does before calling
        // activate(). From here on the instance is a snapshot.
        $this->assertNotNull($pending->user);

        // The competing process: it anonymizes the row and, as anonymize() does,
        // clears the pending table. Written through the query builder so no
        // model event can refresh the snapshot the test is holding.
        DB::table('users')->where('id', $user->getKey())->update([
            'email' => 'anonymised-value',
            'isAnonymized' => 1,
            'email_verified_at' => null,
        ]);

        $this->assertSame(PendingUserEmail::REJECTED_ANONYMIZED, $pending->activate());

        $fresh = $user->fresh();

        $this->assertSame('anonymised-value', $fresh->email, 'The real address must not come back.');
        $this->assertSame(1, (int) $fresh->isAnonymized);
        $this->assertNull($fresh->email_verified_at, 'And it must not be marked verified either.');
    }

    public function test_the_stale_snapshot_would_have_left_the_row_reading_as_anonymised(): void
    {
        // The control that names the defect precisely. `save()` writes only the
        // dirty attributes, so the pre-fix code would have written `email`
        // alone - producing a row that says isAnonymized = 1 and carries the
        // real address. This asserts the pair together, because either half on
        // its own would have passed before the fix.
        [$user] = $this->userWithPendingEmail('race-pair@example.test', 'race-pair-new@example.test');

        $pending = PendingUserEmail::forUser($user)->firstOrFail();
        $pending->user;

        DB::table('users')->where('id', $user->getKey())->update([
            'email' => 'anonymised-pair',
            'isAnonymized' => 1,
        ]);

        $pending->activate();

        $row = DB::table('users')->where('id', $user->getKey())->first();

        $this->assertFalse(
            (int) $row->isAnonymized === 1 && $row->email === 'race-pair-new@example.test',
            'A row must never read as anonymized while carrying the requested address.'
        );
    }

    public function test_the_pending_row_is_dropped_when_the_activation_is_refused(): void
    {
        [$user] = $this->userWithPendingEmail('race-drop@example.test', 'race-drop-new@example.test');

        $pending = PendingUserEmail::forUser($user)->firstOrFail();
        $pending->user;

        DB::table('users')->where('id', $user->getKey())->update(['isAnonymized' => 1]);

        $pending->activate();

        $this->assertSame(
            0,
            DB::table('pending_user_emails')->where('user_id', $user->getKey())->count(),
            'The real address must not stay in the table after a refusal.'
        );

        // Asserted together on purpose: an activation that WROTE the address
        // also clears the table, so the count above does not discriminate on
        // its own. The pair does.
        $this->assertNotSame(
            'race-drop-new@example.test',
            $user->fresh()->email,
            'And it must have been a refusal, not a successful write.'
        );
    }

    // =========================================================================
    // 2. The collision window the pre-check cannot close
    // =========================================================================

    public function test_an_address_taken_between_the_check_and_the_write_is_reported_not_fatal(): void
    {
        // The pre-check in activate() cannot cover this: the competing row does
        // not exist when it runs. The listener below fires from inside the
        // window - between the check and the UPDATE - which is exactly where a
        // second request would have landed. Without the catch this is an
        // unhandled SQLSTATE[23000] during a signed-link GET, i.e. a 500.
        [$user, $token] = $this->userWithPendingEmail('window-old@example.test', 'window-target@example.test');

        $planted = false;

        User::updating(function (User $updating) use (&$planted) {
            if ($planted || $updating->email !== 'window-target@example.test') {
                return;
            }

            $planted = true;

            DB::table('users')->insert([
                'name' => 'Competitor',
                'email' => 'window-target@example.test',
                'password' => bcrypt('irrelevant'),
                'role' => 'registered',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $pending = PendingUserEmail::forUser($user)->firstOrFail();

        $this->assertSame(PendingUserEmail::REJECTED_TAKEN, $pending->activate());
        $this->assertTrue($planted, 'The competing row has to be planted inside the window.');

        $this->assertSame(
            'window-old@example.test',
            $user->fresh()->email,
            'A failed activation must not move the address.'
        );

        // And the visitor gets a message rather than a stack trace.
        $this->get($this->signedRoute('pendingEmail.verify', ['token' => $token]))
            ->assertRedirect(route('login'))
            ->assertSessionHas('profile_message');
    }

    // =========================================================================
    // 2b. The delete paths that fire no model events
    // =========================================================================

    public function test_the_hourly_purge_clears_the_pending_rows_of_the_users_it_deletes(): void
    {
        // `users:purge-unverified` used to delete through the query builder,
        // which fires no model events - so UserObserver::deleted() never ran.
        // These are the users most likely to have a pending row in the first
        // place: an unverified account is exactly the one that receives the
        // first confirmation mail.
        Mail::fake();

        $stale = $this->createUser([
            'email' => 'purge-stale@example.test',
            'email_verified_at' => null,
            'created_at' => now()->subDays(8),
        ]);
        $stale->newEmail('purge-stale-new@example.test');

        $this->artisan('users:purge-unverified')->assertExitCode(0);

        $this->assertNull(User::find($stale->getKey()));
        $this->assertSame(
            0,
            DB::table('pending_user_emails')->where('user_id', $stale->getKey())->count(),
            'A real, never-confirmed address must not outlive the user it belongs to.'
        );
    }

    public function test_the_hourly_purge_leaves_a_surviving_users_pending_row_alone(): void
    {
        // Control: the cleanup hangs off one predicate, and without this the
        // assertion above would stay green even if the purge emptied the whole
        // pending table.
        Mail::fake();

        $stale = $this->createUser([
            'email' => 'purge-doomed@example.test',
            'email_verified_at' => null,
            'created_at' => now()->subDays(8),
        ]);
        $stale->newEmail('purge-doomed-new@example.test');

        $kept = $this->createUser([
            'email' => 'purge-kept@example.test',
            'email_verified_at' => null,
            'created_at' => now()->subDays(2),
        ]);
        $kept->newEmail('purge-kept-new@example.test');

        $this->artisan('users:purge-unverified');

        $this->assertNotNull(User::find($kept->getKey()));
        $this->assertSame(
            'purge-kept-new@example.test',
            DB::table('pending_user_emails')->where('user_id', $kept->getKey())->value('email')
        );
    }

    public function test_cancelling_a_registration_clears_the_pending_row(): void
    {
        // `FinishRegistration::cancel()` deleted through the query builder for
        // the same reason and with the same consequence.
        Mail::fake();

        $registered = $this->createUser([
            'email' => 'cancel-me@example.test',
            'email_verified_at' => null,
            'role' => 'registered',
        ]);
        $registered->newEmail('cancel-me-new@example.test');

        // POST, not GET: a signed link proves the application issued it, not
        // that the user meant to open it, so the cancel button is a form.
        $this->post($this->signedRoute('finish_registration_cancel', ['id' => $registered->getKey()]))
            ->assertRedirect('/');

        $this->assertNull(User::find($registered->getKey()));
        $this->assertSame(
            0,
            DB::table('pending_user_emails')->where('user_id', $registered->getKey())->count()
        );
    }

    public function test_cancelling_leaves_a_user_who_does_not_match_the_predicate_untouched(): void
    {
        // Control, and it guards the rewrite specifically: the route now loads
        // an instance before deleting, so the three conditions have to stay on
        // the query rather than quietly becoming a findOrFail on the id.
        Mail::fake();

        $verified = $this->createUser([
            'email' => 'cancel-verified@example.test',
            'email_verified_at' => now(),
            'role' => 'registered',
        ]);
        $verified->newEmail('cancel-verified-new@example.test');

        $this->post($this->signedRoute('finish_registration_cancel', ['id' => $verified->getKey()]))
            ->assertRedirect('/');

        $this->assertNotNull(User::find($verified->getKey()));
        $this->assertSame(
            'cancel-verified-new@example.test',
            DB::table('pending_user_emails')->where('user_id', $verified->getKey())->value('email')
        );
    }

    // =========================================================================
    // 3. One pending row per user, enforced by the schema
    // =========================================================================

    public function test_the_pending_table_carries_a_unique_index_on_the_user(): void
    {
        $this->assertTrue(Schema::hasTable('pending_user_emails'));

        $indexes = collect(DB::select('SHOW INDEX FROM pending_user_emails'))
            ->where('Key_name', 'pending_user_emails_user_unique')
            ->sortBy('Seq_in_index');

        $this->assertCount(2, $indexes, 'The index covers user_type and user_id.');
        $this->assertSame([0, 0], $indexes->pluck('Non_unique')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(
            ['user_type', 'user_id'],
            $indexes->pluck('Column_name')->all()
        );
    }

    public function test_a_second_pending_row_for_the_same_user_is_rejected_by_the_database(): void
    {
        // The backstop under the transaction: even a code path that forgets the
        // lock cannot leave two live tokens behind.
        [$user] = $this->userWithPendingEmail('unique-one@example.test', 'unique-one-new@example.test');

        $this->expectException(QueryException::class);

        DB::table('pending_user_emails')->insert([
            'user_type' => User::class,
            'user_id' => $user->getKey(),
            'email' => 'unique-two-new@example.test',
            'token' => 'a-second-live-token',
            'created_at' => now(),
        ]);
    }

    public function test_two_users_may_wait_for_the_same_address(): void
    {
        // Control: the index is on the USER, not on the address. Two people can
        // legitimately be waiting for the same one - the first to confirm wins,
        // and activation deletes both rows.
        Mail::fake();

        $first = $this->createUser(['email' => 'shared-one@example.test']);
        $second = $this->createUser(['email' => 'shared-two@example.test']);

        $first->newEmail('contested@example.test');
        $second->newEmail('contested@example.test');

        $this->assertSame(
            2,
            DB::table('pending_user_emails')->where('email', 'contested@example.test')->count()
        );
    }

    public function test_a_resend_rotates_the_token_rather_than_adding_a_row(): void
    {
        // The clear-and-create now runs inside one transaction under a row lock.
        //
        // BE HONEST ABOUT WHAT THIS MEASURES: a single-threaded test cannot
        // produce the interleaving the LOCK exists for - two requests entering
        // between the clear and the create. This case states the uncontended
        // outcome, and the two below cover the parts that are observable from
        // one process: the transaction, and the unique index that backs it up.
        [$user] = $this->userWithPendingEmail('rotate@example.test', 'rotate-new@example.test');

        $before = DB::table('pending_user_emails')->where('user_id', $user->getKey())->value('token');

        $user->resendPendingEmailVerificationMail();

        $rows = DB::table('pending_user_emails')->where('user_id', $user->getKey())->get();

        $this->assertCount(1, $rows);
        $this->assertNotSame($before, $rows[0]->token);
    }

    public function test_a_failed_create_rolls_the_clear_back_and_leaves_the_old_row(): void
    {
        // The observable half of the transaction. Without it the clear commits
        // on its own, so a create that fails for any reason leaves the user
        // with NO pending row and no mail - the address they asked for silently
        // gone, and the profile page showing nothing to resend.
        [$user] = $this->userWithPendingEmail('rollback@example.test', 'rollback-old@example.test');

        PendingUserEmail::creating(function () {
            throw new RuntimeException('create failed inside the transaction');
        });

        try {
            $user->newEmail('rollback-new@example.test');
            $this->fail('The create was supposed to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('create failed inside the transaction', $e->getMessage());
        }

        $this->assertSame(
            'rollback-old@example.test',
            DB::table('pending_user_emails')->where('user_id', $user->getKey())->value('email'),
            'The earlier request must survive a failed replacement.'
        );
    }
}
