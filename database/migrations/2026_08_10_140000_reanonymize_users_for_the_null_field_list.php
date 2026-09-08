<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * TODO 33.2: bring already-anonymized users up to the current field lists.
 *
 * WHY THIS EXISTS
 *
 * Anonymization used to leave every column in the list below untouched, plus
 * the password hash and two_factor_confirmed. A user anonymized before
 * TODO 33.2 therefore still carries data they asked to have removed - including
 * a working password hash, a usable remember_token and, where two-factor was
 * enabled, the encrypted TOTP secret with its recovery codes.
 *
 * No ordinary run will ever reach those rows: gdpr:anonymize-inactive selects on
 * `isAnonymized = 0`, so a row is anonymized exactly once. Hence this backfill.
 *
 * It deliberately does NOT touch email, name, role or isAnonymized. The old
 * anonymizer already handled all four, so rewriting them would be churn with no
 * effect - and re-randomizing an address that is already a unique token buys
 * nothing.
 *
 * WHY IT WRITES DIRECTLY INSTEAD OF CALLING User::anonymize()
 *
 * Two reasons, both deliberate:
 *
 *  1. User::anonymize() consults AnonymizationPolicy and returns false when the
 *     succession rule blocks - silently, by design. A blocked row would keep
 *     its real password hash with nothing reporting it, which is the one
 *     failure this migration must not have. The guard protects a live
 *     administrator from being demoted; these rows are already anonymized and
 *     already `registered`, so it has nothing left to protect.
 *     This is the same trap the 2024_12_01_223022 backfill still has.
 *
 *  2. A migration is a record of what was done at a point in time. Freezing the
 *     column list here means a later edit to User::$gdprNullFields cannot
 *     retroactively change what this migration does on a fresh database. When
 *     that list grows, it needs its own backfill - as it did here.
 *
 * The list below is the state of User::$gdprNullFields as of TODO 33.2.
 *
 * COST AND SAFETY
 *
 * The runtime is one bcrypt per affected row and is dominated by it, so on an
 * installation with a long retention history this migration takes minutes, not
 * seconds. That is why set_time_limit() is lifted: the updater runs `migrate`
 * from inside a web request. Everything else is a single UPDATE.
 *
 * Idempotent: running it twice only costs another pass of hashing. Harmless in
 * the test suite, where migrations run against an empty users table.
 *
 * Covered by tests/Feature/Gdpr/ReanonymizeBackfillTest.php.
 */
class ReanonymizeUsersForTheNullFieldList extends Migration
{
    /**
     * User::$gdprNullFields as of TODO 33.2, frozen. See the docblock.
     */
    private const NULL_COLUMNS = [
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
    ];

    public function up()
    {
        // The password rehash is the slow half and the updater calls `migrate`
        // from a web request, where the default limit would cut it off partway.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $anonymized = DB::table('users')->where('isAnonymized', 1);

        if ((clone $anonymized)->doesntExist()) {
            return;
        }

        // One statement for everything that is the same on every row.
        // two_factor_confirmed was NOT NULL when this migration was written, so
        // it got its default rather than joining the null list - exactly as
        // User declared it at the time.
        //
        // TODO 39.2 later dropped that column in favour of Fortify's nullable
        // two_factor_confirmed_at. On a fresh install this migration still runs
        // BEFORE that one, so the column is there and the write is the original
        // one; the guard only decides what happens when this up() is invoked
        // against a schema that has already moved past it, which is what
        // ReanonymizeBackfillTest does. Nothing is lost in that case either:
        // TODO 39.2 derives the timestamp from this very boolean, so a row this
        // migration has touched cannot come out confirmed.
        $sameOnEveryRow = array_fill_keys(self::NULL_COLUMNS, null);

        if (Schema::hasColumn('users', 'two_factor_confirmed')) {
            $sameOnEveryRow['two_factor_confirmed'] = 0;
        }

        (clone $anonymized)->update(array_merge(
            $sameOnEveryRow,
            ['updated_at' => now()]
        ));

        // The password cannot be emptied - the column is NOT NULL - so each row
        // gets a hash of 64 random characters that are discarded immediately.
        // Per row rather than one shared hash, so the backfill produces exactly
        // what User::getAnonymizedPassword() produces for every new
        // anonymization; a reader should not have to work out why they differ.
        (clone $anonymized)->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('users')
                    ->where('id', $row->id)
                    ->update(['password' => Hash::make(Str::random(64))]);
            }
        });
    }

    /**
     * Not reversible, and deliberately silent about it.
     *
     * The data this removed is gone: the password hashes, the TOTP secrets and
     * the timestamps were overwritten, not moved. Throwing here would only
     * block a rollback of the migrations around it for no benefit.
     */
    public function down()
    {
        //
    }
}
