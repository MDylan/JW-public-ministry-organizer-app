<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Admin\Settings;
use App\Models\Settings as SettingsModel;
use App\Models\User;
use App\Notifications\TestNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07: Admin\Settings was smoke-only. At 329 lines it is the largest admin
 * component, and it drives the whole application's configuration.
 *
 * Deliberately NOT covered: saveOthers() and languageSetDefault(). Both call
 * setEnvironment::setEnvironmentValue(), which rewrites the real environment
 * file returned by app()->environmentFilePath() - under APP_ENV=testing that is
 * `.env.testing`, so invoking them from a test would corrupt the test
 * configuration itself. That untestability is a finding in its own right and is
 * recorded in the roadmap (it also feeds TODO 28: move env() into config).
 */
class AdminSettingsTest extends FeatureTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser(['email' => 'settings-admin@example.test', 'role' => 'mainAdmin']);
        $this->actingAs($this->admin);
    }

    private function setLanguages(array $languages): void
    {
        SettingsModel::updateOrCreate(['name' => 'languages'], ['value' => json_encode($languages)]);
    }

    private function storedLanguages(): array
    {
        return json_decode(SettingsModel::where('name', 'languages')->value('value'), true) ?? [];
    }

    // --- mount / load ---

    public function test_mount_loads_stored_settings_over_the_defaults(): void
    {
        SettingsModel::updateOrCreate(['name' => 'registration'], ['value' => '0']);

        $component = Livewire::actingAs($this->admin)->test(Settings::class);

        $this->assertSame('0', $component->get('state')['others']['registration']);
    }

    public function test_mount_falls_back_to_the_declared_defaults(): void
    {
        SettingsModel::where('name', 'weather')->delete();

        $component = Livewire::actingAs($this->admin)->test(Settings::class);

        // A weather alapértéke false a $others tömbben.
        $this->assertFalse($component->get('state')['others']['weather']);
    }

    public function test_render_exposes_the_dashboard_counters(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->assertOk()
            ->assertViewHas('stat_users', fn ($count) => $count > 0)
            ->assertViewHas('stat_groups')
            ->assertViewHas('waiting_jobs')
            ->assertViewHas('failed_jobs');
    }

    // --- nyelvkezelés ---

    public function test_adding_a_language_stores_it_as_visible(): void
    {
        $this->setLanguages([]);

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->set('state.languageAdd', ['country_code' => 'de', 'country_name' => 'Deutsch'])
            ->call('languageAdd')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('success')
            ->assertEmitted('refresh');

        $languages = $this->storedLanguages();

        $this->assertArrayHasKey('de', $languages);
        $this->assertSame('Deutsch', $languages['de']['name']);
        $this->assertTrue($languages['de']['visible']);
    }

    public function test_adding_a_language_validates_the_code_and_name(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->set('state.languageAdd', ['country_code' => 'x', 'country_name' => 'Deutsch'])
            ->call('languageAdd')
            ->assertHasErrors(['country_code']);

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->set('state.languageAdd', ['country_code' => 'de', 'country_name' => 'D'])
            ->call('languageAdd')
            ->assertHasErrors(['country_name']);
    }

    public function test_adding_a_language_rejects_a_non_alphabetic_code(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->set('state.languageAdd', ['country_code' => 'de-1', 'country_name' => 'Deutsch'])
            ->call('languageAdd')
            ->assertHasErrors(['country_code']);
    }

    public function test_removing_a_language_asks_for_confirmation_first(): void
    {
        $this->setLanguages(['de' => ['name' => 'Deutsch', 'visible' => true]]);

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->call('languageRemoveConfirmation', 'de')
            ->assertSet('lang_beeing_deleted', 'de')
            ->assertDispatchedBrowserEvent('show-languageRemove-confirmation');

        // Megerősítés előtt még megvan.
        $this->assertArrayHasKey('de', $this->storedLanguages());
    }

    public function test_confirmation_is_not_offered_for_an_unknown_language(): void
    {
        $this->setLanguages(['de' => ['name' => 'Deutsch', 'visible' => true]]);

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->call('languageRemoveConfirmation', 'fr')
            ->assertSet('lang_beeing_deleted', '');
    }

    public function test_confirming_removal_deletes_the_language(): void
    {
        $this->setLanguages([
            'de' => ['name' => 'Deutsch', 'visible' => true],
            'en' => ['name' => 'English', 'visible' => true],
        ]);

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->call('languageRemoveConfirmation', 'de')
            ->call('languageRemoveConfirmed')
            ->assertSet('lang_beeing_deleted', '')
            ->assertEmitted('refresh');

        $languages = $this->storedLanguages();

        $this->assertArrayNotHasKey('de', $languages);
        $this->assertArrayHasKey('en', $languages);
    }

    public function test_toggling_visibility_flips_the_flag_both_ways(): void
    {
        $this->setLanguages(['de' => ['name' => 'Deutsch', 'visible' => true]]);

        $component = Livewire::actingAs($this->admin)->test(Settings::class);

        $component->call('languageVisibility', 'de')->assertDispatchedBrowserEvent('success');
        $this->assertFalse($this->storedLanguages()['de']['visible']);

        $component->call('languageVisibility', 'de');
        $this->assertTrue($this->storedLanguages()['de']['visible']);
    }

    public function test_toggling_visibility_of_an_unknown_language_does_nothing(): void
    {
        $this->setLanguages(['de' => ['name' => 'Deutsch', 'visible' => true]]);

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->call('languageVisibility', 'fr');

        $this->assertSame(['de' => ['name' => 'Deutsch', 'visible' => true]], $this->storedLanguages());
    }

    // --- karbantartó parancsok ---

    public function test_run_maps_the_whitelist_key_to_the_right_artisan_command(): void
    {
        // Az Artisan facade-ot mockoljuk, mert a valódi hívásoknak globális
        // mellékhatásuk van: a view:clear kiüríti a lefordított Blade
        // nézeteket, ami a suite hátralévő részét drámaian lelassítja.
        Artisan::shouldReceive('call')->once()->with('view:clear')->andReturn(0);

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->call('run', 'view_clear')
            ->assertDispatchedBrowserEvent('success');
    }

    public function test_run_passes_the_force_flag_for_migrations(): void
    {
        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true])->andReturn(0);

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->call('run', 'migrate')
            ->assertDispatchedBrowserEvent('success');
    }

    public function test_run_ignores_a_command_outside_the_whitelist(): void
    {
        // Fontos biztonsági tulajdonság: csak a fix listán szereplő parancsok
        // futhatnak, tetszőleges Artisan hívás nem.
        Artisan::shouldReceive('call')->never();

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->call('run', 'db:wipe')
            ->assertNotDispatchedBrowserEvent('success');
    }

    // --- levélteszt ---

    public function test_test_mail_sends_a_notification_and_reports_success(): void
    {
        Notification::fake();

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->set('state.env.MAIL_MAILER', 'array')
            ->set('state.env.MAIL_HOST', '127.0.0.1')
            ->set('state.env.MAIL_PORT', '1025')
            ->set('state.env.MAIL_ENCRYPTION', '')
            ->set('state.env.MAIL_USERNAME', '')
            ->set('state.env.MAIL_PASSWORD', '')
            ->set('state.env.MAIL_FROM_ADDRESS', 'probauzenet@example.test')
            ->call('testMail')
            ->assertSet('mailtest', true);

        Notification::assertSentOnDemand(TestNotification::class);
    }

    public function test_test_mail_restores_the_mail_configuration_afterwards(): void
    {
        Notification::fake();
        $originalMailer = config('mail.default');

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->set('state.env.MAIL_MAILER', 'smtp')
            ->set('state.env.MAIL_HOST', 'mail.example.test')
            ->set('state.env.MAIL_PORT', '587')
            ->set('state.env.MAIL_ENCRYPTION', 'tls')
            ->set('state.env.MAIL_USERNAME', 'user')
            ->set('state.env.MAIL_PASSWORD', 'secret')
            ->set('state.env.MAIL_FROM_ADDRESS', 'probauzenet@example.test')
            ->call('testMail');

        // A metódus ideiglenesen átírja a mail configot, majd visszaállítja.
        $this->assertSame($originalMailer, config('mail.default'));
    }
}
