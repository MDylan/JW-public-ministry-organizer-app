<?php

namespace Tests\Feature\Gdpr;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12: a Dialect\Gdpr\Anonymizable trait viselkedése.
 *
 * Eddig csak közvetve, a gdpr:anonymize-inactive parancson keresztül volt
 * mérve (TODO 06), és MINDIG pontosan egy felhasználóval - ezért a legfontosabb
 * tulajdonsága, az egyediség, sosem került terítékre.
 *
 * A trait a $gdprAnonymizableFields tömböt járja be. Kétféle alakot ismer:
 *   'kulcs' => 'érték'  -> a mező az értéket kapja
 *   'mező'              -> a mező SAJÁT MAGÁT kapja értékül (parseValue())
 * Az utóbbi az, ami az e-mailnél végzetes.
 */
class AnonymizationTest extends FeatureTestCase
{
    private function inactiveUser(string $email, array $attributes = []): User
    {
        return $this->createUser(array_merge([
            'email' => $email,
            'role' => 'registered',
            'name' => 'Eredeti Név',
            'phone_number' => '+36301234567',
            'congregation' => 'Gyülekezet',
            'isAnonymized' => 0,
            'last_login_ip' => '10.0.0.1',
        ], $attributes));
    }

    // =========================================================================
    // 1. A mezőleképzés
    // =========================================================================

    public function test_anonymize_maps_every_declared_field(): void
    {
        $user = $this->inactiveUser('map@example.test');

        $user->anonymize();

        $fresh = User::find($user->id);

        $this->assertNotNull($fresh, 'A sor megmarad, csak az adatai cserélődnek.');
        $this->assertSame('Anonym', $fresh->name);
        $this->assertSame('registered', $fresh->role);
        $this->assertNull($fresh->phone_number);
        $this->assertNull($fresh->congregation);
        $this->assertNull($fresh->last_login_ip);
        $this->assertNull($fresh->firstDay);
        $this->assertNull($fresh->show_fields);
        $this->assertNull($fresh->opted_out_of_notifications);
        $this->assertSame(1, (int) $fresh->isAnonymized);
    }

    public function test_the_password_survives_anonymization(): void
    {
        // A jelszó NINCS a $gdprAnonymizableFields-ben, tehát a hash megmarad.
        // Belépni mégsem lehet vele: az e-mail cserélődik, ami a másik
        // azonosító fele. Ezt sehol nem írta le semmi.
        $user = $this->inactiveUser('password@example.test');
        $hash = $user->password;

        $user->anonymize();

        $this->assertSame($hash, User::find($user->id)->password);
    }

    // =========================================================================
    // 2. Az e-mail - a trait bare-érték ága
    // =========================================================================

    public function test_the_anonymized_email_is_unique_per_user(): void
    {
        // Az egyediséget EGYETLEN dolog biztosítja: a User::getAnonymizedEmail()
        // (:247). A trait a kulcs nélküli 'email' elemnél előbb a
        // getAnonymized+Str::studly($val) metódust keresi a modellen, és csak
        // ha nincs, esik vissza a parseValue()-ra - ami stringre önmagát adja
        // vissza, tehát MINDEN usernek a literál 'email' jutna. A users.email
        // unique, így a második anonimizálás duplikált kulccsal elszállna.
        //
        // Mérve: a metódus eltávolításával ez a teszt pontosan azzal a
        // SQLSTATE[23000] hibával bukik. A modell horgja tehát nem kényelmi
        // elem, hanem a napi parancs működésének feltétele - fontos a TODO 16
        // csomagcsere-döntéséhez.
        $first = $this->inactiveUser('first@example.test');
        $second = $this->inactiveUser('second@example.test');

        $first->anonymize();
        $second->anonymize();

        $firstEmail = User::find($first->id)->email;
        $secondEmail = User::find($second->id)->email;

        $this->assertNotSame('first@example.test', $firstEmail);
        $this->assertNotSame('second@example.test', $secondEmail);
        $this->assertNotSame(
            $firstEmail,
            $secondEmail,
            'Két anonimizált felhasználó nem kaphatja ugyanazt az e-mailt.'
        );
    }

    public function test_the_anonymized_email_is_not_an_address_at_all(): void
    {
        // KARAKTERIZÁLÁS: a getAnonymizedEmail() Str::random(10)-et ad, tehát a
        // users.email egy 10 karakteres token lesz, @ jel nélkül - vagyis nem
        // e-mail cím. Egyedi, de szintaktikailag érvénytelen.
        //
        // Ez akkor számít, ha bármelyik küldési út el tud jutni egy anonimizált
        // felhasználóig: a SwiftMailer RFC-hibát dob érvénytelen címre. A
        // group-relációk (Group::groupUsers, ::users) szűrik az isAnonymized-et,
        // a User::userGroupsEditable / ::userGroupsDeletable viszont NEM - és a
        // newsletters:send-due pont ezeken válogat.
        $user = $this->inactiveUser('deliverable@example.test');

        $user->anonymize();

        $email = User::find($user->id)->email;

        $this->assertSame(10, strlen($email));
        $this->assertStringNotContainsString('@', $email, 'Nem cím, csak token.');
    }

    public function test_a_whole_batch_of_users_can_be_anonymized(): void
    {
        // Ez a teszt méri azt, amiért az egész javítás készült: a napi parancs
        // ciklusban megy végig az inaktív felhasználókon. Ütköző e-maillel a
        // MÁSODIK iterációnál elszállt, és onnantól minden nap ugyanott.
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $users[] = $this->inactiveUser('batch-'.$i.'@example.test');
        }

        foreach ($users as $user) {
            $user->anonymize();
        }

        $emails = User::whereIn('id', collect($users)->pluck('id'))->pluck('email');

        $this->assertCount(5, $emails->unique(), 'Mind az öt e-mailnek különböznie kell.');
    }

    // =========================================================================
    // 3. A rekurzió - csendben nem csinál semmit
    // =========================================================================

    public function test_the_recursion_into_events_and_groups_changes_nothing(): void
    {
        // A trait a $gdprWith relációin (eventsOnly, groupsAccepted) végigmegy,
        // és mindegyik elemre meghívja az anonymize()-t. Az Event és a Group
        // használja is a traitet - de MINDKETTŐ $gdprAnonymizableFields-e üres
        // tömb, tehát a rekurzió lefut és nem csinál semmit.
        //
        // Ez azt jelenti, hogy az események és a csoportok adatai az
        // anonimizálás után is a felhasználóhoz köthetők maradnak.
        $user = $this->inactiveUser('recursion@example.test');
        $this->actingAs($user);

        $group = $this->createGroup(['name' => 'Megmaradó csoport']);
        $this->attachUserToGroup($user, $group, 'member');

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);
        $event = $this->createEventInRange($group, $user, $date, '08:00', '09:00');
        $event->update(['comment' => 'Eredeti megjegyzés']);

        $user->fresh()->anonymize();

        $this->assertSame('Megmaradó csoport', Group::find($group->id)->name);
        $this->assertSame('Eredeti megjegyzés', Event::find($event->id)->comment);
        $this->assertSame(
            $user->id,
            (int) Event::find($event->id)->user_id,
            'Az esemény továbbra is a felhasználóhoz kötött.'
        );
    }

    public function test_group_anonymize_is_a_no_op(): void
    {
        // A Group is Anonymizable, de üres mezőlistával - a trait ilyenkor
        // update()-et sem hív, tehát a modell-események sem sülnek el.
        $group = $this->createGroup(['name' => 'Érintetlen']);

        $group->anonymize();

        $this->assertSame('Érintetlen', Group::find($group->id)->name);
    }
}
