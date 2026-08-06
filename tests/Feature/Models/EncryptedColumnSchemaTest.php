<?php

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 13: a 9 titkosított oszlop SÉMÁJA, pinelve.
 *
 * Ez a fájl a tulajdonképpeni védőháló a migrációk összevonása (TODO 32) és a
 * Laravel 11 natív change()-e (TODO 66) alá. Az utóbbi minden újra nem
 * deklarált attribútumot ELDOB - nullable, default, charset -, és a lenti
 * kilencből négy mögött épp egy ->change() migráció áll:
 *
 *   2022_04_12_205152  groups.name        string -> text
 *   2022_04_12_210544  events.comment     string(100) -> text
 *   2022_04_12_211517  group_posters.info string -> mediumText
 *   2022_04_12_212618  group_user.note    string -> text
 *
 * A RouteContractSnapshotTest (TODO 01) mintáját követi: a mátrix megváltozása
 * legyen szándékos, látható diff, ne csendes regresszió. Ha ez a teszt bukik
 * egy framework-hop után, akkor az adott migráció elvesztett egy attribútumot -
 * és a titkosított tartalom csonkolódhat vagy elvész.
 *
 * A hosszkorlát azért kritikus: az EncryptedAttributeTest mérése szerint egy
 * 100 karakteres nyílt szöveg titkosított alakja már 255 fölött van, tehát egy
 * varchar-ra visszaesett oszlop csendben csonkolna (MySQL nem strict módban)
 * vagy hibát dobna.
 */
class EncryptedColumnSchemaTest extends FeatureTestCase
{
    /**
     * Oszlop => [adattípus, nullable, karakteres maximum].
     *
     * Mért értékek a kozter_testing sémából, nem becslés.
     */
    private const EXPECTED_SCHEMA = [
        'users.name'             => ['text',       true,  65535],
        'users.phone_number'     => ['text',       true,  65535],
        'users.congregation'     => ['text',       true,  65535],
        'groups.name'            => ['text',       false, 65535],
        'groups.replyTo'         => ['text',       true,  65535],
        'events.comment'         => ['text',       true,  65535],
        'group_user.note'        => ['text',       true,  65535],
        'group_posters.info'     => ['mediumtext', false, 16777215],
        'group_messages.message' => ['longtext',   true,  4294967295],
    ];

    /** @return array<string, object> */
    private function columnMetadata(): array
    {
        $rows = DB::select(
            'select TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME
             from information_schema.COLUMNS
             where TABLE_SCHEMA = ?',
            [DB::getDatabaseName()]
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->TABLE_NAME.'.'.$row->COLUMN_NAME] = $row;
        }

        return $indexed;
    }

    public function test_every_encrypted_column_keeps_its_type_and_nullability(): void
    {
        $metadata = $this->columnMetadata();

        foreach (self::EXPECTED_SCHEMA as $column => [$type, $nullable, $maxLength]) {
            $this->assertArrayHasKey($column, $metadata, "A(z) {$column} oszlop eltűnt a sémából.");

            $actual = $metadata[$column];

            $this->assertSame(
                $type,
                strtolower($actual->DATA_TYPE),
                "A(z) {$column} típusa megváltozott. Ha ez szándékos, írd át az EXPECTED_SCHEMA-t."
            );

            $this->assertSame(
                $nullable,
                $actual->IS_NULLABLE === 'YES',
                "A(z) {$column} nullable volta megváltozott - ezt ejti el a Laravel 11 natív change()-e."
            );

            $this->assertSame(
                $maxLength,
                (int) $actual->CHARACTER_MAXIMUM_LENGTH,
                "A(z) {$column} hosszkorlátja megváltozott; egy szűkebb típus csonkolná a titkosított értéket."
            );
        }
    }

    public function test_every_encrypted_column_stays_on_utf8mb4(): void
    {
        // A titkosított érték base64, tehát ASCII - a charset önmagában nem
        // rontaná el. De a charset ugyanaz az attribútum-csoport, amit a
        // Laravel 11 change()-e elejt, és ha egy oszlop utf8mb3-ra esne vissza,
        // az a MIGRÁCIÓ hibájának első jele lenne, még mielőtt egy nem
        // titkosított oszlopon adatvesztést okozna.
        $metadata = $this->columnMetadata();

        foreach (array_keys(self::EXPECTED_SCHEMA) as $column) {
            $this->assertSame(
                'utf8mb4',
                $metadata[$column]->CHARACTER_SET_NAME,
                "A(z) {$column} karakterkészlete megváltozott."
            );
        }
    }

    public function test_no_encrypted_column_is_a_varchar(): void
    {
        // A legfontosabb egyetlen állítás. Az EncryptedAttributeTest kimérte,
        // hogy egy 100 karakteres szöveg titkosított alakja 255 fölé megy, egy
        // rövidé pedig ~200 - vagyis egy varchar(255) nagyjából 30 karakternyi
        // nyílt szöveget bír el, egy varchar(100) (az events.comment eredeti
        // típusa) pedig SEMENNYIT.
        $metadata = $this->columnMetadata();

        foreach (array_keys(self::EXPECTED_SCHEMA) as $column) {
            $this->assertNotSame(
                'varchar',
                strtolower($metadata[$column]->DATA_TYPE),
                "A(z) {$column} visszaesett varchar-ra; ebbe nem fér bele a titkosított érték."
            );
        }
    }
}
