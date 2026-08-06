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
 * TODO 13: a 9 `encrypted` cast alatti oszlop viselkedése.
 *
 * Ez a projekt legérzékenyebb adatfelülete. A rajta ejtett hiba nem hibaüzenet,
 * hanem olvashatatlan adat: egy elrontott ->change() migráció, egy rossz
 * APP_KEY, vagy egy castot megkerülő írás mind ide vezet.
 *
 * Eddig egyetlen teszt fedte (ModelFactoryTest), 9-ből 2 oszlopon, csak a
 * boldog úton. Ez a fájl a maradékot méri, és egyben elfogadási feltétel a
 * migrációk összevonásához (TODO 32) és a Laravel 11 natív change()-éhez
 * (TODO 66) - a séma oldalát a szomszédos EncryptedColumnSchemaTest pinneli.
 *
 * A keretrendszer oldaláról ellenőrzött kiindulópontok (Laravel 8.83):
 *   HasAttributes::setAttribute():941   - a null NYERSEN megy be
 *   HasAttributes::castAttribute():696  - a null NYERSEN jön vissza
 *   HasAttributes::fromEncryptedString():1200 - hibás payloadra DecryptException
 * A tesztek azt mérik, hogy a projekt modelljein tényleg így viselkedik.
 */
class EncryptedAttributeTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Az EventObserver auth()->user()-t olvas és értesítést küld, a
        // UserObserver pedig névindex-jobot indít minden íráskor.
        Notification::fake();
        $this->actingAs($this->createUser(['email' => 'encrypted-owner@example.test']));
    }

    /**
     * A titkosított oszlopok teljes listája.
     *
     * A lista maga is állítás: aki új `encrypted` castot vesz fel egy modellre,
     * de ide nem írja be, annak a test_every_encrypted_cast_is_covered() bukik.
     * Ugyanaz a minta, mint a ModelFactoryTest gyáralistája (TODO 04).
     *
     * PHPUnit 11 tiltja a nem statikus providereket, ezért statikus.
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

    /** Csak a nullable oszlopok. */
    public static function nullableEncryptedColumns(): array
    {
        return array_filter(self::encryptedColumns(), static fn ($row) => $row[3] === true);
    }

    /** Csak a NOT NULL oszlopok - ma kettő van. */
    public static function notNullEncryptedColumns(): array
    {
        return array_filter(self::encryptedColumns(), static fn ($row) => $row[3] === false);
    }

    /** A modell egy példánya, a megadott oszloppal beállítva. */
    private function makeWith(string $model, string $column, $value)
    {
        return $model::factory()->create([$column => $value]);
    }

    /** A nyers, adatbázisban tárolt érték - cast nélkül. */
    private function rawValue(string $table, $id, string $column)
    {
        return DB::table($table)->where('id', $id)->value($column);
    }

    // =========================================================================
    // 1. A lista teljessége
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

        // És valóban a keretrendszer payloadja, nem valami saját kódolás -
        // ez az, amire a NotifyUpcomingAnonymization kézi
        // Crypt::decryptString() hívásai (:104, :106) épülnek: azok nyers
        // joinból olvasnak, ahol az Eloquent cast nem fut le.
        $this->assertSame($value, Crypt::decryptString($raw));
    }

    /** @dataProvider encryptedColumns */
    public function test_the_same_text_produces_a_different_ciphertext_every_time(string $model, string $column, string $table): void
    {
        // Véletlen IV, tehát a titkosítás nem determinisztikus. Ennek a
        // legfontosabb következménye nem kriptográfiai, hanem gyakorlati:
        // ezeken az oszlopokon a where() SOHA nem talál - lásd a következő
        // tesztet.
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
        // KIMONDOTT SZABÁLY, amit eddig csak egy mellékmondat rögzített
        // (TODO 07.2): sem az assertDatabaseHas([$column => ...]), sem egy
        // where($column, $value) nem talál rá titkosított oszlopon. Ezért nem
        // lehet duplikált csoportnevet megakadályozni, és ezért létezik a
        // users.name_index, amit a CalulcateUserNameIndexProcess tart karban -
        // rendezni és keresni csak azon lehet.
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
    // 3. Null és üres string
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
        // Élő ellenőrzése a nullability-mátrixnak. A Laravel 11 natív
        // change()-e minden újra nem deklarált attribútumot eldob (TODO 66),
        // és mindkét NOT NULL oszlop mögött van egy ->change() migráció.
        $this->expectException(QueryException::class);

        $this->makeWith($model, $column, null);
    }

    /** @dataProvider encryptedColumns */
    public function test_an_empty_string_is_encrypted_not_treated_as_null(string $model, string $column, string $table): void
    {
        // Az üres string NEM null: a setAttribute() null-őre (`! is_null`) nem
        // fogja meg, tehát titkosítva tárolódik. Külön kell mérni, mert a
        // GroupUserMoves::attach() pont ilyet ír a note mezőbe.
        $record = $this->makeWith($model, $column, '');

        $raw = $this->rawValue($table, $record->getKey(), $column);

        $this->assertNotNull($raw);
        $this->assertNotSame('', $raw, 'Az üres string is titkosítva kerül a DB-be.');
        $this->assertSame('', $record->fresh()->{$column});
    }

    // =========================================================================
    // 4. Hossz - miért kellett minden oszlopot szélesíteni
    // =========================================================================

    /** @dataProvider encryptedColumns */
    public function test_the_ciphertext_has_a_fixed_two_hundred_character_floor(string $model, string $column, string $table): void
    {
        // MÉRT ÉRTÉK, nem becslés: az AES-256-CBC payload (iv + value + mac,
        // JSON-ba csomagolva, base64-elve) még egy 5 karakteres szövegre is
        // ~200 karakter. Ebből a legfontosabb következmény az events.comment
        // eredeti típusa: az string(100) volt, tehát abba EGYETLEN titkosított
        // érték sem fért volna bele - a mac önmagában 64 karakter.
        $record = $this->makeWith($model, $column, 'rövid');

        $length = strlen((string) $this->rawValue($table, $record->getKey(), $column));

        $this->assertGreaterThan(190, $length);
        $this->assertLessThan(255, $length, 'Rövid értékre a payload még belefér egy varchar(255)-be.');
    }

    /** @dataProvider encryptedColumns */
    public function test_ordinary_length_content_outgrows_a_varchar_255(string $model, string $column, string $table): void
    {
        // ÉS ITT A HATÁR. Egy 100 karakteres - vagyis teljesen hétköznapi -
        // szöveg titkosított alakja már 255 fölé megy. Egy varchar(255) oszlop
        // tehát nagyjából 30 karakternyi nyílt szöveget bír el, ami használhatatlan.
        // Ez a magyarázat mind a négy ->change() migrációra és a text/mediumText/
        // longText típusokra.
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
        // 4 KB nyílt szöveg titkosítva nagyjából 5,5 KB - egy TEXT oszlop
        // (65535 bájt) elbírja, a mediumText/longText oszlopok pedig sokkal
        // többet. Ez az alsó korlát, amit minden oszlopnak tudnia kell.
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
    // 5. Ami elrontja: a castot megkerülő írás és a rossz kulcs
    // =========================================================================

    /** @dataProvider encryptedColumns */
    public function test_a_raw_write_makes_the_column_unreadable(string $model, string $column, string $table): void
    {
        // A LEGFONTOSABB VÉDŐHÁLÓ a TODO 32 squash és minden későbbi
        // adatmigráció alá. Egy query builderes írás (vagy egy Model::insert(),
        // ami szintén megkerüli a castot - a TODO 04 három ilyen call site-ot
        // talált) nyílt szöveget tesz a titkosított oszlopba, és onnantól a
        // modellen keresztüli olvasás elszáll.
        $record = $this->makeWith($model, $column, 'eredeti');

        DB::table($table)->where('id', $record->getKey())->update([$column => 'nyers írás']);

        $this->expectException(DecryptException::class);

        $model::find($record->getKey())->{$column};
    }

    public function test_a_wrong_app_key_fails_loudly_instead_of_returning_garbage(): void
    {
        // Pontosítás a roadmap "silently data-destructive" megfogalmazásához:
        // a rossz kulcs OLVASÁSKOR HANGOS - DecryptException, nem null és nem
        // szemét. A veszély nem az olvasás, hanem az, ha valaki a hibát látva
        // felülírja a sort.
        $group = Group::factory()->create(['name' => 'Kulcspróba']);

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        app()->forgetInstance('encrypter');
        \Illuminate\Support\Facades\Crypt::clearResolvedInstance('encrypter');

        $this->expectException(DecryptException::class);

        Group::find($group->id)->name;
    }

    // =========================================================================
    // 6. A pivot-oszlop: a titkosítás a using() deklaráción múlik
    // =========================================================================

    public function test_an_updated_pivot_note_goes_through_the_cast(): void
    {
        // A LÉTEZŐ tagság frissítése az updateExistingPivotUsingCustomClass()
        // úton megy, ami fill()-t hív - tehát a cast lefut.
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
        // AZ ÚJ tagság egészen más úton megy, mint a frissítés:
        //   syncWithoutDetaching() -> attachNew() -> attach()
        //   -> attachUsingCustomClass() -> formatAttachRecord()
        //   -> castAttributes() -> newPivot()->fill()
        // A döntő lépés a castAttributes() (InteractsWithPivotTable:656):
        //
        //     return $this->using ? $this->newPivot()->fill($attributes)->getAttributes()
        //                         : $attributes;
        //
        // Vagyis a titkosítás KIZÁRÓLAG azon múlik, hogy a reláció deklarál-e
        // using(GroupUser::class)-t. Ha nem, a nyílt szöveg megy a DB-be. A
        // GroupUserMoves::attach() (:31) ezen az úton ír 'note' => ''-t minden
        // új tagságnál - a groupUsersAll() using()-ja miatt titkosítva.
        //
        // Ezt az utat a TODO 07.2 4. lelete már megjelölte mint
        // framework-verziófüggőt (ott a finish_guest_registration kapcsán);
        // itt a titkosítás oldaláról is rögzítve van.
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
        // A using() tehát nem stílus kérdése, hanem ez tartja titkosítva az
        // oszlopot - írásnál ÉS olvasásnál egyaránt. Ez a mátrix rögzíti, mely
        // relációk deklarálják; a megváltozása legyen látható diff, ne csendes
        // regresszió.
        //
        // A User::groupsAcceptedFiltered() a kivétel: NEM deklarál using()-ot,
        // ezért rajta a pivot egy sima Illuminate Pivot, casttal együtt.
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
        // A mátrix következménye, mérve. A groupsAcceptedFiltered() ugyanazt a
        // sort olvassa, mint a groupUsers() - de cast nélkül, tehát a nyers
        // base64 payloadot adja vissza, nem a jegyzetet.
        //
        // Ma ártalmatlan: a reláció egyetlen hívási helye sem olvassa a note-ot.
        // Az számít, hogy ha valaki egyszer mégis megteszi, vagy ha egy jövőbeli
        // refaktor egy ÍRÓ relációról felejti le a using()-ot, akkor nyílt
        // szöveg kerül a titkosított oszlopba - és az visszamenőleg nem
        // észlelhető.
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
