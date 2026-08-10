<?php

namespace Tests\Unit\Models;

use App\Models\Group;
use App\Models\GroupUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * TODO 29: `$casts` instead of the `$dates` property.
 *
 * WHY THIS IS NEEDED
 *
 * `protected $dates` was removed in Laravel 10 - both `Model::getDates()` and
 * the property itself. Two models use it: `Group` (`['deleted_at']`) and
 * `GroupUser` (`['created_at','updated_at','deleted_at']`). The rewrite
 * itself is trivial; the question is whether it is BEHAVIORALLY identical,
 * and nothing has asserted that so far.
 *
 * The two cases are not symmetric:
 *
 *  - `Group` has no `$casts` array, and the `SoftDeletes` trait casts
 *    `deleted_at` anyway (`initializeSoftDeletes()` writes it into `$casts`
 *    if it's not already there). The `$dates` line there was thus already
 *    redundant to begin with.
 *  - `GroupUser` is a custom `Pivot`, with `SoftDeletes` and
 *    `$incrementing = true`, and ALREADY HAS a `$casts` array (`signs`,
 *    `note`) - so this is a merge. Its timestamp handling, moreover, comes
 *    from `AsPivot`, which sets the value of `$timestamps` from the loaded
 *    attributes (`AsPivot.php:44,77`), not fixed to true. Because of this it
 *    is not self-evident that `created_at` / `updated_at` come back as
 *    dates - we measure that here, not assume it.
 *
 * This file went green BEFORE the swap, and must stay green after it too. If
 * any of its cases flips, that is a real behavior change.
 */
class DateCastingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsDomainFixtures;

    public function test_the_group_deleted_at_column_comes_back_as_a_date(): void
    {
        $group = $this->createGroup();
        $group->delete();

        $trashed = Group::onlyTrashed()->findOrFail($group->id);

        $this->assertInstanceOf(Carbon::class, $trashed->deleted_at);
    }

    public function test_the_group_user_timestamps_come_back_as_dates(): void
    {
        $user = $this->createUser();
        $group = $this->createGroup();
        $pivot = $this->attachUserToGroup($user, $group);

        $fresh = GroupUser::findOrFail($pivot->id);

        $this->assertInstanceOf(Carbon::class, $fresh->created_at, 'The Pivot derives the value of $timestamps from the attributes - this line measures that created_at is really a date.');
        $this->assertInstanceOf(Carbon::class, $fresh->updated_at);
    }

    public function test_the_group_user_deleted_at_column_comes_back_as_a_date(): void
    {
        $user = $this->createUser();
        $group = $this->createGroup();
        $pivot = $this->attachUserToGroup($user, $group);
        $pivot->delete();

        $trashed = GroupUser::onlyTrashed()->findOrFail($pivot->id);

        $this->assertInstanceOf(Carbon::class, $trashed->deleted_at);
    }

    /**
     * The `GroupUser` `$casts` array does not contain only dates: `note` is
     * `encrypted`, `signs` is `array`. TODO 13's round-trip tests cover these
     * separately, but if the merge mangles the array, that should show up
     * here too - a lost `encrypted` cast would mean unencrypted data in the
     * database.
     */
    public function test_the_other_group_user_casts_survive_alongside_the_dates(): void
    {
        $user = $this->createUser();
        $group = $this->createGroup();
        $pivot = $this->attachUserToGroup($user, $group);

        $pivot->note = 'titkos megjegyzés';
        $pivot->signs = ['a', 'b'];
        $pivot->save();

        $fresh = GroupUser::findOrFail($pivot->id);
        $this->assertSame('titkos megjegyzés', $fresh->note);
        $this->assertSame(['a', 'b'], $fresh->signs);

        $raw = $this->getConnection()->table('group_user')->where('id', $pivot->id)->value('note');
        $this->assertNotSame('titkos megjegyzés', $raw, 'The note column must be encrypted in the database.');
    }

    /**
     * This is the property that would break under Laravel 10. The test does
     * not measure behavior, but that the migration happened - and that no
     * one puts it back.
     *
     * Via Reflection, not by searching the source file: the first version was
     * `assertStringNotContainsString('protected $dates', ...)`, and it
     * matched an explanatory comment written ALONGSIDE the swap, so it still
     * failed after the fix. Querying the declaring class tells us what we
     * want to know - `$dates` still exists on the `Model` ancestor in
     * Laravel 8, it's just that these models must not declare it again.
     */
    public function test_neither_model_declares_the_removed_dates_property(): void
    {
        foreach ([Group::class, GroupUser::class] as $model) {
            $ownDeclarations = array_filter(
                (new \ReflectionClass($model))->getProperties(),
                fn (\ReflectionProperty $p) => $p->getName() === 'dates'
                    && $p->getDeclaringClass()->getName() === $model
            );

            $this->assertSame(
                [],
                $ownDeclarations,
                $model.' still declares the $dates property, which Laravel 10 removed.'
            );
        }
    }
}
