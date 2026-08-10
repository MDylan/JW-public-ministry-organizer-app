<?php

namespace Tests\Feature\Gdpr;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ReanonymizeUsersForTheNullFieldList;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 33.2: the one-off backfill that brings already-anonymized users up to
 * the current $gdprNullFields list.
 *
 * The migration itself is what these tests run. It has already been applied by
 * the time the suite boots - against an empty users table, which is why it
 * costs nothing there - so each test builds a legacy row and calls up() again.
 * That doubles as the idempotency check: the migration is written to be safe to
 * re-run, and the suite re-runs it on every one of these tests.
 */
class ReanonymizeBackfillTest extends FeatureTestCase
{
    private function migration(): ReanonymizeUsersForTheNullFieldList
    {
        require_once database_path(
            'migrations/2026_08_10_140000_reanonymize_users_for_the_null_field_list.php'
        );

        return new ReanonymizeUsersForTheNullFieldList();
    }

    /**
     * A user anonymized by the OLD code: address and name already replaced, but
     * every column TODO 33.2 added still carrying data.
     */
    private function legacyAnonymizedUser(string $token = 'AbCdEfGhIj'): User
    {
        $user = $this->createUser([
            'email' => $token,
            'name' => 'Anonym',
            'role' => 'registered',
            'isAnonymized' => 1,
            'password' => bcrypt('password'),
            'email_verified_at' => now()->subYear(),
            'accepted_gdpr' => 1,
            'last_login_time' => now()->subYear(),
            'calendars' => ['legacy-calendar'],
        ]);

        // Outside $fillable, so they have to be forced in - which is the same
        // reason the old update() based anonymizer never cleared them.
        $user->forceFill([
            'two_factor_secret' => encrypt('TOTPSECRET'),
            'two_factor_recovery_codes' => encrypt(json_encode(['one', 'two'])),
            'two_factor_confirmed' => 1,
            'remember_token' => Str::random(60),
        ])->save();

        return $user->fresh();
    }

    public function test_it_empties_every_column_the_old_anonymizer_left_behind(): void
    {
        $user = $this->legacyAnonymizedUser();

        // Precondition: without this the assertions below could pass vacuously.
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->remember_token);
        $this->assertNotNull($user->email_verified_at);

        $this->migration()->up();

        $fresh = User::find($user->id);

        foreach ([
            'phone_number',
            'congregation',
            'show_fields',
            'opted_out_of_notifications',
            'last_login_ip',
            'firstDay',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'remember_token',
            'calendars',
            'last_login_time',
            'email_verified_at',
            'accepted_gdpr',
        ] as $column) {
            $this->assertNull(
                $fresh->getAttribute($column),
                "{$column} must be empty after the backfill."
            );
        }

        $this->assertSame(0, (int) $fresh->two_factor_confirmed);
    }

    public function test_it_replaces_the_surviving_password_hash(): void
    {
        // The sharpest of the columns the old anonymizer missed: the row kept
        // the real password hash of someone who had asked to be forgotten.
        $user = $this->legacyAnonymizedUser();
        $original = $user->password;

        $this->migration()->up();

        $fresh = User::find($user->id);

        $this->assertNotSame($original, $fresh->password);
        $this->assertFalse(Hash::check('password', $fresh->password));
        $this->assertNotNull($fresh->password, 'The column is NOT NULL.');
    }

    public function test_two_anonymized_users_do_not_end_up_sharing_a_hash(): void
    {
        // Per row rather than one shared hash, matching what
        // User::getAnonymizedPassword() does for every new anonymization.
        $first = $this->legacyAnonymizedUser('AaAaAaAaAa');
        $second = $this->legacyAnonymizedUser('BbBbBbBbBb');

        $this->migration()->up();

        $this->assertNotSame(
            User::find($first->id)->password,
            User::find($second->id)->password
        );
    }

    public function test_it_leaves_the_identifying_columns_alone(): void
    {
        // Already handled by the old anonymizer, so re-randomizing them would
        // be churn: the address is already a unique token, name is the
        // placeholder and role is already registered.
        $user = $this->legacyAnonymizedUser('KeepThisXy');

        $this->migration()->up();

        $fresh = User::find($user->id);

        $this->assertSame('KeepThisXy', $fresh->email);
        $this->assertSame('Anonym', $fresh->name);
        $this->assertSame('registered', $fresh->role);
        $this->assertSame(1, (int) $fresh->isAnonymized);
    }

    public function test_it_does_not_touch_a_user_who_is_not_anonymized(): void
    {
        // The whole migration hangs off one predicate. If it were ever dropped,
        // the backfill would wipe the entire user table's credentials - so this
        // is the assertion that matters most.
        $active = $this->createUser([
            'email' => 'active@example.test',
            'password' => bcrypt('password'),
            'isAnonymized' => 0,
            'email_verified_at' => now(),
            'last_login_time' => now(),
        ]);
        $active->forceFill(['remember_token' => Str::random(60)])->save();

        $before = $active->fresh();

        $this->migration()->up();

        $fresh = User::find($active->id);

        $this->assertSame('active@example.test', $fresh->email);
        $this->assertSame($before->password, $fresh->password);
        $this->assertSame($before->remember_token, $fresh->remember_token);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertNotNull($fresh->last_login_time);
    }

    public function test_running_it_twice_changes_nothing_beyond_the_hash(): void
    {
        $user = $this->legacyAnonymizedUser();

        $this->migration()->up();
        $afterFirst = User::find($user->id);

        $this->migration()->up();
        $afterSecond = User::find($user->id);

        $this->assertSame($afterFirst->email, $afterSecond->email);
        $this->assertNull($afterSecond->remember_token);
        $this->assertNull($afterSecond->email_verified_at);

        // The only thing a second pass does is hash again, which is why the
        // migration is safe to re-run but not free.
        $this->assertNotSame($afterFirst->password, $afterSecond->password);
    }

    public function test_it_is_a_no_op_when_there_is_nothing_to_backfill(): void
    {
        // The production run happens once; every later boot of a fresh database
        // hits this path, including every test run.
        $this->assertSame(0, User::where('isAnonymized', 1)->count());

        $this->migration()->up();

        $this->assertSame(0, User::where('isAnonymized', 1)->count());
    }
}
