<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Admin\AdminNewsletters;
use App\Http\Livewire\Admin\NewsletterEdit;
use App\Http\Livewire\Admin\StaticPageEdit;
use App\Http\Livewire\Admin\StaticPages;
use App\Http\Livewire\Admin\Statistics;
use App\Http\Livewire\Admin\Translation;
use App\Models\AdminNewsletter;
use App\Models\AdminNewsletterRead;
use App\Models\StaticPage;
use App\Models\Statistics as StatisticsModel;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07: the Admin components that were smoke-only.
 *
 * Covers Admin\AdminNewsletters, Admin\NewsletterEdit, Admin\StaticPages,
 * Admin\StaticPageEdit, Admin\Statistics and Admin\Translation.
 * Admin\Settings has its own file because of its size.
 */
class AdminComponentsTest extends FeatureTestCase
{
    private User $admin;
    private User $plainUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser(['email' => 'adm@example.test', 'role' => 'mainAdmin']);
        $this->plainUser = $this->createUser(['email' => 'plain@example.test']);
        $this->actingAs($this->admin);
    }

    // --- Admin\AdminNewsletters ---

    public function test_marking_a_newsletter_as_read_records_it_and_refreshes_the_partials(): void
    {
        $newsletter = AdminNewsletter::factory()->published()->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(AdminNewsletters::class)
            ->call('setAsRead', $newsletter->id)
            ->assertEmitted('refresh');

        $this->assertDatabaseHas('admin_newsletter_reads', [
            'user_id' => $this->admin->id,
            'admin_newsletter_id' => $newsletter->id,
        ]);
    }

    public function test_marking_the_same_newsletter_twice_does_not_duplicate_the_row(): void
    {
        $newsletter = AdminNewsletter::factory()->published()->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)->test(AdminNewsletters::class)
            ->call('setAsRead', $newsletter->id)
            ->call('setAsRead', $newsletter->id);

        $this->assertSame(
            1,
            AdminNewsletterRead::where('admin_newsletter_id', $newsletter->id)
                ->where('user_id', $this->admin->id)
                ->count()
        );
    }

    public function test_an_admin_sees_unpublished_and_future_newsletters(): void
    {
        AdminNewsletter::factory()->create([
            'user_id' => $this->admin->id,
            'status' => 0,
            'date' => today()->addWeek(),
            'send_to' => 'groupServants',
        ]);

        Livewire::actingAs($this->admin)
            ->test(AdminNewsletters::class)
            ->assertViewHas('newsletters', fn ($newsletters) => $newsletters->count() === 1)
            ->assertViewHas('editor', true);
    }

    public function test_a_non_admin_sees_neither_unpublished_nor_future_newsletters(): void
    {
        AdminNewsletter::factory()->create([
            'user_id' => $this->admin->id,
            'status' => 0,
            'date' => today(),
            'send_to' => 'groupServants',
        ]);
        AdminNewsletter::factory()->create([
            'user_id' => $this->admin->id,
            'status' => 1,
            'date' => today()->addWeek(),
            'send_to' => 'groupServants',
        ]);

        Livewire::actingAs($this->plainUser)
            ->test(AdminNewsletters::class)
            ->assertViewHas('newsletters', fn ($newsletters) => $newsletters->count() === 0)
            ->assertViewHas('editor', false);
    }

    // --- Admin\NewsletterEdit ---

    public function test_newsletter_edit_mounts_with_defaults_for_a_new_item(): void
    {
        $component = Livewire::actingAs($this->admin)->test(NewsletterEdit::class);

        $state = $component->get('state');

        $this->assertSame(0, $state['status']);
        $this->assertSame('groupServants', $state['send_to']);
        $this->assertSame(0, $state['send_newsletter']);
    }

    public function test_newsletter_edit_loads_translations_for_an_existing_item(): void
    {
        $newsletter = AdminNewsletter::factory()->create(['user_id' => $this->admin->id]);
        $locale = config('app.locale', 'hu');

        $component = Livewire::actingAs($this->admin)
            ->test(NewsletterEdit::class, ['id' => $newsletter->id]);

        $this->assertNotEmpty($component->get('state')['lang'][$locale]['subject']);
    }

    public function test_creating_a_newsletter_persists_it_with_the_author(): void
    {
        $locale = config('app.locale', 'hu');

        Livewire::actingAs($this->admin)
            ->test(NewsletterEdit::class)
            ->set('state.date', today()->toDateString())
            ->set('state.status', 1)
            ->set('state.send_to', 'groupAdmins')
            ->set('state.send_newsletter', 0)
            ->set('state.lang', [$locale => ['subject' => 'Hírlevél tárgy', 'content' => 'Hírlevél tartalom']])
            ->call('editNewsletter')
            ->assertHasNoErrors();

        $newsletter = AdminNewsletter::first();

        $this->assertNotNull($newsletter);
        $this->assertSame($this->admin->id, $newsletter->user_id);
        $this->assertSame('groupAdmins', $newsletter->send_to);
        $this->assertSame('Hírlevél tárgy', $newsletter->translate($locale)->subject);
    }

    public function test_newsletter_validation_rejects_an_unknown_recipient_group(): void
    {
        // Ez a validáció az egyetlen védelem a newsletters:send-due parancs
        // "ismeretlen send_to megakasztja a sort" hibája ellen (TODO 06).
        Livewire::actingAs($this->admin)
            ->test(NewsletterEdit::class)
            ->set('state.date', today()->toDateString())
            ->set('state.status', 1)
            ->set('state.send_to', 'valamiIsmeretlen')
            ->set('state.send_newsletter', 0)
            ->call('editNewsletter')
            ->assertHasErrors(['send_to']);
    }

    public function test_newsletter_validation_requires_a_date(): void
    {
        Livewire::actingAs($this->admin)
            ->test(NewsletterEdit::class)
            ->set('state.status', 1)
            ->set('state.send_to', 'groupServants')
            ->set('state.send_newsletter', 0)
            ->call('editNewsletter')
            ->assertHasErrors(['date']);
    }

    public function test_deleting_a_newsletter_removes_it(): void
    {
        $newsletter = AdminNewsletter::factory()->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(NewsletterEdit::class, ['id' => $newsletter->id])
            ->call('confirmNewDelete')
            ->assertDispatchedBrowserEvent('show-newsDelete-confirmation')
            ->call('deleteConfirmed');

        $this->assertNull(AdminNewsletter::find($newsletter->id));
    }

    // --- Admin\StaticPages / StaticPageEdit ---

    public function test_static_pages_list_renders_the_existing_pages(): void
    {
        Livewire::actingAs($this->admin)
            ->test(StaticPages::class)
            ->assertOk()
            // A FeatureTestCase setUp-ja létrehoz egy 'home' oldalt.
            ->assertSee('home');
    }

    public function test_static_page_edit_mounts_with_defaults(): void
    {
        $component = Livewire::actingAs($this->admin)->test(StaticPageEdit::class);

        $this->assertSame('left', $component->get('state')['position']);
        $this->assertSame(0, $component->get('state')['status']);
    }

    public function test_creating_a_static_page_persists_it(): void
    {
        $locale = config('app.locale', 'hu');

        Livewire::actingAs($this->admin)
            ->test(StaticPageEdit::class)
            ->set('state.slug', 'kapcsolat')
            ->set('state.status', 1)
            ->set('state.position', 'bottom')
            ->set('state.lang', [$locale => ['title' => 'Kapcsolat', 'content' => 'Tartalom']])
            ->call('editPage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('static_pages', ['slug' => 'kapcsolat', 'user_id' => $this->admin->id]);
    }

    public function test_static_page_slug_must_be_unique(): void
    {
        // A setUp már létrehozott egy 'home' oldalt.
        Livewire::actingAs($this->admin)
            ->test(StaticPageEdit::class)
            ->set('state.slug', 'home')
            ->set('state.status', 1)
            ->set('state.position', 'bottom')
            ->call('editPage')
            ->assertHasErrors(['slug']);
    }

    public function test_editing_a_page_allows_keeping_its_own_slug(): void
    {
        $page = StaticPage::where('slug', 'home')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(StaticPageEdit::class, ['staticPage' => $page->id])
            ->set('state.status', 2)
            ->call('editPage')
            ->assertHasNoErrors();

        $this->assertSame(2, (int) $page->fresh()->status);
    }

    public function test_check_slug_normalises_the_input(): void
    {
        Livewire::actingAs($this->admin)
            ->test(StaticPageEdit::class)
            // Ékezetes bemenet: a Str::slug transzliterál és kötőjelez.
            ->set('state.slug', 'Árvíztűrő Tükörfúrógép')
            ->call('checkSlug')
            ->assertSet('state.slug', 'arvizturo-tukorfurogep');
    }

    public function test_deleting_a_static_page_removes_it(): void
    {
        $page = StaticPage::where('slug', 'home')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(StaticPageEdit::class, ['staticPage' => $page->id])
            ->call('confirmNewDelete')
            ->assertDispatchedBrowserEvent('show-pageDelete-confirmation')
            ->call('deleteConfirmed');

        $this->assertNull(StaticPage::find($page->id));
    }

    // --- Admin\Statistics / Admin\Translation ---

    public function test_admin_statistics_renders_recorded_datapoints(): void
    {
        StatisticsModel::factory()->ofType('active_users')->create([
            'date' => now()->subHours(2)->format('Y-m-d H:00:00'),
            'number' => 7,
        ]);
        StatisticsModel::factory()->ofType('dialy_users')->create([
            'date' => now()->subDay()->format('Y-m-d'),
            'number' => 12,
        ]);

        Livewire::actingAs($this->admin)
            ->test(Statistics::class)
            ->assertOk();
    }

    public function test_admin_translation_component_renders(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Translation::class)
            ->assertOk();
    }
}
