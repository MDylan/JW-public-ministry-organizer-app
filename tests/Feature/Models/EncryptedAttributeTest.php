<?php

namespace Tests\Feature\Models;

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\GroupPosters;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 13: the behavior of the 9 columns under the `encrypted` cast.
 *
 * This is the project's most sensitive data surface. A defect here is not an
 * error message but unreadable data: a botched ->change() migration, a wrong
 * APP_KEY, or a write that bypasses the cast all lead here.
 *
 * So far only one test covered it (ModelFactoryTest), on 2 of the 9 columns,
 * and only on the happy path. This file measures the rest, and is also an
 * acceptance condition for the migration squash (TODO 32) and Laravel 11's
 * native change() (TODO 66) - the schema side is pinned by the neighboring
 * EncryptedColumnSchemaTest.
 *
 * Starting points verified on the framework side (Laravel 8.83):
 *   HasAttributes::setAttribute():941   - null goes in RAW
 *   HasAttributes::castAttribute():696  - null comes back RAW
 *   HasAttributes::fromEncryptedString():1200 - DecryptException on a bad payload
 * The tests measure that the project's models actually behave this way.
 */
class EncryptedAttributeTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // EventObserver reads auth()->user() and sends a notification, and
        // UserObserver dispatches a name-index job on every write.
        Notification::fake();
        $this->actingAs($this->createUser(['email' => 'encrypted-owner@example.test']));
    }

    /**
     * The full list of encrypted columns.
     *
     * The list itself is an assertion: whoever adds a new `encrypted` cast to
     * a model but does not add it here will fail
     * test_every_encrypted_cast_is_covered(). Same pattern as
     * ModelFactoryTest's factory list (TODO 04).
     *
     * PHPUnit 11 forbids non-static providers, hence static.
     *
     * @return array<string, array{0: class-string, 1: string, 2: string, 3: bool}>
     */
    public static function encryptedColumns(): array
    {
        return [
            'users.name'             => [User::class, 'name', 'users', true],
            'users.phone_number'     => [User::class, 'phone_number', 'users', true],
            'users.congregation'     => [User::class, 'congregation', 'users', true],
            'groups.name'            => [Group::class, 'name', 'groups', false],
            'groups.replyTo'         => [Group::class, 'replyTo', 'groups', true],
            'events.comment'         => [Event::class, 'comment', 'events', true],
            'group_user.note'        => [GroupUser::class, 'note', 'group_user', true],
            'group_posters.info'     => [GroupPosters::class, 'info', 'group_posters', false],
            'group_messages.message' => [GroupMessage::class, 'message', 'group_messages', true],
        ];
    }

    /** Only the nullable columns. */
    public static function nullableEncryptedColumns(): array
    {
        return array_filter(self::encryptedColumns(), static fn ($row) => $row[3] === true);
    }

    /** Only the NOT NULL columns - there are two today. */
    public static function notNullEncryptedColumns(): array
    {
        return array_filter(self::encryptedColumns(), static fn ($row) => $row[3] === false);
    }

    /** An instance of the model, with the given column set. */
    private function makeWith(string $model, string $column, $value)
    {
        return $model::factory()->create([$column => $value]);
    }

    /** The raw value stored in the database - without the cast. */
    private function rawValue(string $table, $id, string $column)
    {
        return DB::table($table)->where('id', $id)->value($column);
    }

    // =========================================================================
    // 1. Completeness of the list
    // =========================================================================

    public function test_every_encrypted_cast_is_covered(): void
    {
        $models = [User::class, Group::class, Event::class, GroupUser::class, GroupPosters::class, GroupMessage::class];

        $found = [];
        foreach ($models as $model) {
            $instance = new $model();
            foreach ($instance->getCasts() as $column => $cast) {
                if (str_starts_with((string) $cast, 'encrypted')) {
                    $found[] = $instance->getTable().'.'.$column;
                }
            }
        }

        sort($found);
        $covered = array_keys(self::encryptedColumns());
        sort($covered);

        $this->assertSame(
            $covered,
            $found,
            'Új encrypted cast került egy modellre - vedd fel az encryptedColumns() listába.'
        );
    }

    // =========================================================================
    // 2. Round trip
    // =========================================================================

    /** @dataProvider encryptedColumns */
    public function test_the_value_round_trips_through_the_cast(string $model, string $column, string $table): void
    {
        $value = 'Árvíztűrő tükörfúrógép';

        $record = $this->makeWith($model, $column, $value);

        $this->assertSame($value, $record->fresh()->{$column});
    }

    /** @dataProvider encryptedColumns */
    public function test_the_stored_column_is_not_plain_text(string $model, string $column, string $table): void
    {
        $value = 'Titkos érték '.$column;

        $record = $this->makeWith($model, $column, $value);
        $raw = $this->rawValue($table, $record->getKey(), $column);

        $this->assertNotSame($value, $raw, 'A nyers oszlopban nem állhat nyílt szöveg.');
        $this->assertStringNotContainsString('Titkos', (string) $raw);

        // And indeed it is the framework's payload, not some custom encoding -
        // this is what NotifyUpcomingAnonymization's manual
        // Crypt::decryptString() calls (:104, :106) build on: those read from
        // a raw join, where the Eloquent cast does not run.
        $this->assertSame($value, Crypt::decryptString($raw));
    }

    /** @dataProvider encryptedColumns */
    public function test_the_same_text_produces_a_different_ciphertext_every_time(string $model, string $column, string $table): void
    {
        // Random IV, so the encryption is not deterministic. The most
        // important consequence of this is not cryptographic, but practical:
        // on these columns, where() NEVER finds a match - see the next test.
        $value = 'Ismétlődő szöveg';

        $first = $this->makeWith($model, $column, $value);
        $second = $this->makeWith($model, $column, $value);

        $this->assertNotSame(
            $this->rawValue($table, $first->getKey(), $column),
            $this->rawValue($table, $second->getKey(), $column),
            'Azonos szöveghez nem tartozhat azonos titkosított érték.'
        );
    }

    /** @dataProvider encryptedColumns */
    public function test_the_column_cannot_be_searched_with_a_plain_where(string $model, string $column, string $table): void
    {
        // A STATED RULE that so far only a side clause has recorded
        // (TODO 07.2): neither assertDatabaseHas([$column => ...]) nor a
        // where($column, $value) finds a match on an encrypted column.
        // Because of this, a duplicate group name cannot be prevented, and
        // this is why users.name_index exists, maintained by
        // CalulcateUserNameIndexProcess - only that can be used for sorting and searching.
        $value = 'Kereshetetlen érték';

        $this->makeWith($model, $column, $value);

        $this->assertSame(
            0,
            DB::table($table)->where($column, $value)->count(),
            'A titkosított oszlopon a where() nem találhat.'
        );
        $this->assertDatabaseMissing($table, [$column => $value]);
    }

    // =========================================================================
    // 3. Null and the empty string
    // =========================================================================

    /** @dataProvider nullableEncryptedColumns */
    public function test_null_is_stored_and_read_back_as_null(string $model, string $column, string $table): void
    {
        $record = $this->makeWith($model, $column, null);

        $this->assertNull($record->fresh()->{$column});
        $this->assertNull(
            $this->rawValue($table, $record->getKey(), $column),
            'A null nyersen megy be, nem titkosított üres stringként.'
        );
    }

    /** @dataProvider notNullEncryptedColumns */
    public function test_a_not_null_column_rejects_null(string $model, string $column, string $table): void
    {
        // Live check of the nullability matrix. Laravel 11's native change()
        // drops every attribute that is not redeclared (TODO 66), and both
        // NOT NULL columns have a ->change() migration behind them.
        $this->expectException(QueryException::class);

        $this->makeWith($model, $column, null);
    }

    /** @dataProvider encryptedColumns */
    public function test_an_empty_string_is_encrypted_not_treated_as_null(string $model, string $column, string $table): void
    {
        // The empty string is NOT null: setAttribute()'s null check
        // (`! is_null`) does not catch it, so it is stored encrypted. Needs
        // to be measured separately, because GroupUserMoves::attach() writes
        // exactly this into the note field.
        $record = $this->makeWith($model, $column, '');

        $raw = $this->rawValue($table, $record->getKey(), $column);

        $this->assertNotNull($raw);
        $this->assertNotSame('', $raw, 'Az üres string is titkosítva kerül a DB-be.');
        $this->assertSame('', $record->fresh()->{$column});
    }

    // =========================================================================
    // 4. Length - why every column had to be widened
    // =========================================================================

    /** @dataProvider encryptedColumns */
    public function test_the_ciphertext_has_a_fixed_two_hundred_character_floor(string $model, string $column, string $table): void
    {
        // MEASURED VALUE, not an estimate: the AES-256-CBC payload (iv +
        // value + mac, packed into JSON, base64-encoded) is ~200 characters
        // even for a 5-character text. The most important consequence of
        // this is events.comment's original type: it was string(100), so NOT
        // A SINGLE encrypted value would have fit into it - the mac alone is 64 characters.
        $record = $this->makeWith($model, $column, 'rövid');

        $length = strlen((string) $this->rawValue($table, $record->getKey(), $column));

        $this->assertGreaterThan(190, $length);
        $this->assertLessThan(255, $length, 'Rövid értékre a payload még belefér egy varchar(255)-be.');
    }

    /** @dataProvider encryptedColumns */
    public function test_ordinary_length_content_outgrows_a_varchar_255(string $model, string $column, string $table): void
    {
        // AND HERE IS THE LIMIT. A 100-character - i.e. completely ordinary -
        // text's encrypted form already goes above 255. A varchar(255) column
        // can therefore hold roughly 30 characters of plaintext, which is
        // unusable. This is the explanation for all four ->change()
        // migrations and for the text/mediumText/longText types.
        $value = str_repeat('a', 100);

        $record = $this->makeWith($model, $column, $value);

        $this->assertGreaterThan(
            255,
            strlen((string) $this->rawValue($table, $record->getKey(), $column))
        );
    }

    /** @dataProvider encryptedColumns */
    public function test_a_multi_kilobyte_value_survives_the_round_trip(string $model, string $column, string $table): void
    {
        // 4 KB of plaintext encrypted is roughly 5.5 KB - a TEXT column
        // (65535 bytes) can hold it, and the mediumText/longText columns much
        // more. This is the lower bound that every column must be able to handle.
        $value = str_repeat('Hosszú megjegyzés éíűő. ', 170);

        $record = $this->makeWith($model, $column, $value);

        $this->assertSame($value, $record->fresh()->{$column});
        $this->assertGreaterThan(
            strlen($value),
            strlen((string) $this->rawValue($table, $record->getKey(), $column)),
            'A titkosított alak mindig hosszabb a nyílt szövegnél.'
        );
    }

    // =========================================================================
    // 5. What breaks it: a write that bypasses the cast, and a wrong key
    // =========================================================================

    /** @dataProvider encryptedColumns */
    public function test_a_raw_write_makes_the_column_unreadable(string $model, string $column, string $table): void
    {
        // THE MOST IMPORTANT SAFETY NET underneath the TODO 32 squash and
        // every later data migration. A query-builder write (or a
        // Model::insert(), which also bypasses the cast - TODO 04 found three
        // such call sites) puts plaintext into the encrypted column, and from
        // then on reading through the model fails.
        $record = $this->makeWith($model, $column, 'eredeti');

        DB::table($table)->where('id', $record->getKey())->update([$column => 'nyers írás']);

        $this->expectException(DecryptException::class);

        $model::find($record->getKey())->{$column};
    }

    public function test_a_wrong_app_key_fails_loudly_instead_of_returning_garbage(): void
    {
        // Clarification to the roadmap's "silently data-destructive" wording:
        // a wrong key is LOUD ON READ - a DecryptException, not null and not
        // garbage. The danger is not the read, but if someone, seeing the error, overwrites the row.
        $group = Group::factory()->create(['name' => 'Kulcspróba']);

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        app()->forgetInstance('encrypter');
        \Illuminate\Support\Facades\Crypt::clearResolvedInstance('encrypter');

        $this->expectException(DecryptException::class);

        Group::find($group->id)->name;
    }

    // =========================================================================
    // 6. The pivot column: encryption depends on the using() declaration
    // =========================================================================

    public function test_an_updated_pivot_note_goes_through_the_cast(): void
    {
        // Updating an EXISTING membership goes through the
        // updateExistingPivotUsingCustomClass() path, which calls fill() -
        // so the cast runs.
        $user = $this->createUser(['email' => 'pivot-update@example.test']);
        $group = $this->createGroup(['name' => 'Pivot csoport']);
        $this->attachUserToGroup($user, $group, 'member');

        $group->groupUsersAll()->updateExistingPivot($user->id, ['note' => 'Frissített jegyzet']);

        $raw = DB::table('group_user')
            ->where('user_id', $user->id)->where('group_id', $group->id)
            ->value('note');

        $this->assertNotSame('Frissített jegyzet', $raw, 'A frissítés titkosít.');
        $this->assertSame('Frissített jegyzet', Crypt::decryptString($raw));
    }

    public function test_a_freshly_attached_pivot_note_also_goes_through_the_cast(): void
    {
        // A NEW membership goes through an entirely different path than an update:
        //   syncWithoutDetaching() -> attachNew() -> attach()
        //   -> attachUsingCustomClass() -> formatAttachRecord()
        //   -> castAttributes() -> newPivot()->fill()
        // The decisive step is castAttributes() (InteractsWithPivotTable:656):
        //
        //     return $this->using ? $this->newPivot()->fill($attributes)->getAttributes()
        //                         : $attributes;
        //
        // In other words, encryption depends EXCLUSIVELY on whether the
        // relation declares using(GroupUser::class). If not, the plaintext
        // goes into the DB. GroupUserMoves::attach() (:31) writes 'note' =>
        // '' via this path for every new membership - encrypted because of
        // groupUsersAll()'s using().
        //
        // This path was already flagged by TODO 07.2's finding 4 as
        // framework-version-dependent (there in the context of
        // finish_guest_registration); here it is recorded from the
        // encryption side as well.
        $user = $this->createUser(['email' => 'pivot-attach@example.test']);
        $group = $this->createGroup(['name' => 'Attach csoport']);

        $group->groupUsersAll()->syncWithoutDetaching([
            $user->id => ['group_role' => 'member', 'note' => 'Friss jegyzet', 'hidden' => 0],
        ]);

        $raw = DB::table('group_user')
            ->where('user_id', $user->id)->where('group_id', $group->id)
            ->value('note');

        $this->assertNotSame('Friss jegyzet', $raw, 'Az attach-út is titkosít, mert a reláció using()-ot deklarál.');
        $this->assertSame('Friss jegyzet', Crypt::decryptString($raw));
    }

    public function test_the_pivot_class_of_every_relation_exposing_the_note_column(): void
    {
        // using() is therefore not a matter of style - it is what keeps the
        // column encrypted, both on write AND on read. This matrix records
        // which relations declare it; a change to it should be a visible
        // diff, not a silent regression.
        //
        // User::groupsAcceptedFiltered() is the exception: it does NOT
        // declare using(), so on it the pivot is a plain Illuminate Pivot, without the cast.
        $user = new User();
        $group = new Group();

        $actual = [
            'Group::groupUsers'            => $group->groupUsers()->getPivotClass(),
            'Group::groupUsersAll'         => $group->groupUsersAll()->getPivotClass(),
            'User::userGroups'             => $user->userGroups()->getPivotClass(),
            'User::groupsAcceptedFiltered' => $user->groupsAcceptedFiltered()->getPivotClass(),
        ];

        $this->assertSame([
            'Group::groupUsers'            => GroupUser::class,
            'Group::groupUsersAll'         => GroupUser::class,
            'User::userGroups'             => GroupUser::class,
            'User::groupsAcceptedFiltered' => Pivot::class,
        ], $actual);
    }

    public function test_a_relation_without_using_hands_back_the_raw_ciphertext(): void
    {
        // The consequence of the matrix, measured. groupsAcceptedFiltered()
        // reads the same row as groupUsers() - but without the cast, so it
        // returns the raw base64 payload, not the note.
        //
        // Harmless today: none of the relation's call sites read the note.
        // What matters is that if someone ever does, or if a future refactor
        // forgets the using() on a WRITING relation, then plaintext ends up
        // in the encrypted column - and that cannot be detected retroactively.
        $user = $this->createUser(['email' => 'no-using@example.test']);
        $group = $this->createGroup(['name' => 'Cast nélküli olvasás']);
        $this->attachUserToGroup($user, $group, 'member');

        $group->groupUsersAll()->updateExistingPivot($user->id, ['note' => 'Jegyzet']);

        $withCast = Group::find($group->id)->groupUsers()->first()->pivot->note;
        $withoutCast = User::find($user->id)->groupsAcceptedFiltered()->first()->pivot->note;

        $this->assertSame('Jegyzet', $withCast);
        $this->assertNotSame('Jegyzet', $withoutCast);
        $this->assertSame('Jegyzet', Crypt::decryptString($withoutCast));
    }
}
