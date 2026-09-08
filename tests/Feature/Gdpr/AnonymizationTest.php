<?php

namespace Tests\Feature\Gdpr;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use App\Support\Gdpr\Anonymizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use Tests\Feature\FeatureTestCase;

/**
 * The behaviour of the anonymization trait, measured directly on the model
 * rather than through the nightly command.
 *
 * TODO 12 wrote sections 1-3 against Dialect\Gdpr\Anonymizable. Until then the
 * trait had only ever been exercised indirectly, through gdpr:anonymize-inactive
 * (TODO 06), and always with exactly ONE user - so its most important property,
 * uniqueness of the replacement address, had never been under test at all.
 *
 * TODO 33.2 replaced the trait with App\Support\Gdpr\Anonymizable and added
 * sections 4-5. Those first three sections passed unchanged across the swap,
 * which is what proves the in-house copy faithful.
 *
 * The declaration is two lists, and which one a column belongs in is a decision
 * worth reading as one:
 *
 *   $gdprAnonymizableFields   'column' => 'value'  -> the column gets that value
 *                             'column'             -> getAnonymized{Column}() supplies it
 *   $gdprNullFields           'column'             -> the column is emptied
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
    // 1. The field mapping
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

    public function test_the_password_becomes_unusable(): void
    {
        // TODO 33.2 reversed this. The password hash used to survive
        // anonymization untouched, because it was not in the field list at all;
        // logging in was still impossible only because the email half of the
        // credential pair was replaced. Keeping a working credential for
        // someone who asked to be forgotten was never a decision, just an
        // omission - getAnonymizedPassword() now replaces it with a hash of 64
        // random characters that nobody holds.
        $user = $this->inactiveUser('password@example.test');
        $hash = $user->password;

        $user->anonymize();

        $fresh = User::find($user->id);

        $this->assertNotSame($hash, $fresh->password);
        $this->assertFalse(
            Hash::check('password', $fresh->password),
            'The original password must no longer open the account.'
        );
        $this->assertNotNull($fresh->password, 'The column is NOT NULL, so it needs a value.');
    }

    // =========================================================================
    // 2. The e-mail - the trait's bare-value branch
    // =========================================================================

    public function test_the_anonymized_email_is_unique_per_user(): void
    {
        // Uniqueness is guaranteed by EXACTLY ONE thing: User::getAnonymizedEmail()
        // (:247). For the keyless 'email' entry the trait first looks for a
        // getAnonymized+Str::studly($val) method on the model, and only falls back
        // to parseValue() if there isn't one - which returns a string as itself, so
        // EVERY user would get the literal string 'email'. users.email is unique,
        // so the second anonymization would die with a duplicate key.
        //
        // Measured: removing the method makes this test fail with exactly that
        // SQLSTATE[23000] error. The model hook is therefore not a convenience, but
        // a precondition for the nightly command working at all - relevant to the
        // TODO 16 package-swap decision.
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
        // CHARACTERIZATION: getAnonymizedEmail() returns Str::random(10), so
        // users.email becomes a 10-character token with no @ sign - i.e. not an
        // e-mail address. Unique, but syntactically invalid.
        //
        // This matters if any sending path can reach an anonymized user: the
        // mailer throws an RFC error on an invalid address - SwiftMailer did
        // when this was written, Symfony Mailer does since TODO 34. The group
        // relations (Group::groupUsers, ::users) filter on isAnonymized, but
        // User::userGroupsEditable / ::userGroupsDeletable do NOT - and
        // newsletters:send-due selects precisely through those.
        $user = $this->inactiveUser('deliverable@example.test');

        $user->anonymize();

        $email = User::find($user->id)->email;

        $this->assertSame(10, strlen($email));
        $this->assertStringNotContainsString('@', $email, 'Nem cím, csak token.');
    }

    public function test_a_whole_batch_of_users_can_be_anonymized(): void
    {
        // This test measures exactly what the whole fix was made for: the nightly
        // command loops over inactive users. With a colliding e-mail it died on the
        // SECOND iteration, and from then on at the same place every day.
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
    // 3. The recursion - silently does nothing
    // =========================================================================

    public function test_the_recursion_into_events_and_groups_changes_nothing(): void
    {
        // The trait walks the $gdprWith relations (eventsOnly, groupsAccepted) and
        // calls anonymize() on every element. Both Event and Group use the trait too
        // - but BOTH have an empty array for $gdprAnonymizableFields, so the
        // recursion runs and does nothing.
        //
        // This means that events' and groups' data can still be tied back to the
        // user even after anonymization.
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
        // Group is Anonymizable, but with an empty field list - in that case the
        // trait does not even call update(), so no model events fire either.
        $group = $this->createGroup(['name' => 'Érintetlen']);

        $group->anonymize();

        $this->assertSame('Érintetlen', Group::find($group->id)->name);
    }

    // =========================================================================
    // 4. TODO 33.2: the $gdprNullFields list
    //
    // The user asked for an explicit option to simply empty a column instead of
    // storing a replacement in it, because for most of these there is nothing
    // worth keeping. The vendor trait could only express that as a null value
    // inside the single field list, which could not be told apart from "no
    // value was given". App\Support\Gdpr\Anonymizable now takes a separate
    // $gdprNullFields list, and User declares thirteen columns in it.
    // =========================================================================

    /**
     * A user carrying a value in every column anonymization touches, including
     * the four that are not mass-assignable and therefore have to be written
     * with forceFill().
     */
    private function fullyPopulatedUser(string $email): User
    {
        $user = $this->inactiveUser($email, [
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'accepted_gdpr' => 1,
            'last_login_time' => now()->subDay(),
            'calendars' => ['first-calendar'],
            'firstDay' => 1,
            'show_fields' => ['phone_number'],
            'opted_out_of_notifications' => ['newsletter'],
        ]);

        // NOT in User::$fillable, so neither the factory nor update() can set
        // them - which is exactly why they were being left behind.
        $user->forceFill([
            'two_factor_secret' => encrypt('TOTPSECRET'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code-one', 'code-two'])),
            'two_factor_confirmed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        return $user->fresh();
    }

    public function test_every_column_in_the_null_list_is_emptied(): void
    {
        $user = $this->fullyPopulatedUser('null-list@example.test');

        // Precondition: the assertions below are worthless if the fixture is
        // already empty. Seven of these were never anonymized before TODO 33.2.
        $this->assertNotNull($user->accepted_gdpr);
        $this->assertNotNull($user->last_login_time);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->calendars);

        $user->anonymize();

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
                "{$column} is declared in \$gdprNullFields, so it must be empty."
            );
        }
    }

    public function test_a_column_outside_fillable_is_emptied_too(): void
    {
        // This is the whole reason the trait writes with forceFill()->save()
        // instead of update(). remember_token is not in User::$fillable, so
        // update() would have dropped it silently - no error, no log, and a
        // live "remember me" cookie left working for someone who asked to be
        // forgotten. CONTROL: swap forceFill()->save() back to update() in
        // App\Support\Gdpr\Anonymizable and this test fails on the token while
        // every other column still passes.
        $user = $this->fullyPopulatedUser('not-fillable@example.test');

        $this->assertNotContains('remember_token', $user->getFillable());
        $this->assertNotNull($user->remember_token);

        $user->anonymize();

        $this->assertNull(User::find($user->id)->remember_token);
    }

    public function test_the_two_factor_credentials_are_destroyed(): void
    {
        // An encrypted TOTP secret and its recovery codes are credentials, and
        // they used to outlive the anonymization indefinitely.
        $user = $this->fullyPopulatedUser('two-factor@example.test');

        $user->anonymize();

        $fresh = User::find($user->id);

        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
        $this->assertNull(
            $fresh->two_factor_confirmed_at,
            'TODO 39.2: the confirmation is a nullable timestamp now, so it joins $gdprNullFields instead of being declared with a 0.'
        );
    }

    public function test_the_declared_replacement_values_are_written_as_declared(): void
    {
        // The other half of the split: these columns get a value because
        // something still reads them. role must stay a valid role string, and
        // isAnonymized is the flag every listing and the notification router
        // filter on.
        $user = $this->fullyPopulatedUser('declared@example.test');

        $user->anonymize();

        $fresh = User::find($user->id);

        $this->assertSame('Anonym', $fresh->name);
        $this->assertSame('registered', $fresh->role);
        $this->assertSame(1, (int) $fresh->isAnonymized);
    }

    // =========================================================================
    // 5. TODO 33.2: the trait mechanics that changed with the in-house rewrite
    // =========================================================================

    public function test_the_anonymized_hook_is_resolved_from_the_column_name(): void
    {
        // The vendor built the hook name from the declared VALUE, so it only
        // ever fired for the keyless form: 'name' => 'Anonym' looked for
        // getAnonymizedAnonym(). The in-house trait builds it from the COLUMN,
        // which is what the docblock always claimed.
        //
        // Both halves are asserted here: getAnonymizedEmail() still runs (the
        // address is a random token, not the literal string 'email'), and the
        // keyed 'name' entry is NOT diverted into a hook lookup on 'Anonym'.
        $user = $this->fullyPopulatedUser('hook@example.test');

        $user->anonymize();

        $fresh = User::find($user->id);

        $this->assertNotSame('email', $fresh->email);
        $this->assertSame(10, strlen($fresh->email));
        $this->assertSame('Anonym', $fresh->name);
    }

    public function test_a_keyless_field_without_a_hook_is_a_declaration_error(): void
    {
        // TODO 12 measured what the vendor did instead: it wrote the column's
        // own name into the column. Remove User::getAnonymizedEmail() and every
        // user gets the literal string 'email', so the SECOND row of a nightly
        // batch dies on a duplicate key and GDPR retention stops for good. A
        // declaration mistake now fails loudly, at the only moment it can.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('getAnonymizedNickname()');

        (new AnonymizableKeylessStub())->anonymize();
    }

    public function test_the_recursion_guard_stops_a_cycle(): void
    {
        // The vendor guard pushed relation names as array VALUES and tested
        // them as array KEYS, so it never matched. Nothing looped only because
        // Group and Event declare no $gdprWith, leaving the cascade one level
        // deep - the guard was never actually exercised.
        //
        // Here a model is its own $gdprWith relation. The relation is set by
        // hand so loadMissing() has nothing to query and the test touches no
        // table. CONTROL: delete the isset($visited[$path]) check in
        // App\Support\Gdpr\Anonymizable and this test recurses until PHP dies.
        $stub = new AnonymizableCycleStub();
        $stub->setRelation('mirror', $stub);

        $stub->anonymize();

        $this->assertSame(
            2,
            $stub->visits,
            'One outer call plus exactly one cascade entry; a third means the guard is dead.'
        );
    }
}

/**
 * A model declaring a keyless field with no matching getAnonymized hook.
 * Never saved: gdprAnonymizationUpdates() throws before anything is written.
 */
class AnonymizableKeylessStub extends Model
{
    use Anonymizable;

    protected $gdprAnonymizableFields = ['nickname'];
}

/**
 * A model whose $gdprWith relation is the model itself, i.e. the cycle the
 * vendor recursion guard was supposed to stop and never could.
 */
class AnonymizableCycleStub extends Model
{
    // The same trait alias App\Models\User uses, so the counter wraps the real
    // trait method rather than replacing it.
    use Anonymizable {
        Anonymizable::anonymize as protected anonymizeAttributes;
    }

    public int $visits = 0;

    protected $gdprAnonymizableFields = [];

    protected $gdprWith = ['mirror'];

    public function anonymize($visited = [])
    {
        $this->visits++;

        $this->anonymizeAttributes($visited);
    }
}
