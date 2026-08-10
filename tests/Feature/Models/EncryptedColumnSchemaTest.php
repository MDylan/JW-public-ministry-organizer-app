<?php

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 13: the SCHEMA of the 9 encrypted columns, pinned.
 *
 * This file is the actual safety net underneath the migration squash
 * (TODO 32) and Laravel 11's native change() (TODO 66). The latter DROPS
 * every attribute that is not redeclared - nullable, default, charset - and
 * four of the nine below have exactly one ->change() migration behind them:
 *
 *   2022_04_12_205152  groups.name        string -> text
 *   2022_04_12_210544  events.comment     string(100) -> text
 *   2022_04_12_211517  group_posters.info string -> mediumText
 *   2022_04_12_212618  group_user.note    string -> text
 *
 * It follows the pattern of RouteContractSnapshotTest (TODO 01): a change in
 * the matrix should be deliberate, a visible diff, not a silent regression.
 * If this test fails after a framework hop, then the given migration lost an
 * attribute - and the encrypted content may be truncated or lost.
 *
 * The length limit is critical because: per the EncryptedAttributeTest
 * measurement, a 100-character plaintext's encrypted form already exceeds
 * 255, so a column that fell back to varchar would silently truncate (MySQL
 * in non-strict mode) or throw an error.
 */
class EncryptedColumnSchemaTest extends FeatureTestCase
{
    /**
     * Column => [data type, nullable, character maximum].
     *
     * Measured values from the kozter_testing schema, not an estimate.
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
        // The encrypted value is base64, hence ASCII - the charset by itself
        // would not corrupt it. But the charset is part of the same attribute
        // group that Laravel 11's change() drops, and if a column fell back
        // to utf8mb3, that would be the first sign of the MIGRATION's error,
        // before it caused data loss on a non-encrypted column.
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
        // The single most important assertion. EncryptedAttributeTest
        // measured that a 100-character text's encrypted form goes above
        // 255, and a short one's is ~200 - meaning a varchar(255) can hold
        // roughly 30 characters of plaintext, and a varchar(100) (the
        // original type of events.comment) can hold NONE AT ALL.
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
