<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CalulcateUserNameIndexProcess;
use App\Models\StaticPage;
use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 05: real handle() execution.
 *
 * This job is dispatched from UserObserver on every user create/update/delete,
 * so it runs constantly in production. It rewrites User.name_index for the
 * whole table using a locale-aware Collator - which makes it sensitive to both
 * the intl extension and to the `name` column being an encrypted cast.
 */
class CalulcateUserNameIndexProcessTest extends FeatureTestCase
{
    /**
     * The job reindexes the entire users table, so it needs a deterministic
     * starting state. FeatureTestCase::setUp() creates an owner
     * user and a static page tied to them via a foreign key -
     * so that must be cleared too.
     */
    private function clearUsers(): void
    {
        StaticPage::query()->delete();
        User::query()->delete();
    }

    public function test_handle_assigns_a_contiguous_index_in_locale_sorted_name_order(): void
    {
        $this->clearUsers();

        $this->createUser(['email' => 'c@example.test', 'name' => 'Csaba']);
        $this->createUser(['email' => 'a@example.test', 'name' => 'Aladár']);
        $this->createUser(['email' => 'b@example.test', 'name' => 'Béla']);

        (new CalulcateUserNameIndexProcess())->handle();

        $indexes = User::orderBy('name_index')->get()->pluck('name')->all();

        $this->assertSame(['Aladár', 'Béla', 'Csaba'], $indexes);
        $this->assertSame([1, 2, 3], User::orderBy('name_index')->pluck('name_index')->map(fn ($i) => (int) $i)->all());
    }

    public function test_handle_sorts_hungarian_accented_names_by_collator_not_by_byte_order(): void
    {
        // This is the job's reason for existing: in byte order, "Zs" would come before "Ö".
        // The Collator in the Hungarian locale produces the correct alphabetical order.
        $this->clearUsers();

        $this->createUser(['email' => 'zs@example.test', 'name' => 'Zsolt']);
        $this->createUser(['email' => 'o@example.test', 'name' => 'Ödön']);
        $this->createUser(['email' => 'a@example.test', 'name' => 'Anna']);

        (new CalulcateUserNameIndexProcess())->handle();

        $this->assertSame(
            ['Anna', 'Ödön', 'Zsolt'],
            User::orderBy('name_index')->get()->pluck('name')->all()
        );
    }

    public function test_handle_reindexes_after_a_user_is_removed(): void
    {
        $this->clearUsers();

        $this->createUser(['email' => 'a@example.test', 'name' => 'Anna']);
        $removed = $this->createUser(['email' => 'b@example.test', 'name' => 'Béla']);
        $this->createUser(['email' => 'c@example.test', 'name' => 'Csaba']);

        (new CalulcateUserNameIndexProcess())->handle();
        $removed->forceDelete();
        (new CalulcateUserNameIndexProcess())->handle();

        // Az indexek nem hagynak lyukat.
        $this->assertSame([1, 2], User::orderBy('name_index')->pluck('name_index')->map(fn ($i) => (int) $i)->all());
    }

    public function test_handle_is_a_no_op_on_an_empty_user_table(): void
    {
        $this->clearUsers();

        (new CalulcateUserNameIndexProcess())->handle();

        $this->assertSame(0, User::count());
    }

    public function test_job_declares_a_stable_unique_id(): void
    {
        // ShouldBeUnique: UserObserver dispatches this from four different places;
        // this lock prevents duplicate runs.
        $this->assertSame('CalculateUserNameIndex', (new CalulcateUserNameIndexProcess())->uniqueId());
    }
}
