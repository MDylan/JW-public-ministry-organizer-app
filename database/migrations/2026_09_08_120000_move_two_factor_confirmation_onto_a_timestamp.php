<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TODO 39.2: the two-factor confirmation moves onto Fortify's own column.
 *
 * WHY. composer.json pinned laravel/fortify at ~1.11.2, which declares
 * illuminate/support ^8.82|^9.0 and therefore admits no Laravel 10 release.
 * Lifting that ceiling crosses 1.12.0, where Fortify grew its own
 * two_factor_confirmed_at column and writes it from its own actions. This
 * project had answered the same question with a two_factor_confirmed boolean
 * of its own, so after the bump two columns would have claimed the same fact
 * and only one of them would have been written by the vendor.
 *
 * WHAT THIS DOES. Adds the nullable timestamp, carries the boolean's answer
 * across, then drops the boolean. Nothing else about the flow changes: the
 * application still confirms a second factor through its own controller and
 * its own two-factor.confirm route, and Fortify still registers no routes at
 * all, because FortifyServiceProvider calls Fortify::ignoreRoutes().
 *
 * THE BACKFILLED VALUE IS A MARKER, NOT A MEASUREMENT. The old schema stored
 * whether a second factor was confirmed, never when, so no real confirmation
 * time exists to recover. Rows that were confirmed get the row's own
 * updated_at - the closest thing to it the table holds - falling back to
 * created_at and then to now(). Everything that reads the column asks whether
 * it is null, so the value only has to be non-null; it must not be read as a
 * date somebody can act on.
 *
 * THE OTHER DIRECTION IS LOSSLESS in the only sense that matters: down() puts
 * the boolean back and sets it from "is the timestamp null", which is exactly
 * the information the boolean carried.
 */
class MoveTwoFactorConfirmationOntoATimestamp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('two_factor_confirmed_at')
                ->nullable()
                ->after('two_factor_recovery_codes');
        });

        DB::table('users')
            ->where('two_factor_confirmed', 1)
            ->update([
                'two_factor_confirmed_at' => DB::raw('COALESCE(`updated_at`, `created_at`, NOW())'),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_confirmed');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_confirmed')
                ->after('two_factor_recovery_codes')
                ->default(false);
        });

        DB::table('users')
            ->whereNotNull('two_factor_confirmed_at')
            ->update(['two_factor_confirmed' => 1]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_confirmed_at');
        });
    }
}
