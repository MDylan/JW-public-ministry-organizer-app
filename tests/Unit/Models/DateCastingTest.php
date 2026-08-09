<?php

namespace Tests\Unit\Models;

use App\Models\Group;
use App\Models\GroupUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * TODO 29: a `$dates` property helyett `$casts`.
 *
 * MIÉRT KELL EZ
 *
 * `protected $dates` a Laravel 10-ben megszűnt - a `Model::getDates()` és a
 * property is. Két modell használja: `Group` (`['deleted_at']`) és `GroupUser`
 * (`['created_at','updated_at','deleted_at']`). Az átírás önmagában triviális,
 * a kérdés az, hogy VISELKEDÉSBEN azonos-e, és arra eddig semmi nem állított.
 *
 * A két eset nem szimmetrikus:
 *
 *  - `Group`-nak nincs `$casts` tömbje, és a `deleted_at`-et a `SoftDeletes`
 *    trait amúgy is castolja (`initializeSoftDeletes()` beírja a `$casts`-ba,
 *    ha nincs ott). A `$dates` sor ott tehát eleve redundáns volt.
 *  - `GroupUser` egyedi `Pivot`, `SoftDeletes`-szel és `$incrementing = true`-val,
 *    és MÁR VAN `$casts` tömbje (`signs`, `note`) - ez tehát összeolvasztás.
 *    A timestamp-kezelése ráadásul az `AsPivot`-ból jön, ami a `$timestamps`
 *    értékét a betöltött attribútumokból állítja (`AsPivot.php:44,77`), nem
 *    fixen igazra. Emiatt nem magától értetődő, hogy a `created_at` /
 *    `updated_at` dátumként jön vissza - ezt itt mérjük, nem feltételezzük.
 *
 * Ez a fájl a csere ELŐTT lett zöld, és utána is zöldnek kell maradnia. Ha
 * bármelyik esete elfordul, az valódi viselkedésváltozás.
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

        $this->assertInstanceOf(Carbon::class, $fresh->created_at, 'A Pivot a $timestamps értékét az attribútumokból veszi - ez a sor méri, hogy a created_at tényleg dátum.');
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
     * A `GroupUser` `$casts` tömbje nem csak dátumokat tartalmaz: a `note`
     * `encrypted`, a `signs` `array`. A TODO 13 körjárati tesztjei ezeket
     * külön fedik, de ha az összeolvasztás elírja a tömböt, az itt is
     * látszódjon - egy elveszett `encrypted` cast titkosítatlan adatot
     * jelentene az adatbázisban.
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
        $this->assertNotSame('titkos megjegyzés', $raw, 'A note oszlopnak titkosítva kell az adatbázisban lennie.');
    }

    /**
     * Ez a property az, ami a Laravel 10-ben elesne. A teszt nem a
     * viselkedést méri, hanem azt, hogy a migráció megtörtént - és hogy senki
     * ne tegye vissza.
     *
     * Reflectionnel, nem a forrásfájlban keresve: az első változat
     * `assertStringNotContainsString('protected $dates', ...)` volt, és az
     * illeszkedett a csere MELLÉ írt magyarázó kommentre, tehát a javítás után
     * is bukott. A deklaráló osztály lekérdezése azt mondja meg, amit tudni
     * akarunk - a `$dates` a `Model` ősön Laravel 8-ban még létezik, csak
     * ezek a modellek nem deklarálhatják felül.
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
                $model.' még deklarálja a $dates propertyt, amit a Laravel 10 eltávolított.'
            );
        }
    }
}
