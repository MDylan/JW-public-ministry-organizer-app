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
     * A job a teljes users táblát újraindexeli, ezért determinisztikus
     * kiindulóállapot kell. A FeatureTestCase::setUp() létrehoz egy owner
     * felhasználót és egy hozzá tartozó statikus oldalt, ami idegen kulccsal
     * fogja - ezért azt is takarítani kell.
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
        // Ez a job létjogosultsága: bájtsorrendben az "Zs" az "Ö" elé kerülne.
        // A Collator magyar locale-ban a helyes ábécérendet adja.
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
        // ShouldBeUnique: az UserObserver négy helyről is dispatch-eli, a
        // duplikált futásokat ez a lock akadályozza meg.
        $this->assertSame('CalculateUserNameIndex', (new CalulcateUserNameIndexProcess())->uniqueId());
    }
}
