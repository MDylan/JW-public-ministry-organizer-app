<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TODO 33.5: one pending address per user, enforced by the schema.
 *
 * The rule has always been the intent - MustVerifyNewEmail clears before it
 * creates - but nothing enforced it. `morphs('user')` gives a plain index, not a
 * unique one, so two concurrent profile updates could interleave their clear and
 * create and leave two rows behind. Two rows means two live signed links, and a
 * resend that no longer invalidates the earlier token, which is the whole point
 * of rotating it.
 *
 * The application side is fixed in the same change set (a transaction holding a
 * row lock on the user); this index is the backstop underneath it, and the only
 * thing that also covers rows written by a future second code path.
 *
 * DUPLICATES ARE RESOLVED BEFORE THE INDEX IS ADDED, deterministically: the
 * highest `id` per user survives. It is the most recently requested address, so
 * it is the one whose link the user is actually waiting for - and it is what an
 * uncontended run of the code would have left behind anyway. Any tie is
 * impossible, `id` being the primary key.
 *
 * The plain morphs index is dropped because the unique one covers every query
 * that used it - `scopeForUser()` filters on exactly those two columns, in that
 * order.
 */
class AddUniqueUserIndexToPendingUserEmails extends Migration
{
    public function up()
    {
        $this->deleteDuplicateRows();

        Schema::table('pending_user_emails', function (Blueprint $table) {
            $table->dropIndex('pending_user_emails_user_type_user_id_index');
            $table->unique(['user_type', 'user_id'], 'pending_user_emails_user_unique');
        });
    }

    public function down()
    {
        Schema::table('pending_user_emails', function (Blueprint $table) {
            $table->dropUnique('pending_user_emails_user_unique');
            $table->index(['user_type', 'user_id'], 'pending_user_emails_user_type_user_id_index');
        });
    }

    /**
     * Keep the newest row per user, drop the rest.
     *
     * Written with the query builder rather than through the model on purpose:
     * a migration must not change meaning later because the model's behaviour
     * did. Deleting by explicit id list also means a mistake in the survivor
     * query cannot turn into an unbounded delete.
     */
    private function deleteDuplicateRows(): void
    {
        $survivors = DB::table('pending_user_emails')
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_type', 'user_id')
            ->pluck('id')
            ->all();

        if ($survivors === []) {
            return;
        }

        DB::table('pending_user_emails')->whereNotIn('id', $survivors)->delete();
    }
}
