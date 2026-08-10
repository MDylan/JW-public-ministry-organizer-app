<?php

namespace Tests\Feature\Seeders;

use App\Models\Settings;
use App\Models\StaticPage;
use App\Models\User;
use Database\Seeders\CoreSettingsSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\StaticPagesSetupSeeder;
use Tests\Feature\FeatureTestCase;

/**
 * Guards TODO 04: the seeders must be invocable, idempotent, and must not
 * depend on setup-flow-only arguments.
 */
class SeederTest extends FeatureTestCase
{
    public function test_database_seeder_creates_the_core_settings(): void
    {
        Settings::query()->delete();

        $this->seed(DatabaseSeeder::class);

        foreach (['registration', 'claim_group_creator', 'default_language', 'languages', 'terms_checkbox', 'maintenance', 'group_data_retention'] as $name) {
            $this->assertDatabaseHas('settings', ['name' => $name]);
        }

        // A megőrzési idő alapból kikapcsolt: egy friss telepítés semmit nem
        // törölhet, amíg az adminisztrátor tudatosan be nem kapcsolja.
        $this->assertDatabaseHas('settings', ['name' => 'group_data_retention', 'value' => '0']);
    }

    public function test_the_seeded_language_blob_has_the_shape_every_consumer_expects(): void
    {
        // TODO 33.3. This seeder used to write {"hu":"hu"} - a bare string -
        // while the installer wrote the object form. Everything that reads the
        // blob does $value['visible'], and reaching that on a string is a
        // TypeError on PHP 8, so `db:seed` produced a database whose language
        // switcher, group form and translation screen would all fatal on
        // render. The installer path was fine, which is why nothing noticed.
        //
        // CONTROL: restore json_encode([$lang => $lang]) in CoreSettingsSeeder
        // and this test fails on the first assertion.
        Settings::query()->delete();

        $this->seed(CoreSettingsSeeder::class);

        $blob = json_decode(Settings::where('name', 'languages')->value('value'), true);

        $this->assertIsArray($blob);

        foreach ($blob as $code => $value) {
            $this->assertIsArray($value, "The entry for {$code} must be an array, not a bare string.");
            $this->assertArrayHasKey('name', $value);
            $this->assertArrayHasKey('visible', $value);
        }
    }

    public function test_core_settings_seeder_is_idempotent_and_preserves_existing_values(): void
    {
        Settings::query()->delete();
        Settings::create(['name' => 'registration', 'value' => '0']);

        $this->seed(CoreSettingsSeeder::class);
        $this->seed(CoreSettingsSeeder::class);

        // Meglévő értéket nem ír felül, és nem duplikál.
        $this->assertSame(1, Settings::where('name', 'registration')->count());
        $this->assertSame('0', Settings::where('name', 'registration')->value('value'));
    }

    public function test_static_pages_seeder_runs_with_an_explicit_owner(): void
    {
        // Ez a telepítő útvonala: Setup\AccountController callWith()-szel hív.
        // A FeatureTestCase::setUp() már létrehozott egy owner@example.test
        // mainAdmint, ezért itt egy másodikat adunk át explicit módon, hogy
        // az argumentum tényleges hatása látszódjon.
        $owner = $this->createUser(['email' => 'seeder-owner@example.test', 'role' => 'mainAdmin']);

        StaticPage::query()->delete();
        // A paraméterek név szerint kötődnek (container->call), ezért a
        // kulcsnak egyeznie kell a run() argumentumának nevével - pontosan
        // úgy, ahogy a Setup\AccountController hívja.
        (new DatabaseSeeder())->setContainer($this->app)->callWith(StaticPagesSetupSeeder::class, [
            'user_id' => $owner->id,
        ]);

        foreach (['home', 'contact', 'terms', 'help'] as $slug) {
            $this->assertDatabaseHas('static_pages', ['slug' => $slug, 'user_id' => $owner->id]);
        }
    }

    public function test_static_pages_seeder_falls_back_to_the_first_admin_without_an_argument(): void
    {
        // Ez teszi lehetővé az `artisan db:seed --class=StaticPagesSetupSeeder`
        // hívást, ami korábban ArgumentCountError-ral szállt el.
        // Tulajdonosnak az első mainAdmin kerül be - itt a setUp()-ban
        // létrehozott owner@example.test.
        $firstAdmin = User::where('role', 'mainAdmin')->orderBy('id')->firstOrFail();
        $this->createUser(['email' => 'seeder-later-admin@example.test', 'role' => 'mainAdmin']);

        StaticPage::query()->delete();
        $this->seed(StaticPagesSetupSeeder::class);

        $this->assertDatabaseHas('static_pages', ['slug' => 'home', 'user_id' => $firstAdmin->id]);
        $this->assertSame(4, StaticPage::count());
    }

    public function test_static_pages_seeder_is_a_no_op_without_any_admin(): void
    {
        StaticPage::query()->delete();
        User::where('role', 'mainAdmin')->delete();

        $this->seed(StaticPagesSetupSeeder::class);

        $this->assertSame(0, StaticPage::count());
    }
}
