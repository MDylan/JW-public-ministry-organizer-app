<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Groups\NewsEdit;
use App\Models\Group;
use App\Models\GroupNews;
use App\Models\GroupNewsFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07: Groups\NewsEdit was smoke-only.
 *
 * This is the project's only WithFileUploads component, which makes it the
 * highest-risk single component for the Livewire 2 -> 3 migration (TODO 49):
 * temporary upload signing, the `mimes` rule and `temporaryUrl()` all change.
 * It also writes to the private `news_files` disk, whose Flysystem behaviour
 * changes in Laravel 9 (TODO 37).
 */
class GroupNewsEditTest extends FeatureTestCase
{
    private Group $group;
    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('news_files');

        $this->group = $this->createGroup();
        $this->editor = $this->createUser(['email' => 'news-editor@example.test']);
        $this->attachUserToGroup($this->editor, $this->group, 'admin');
        $this->actingAs($this->editor);
    }

    // --- mount / állapotbetöltés ---

    public function test_mount_for_a_new_item_leaves_the_state_empty(): void
    {
        Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id])
            ->assertSet('newId', false)
            ->assertSet('groupId', $this->group->id)
            ->assertOk();
    }

    public function test_mount_for_an_existing_item_loads_its_translations_into_state(): void
    {
        $news = GroupNews::factory()->forGroup($this->group)->byUser($this->editor)->create();
        $locale = config('app.locale', 'hu');

        $component = Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id, 'new' => $news->id]);

        $component->assertSet('newId', $news->id);
        $this->assertNotEmpty($component->get('state')['lang'][$locale]['title']);
    }

    public function test_mount_fails_for_a_news_item_belonging_to_another_group(): void
    {
        $otherGroup = $this->createGroup();
        $foreign = GroupNews::factory()->forGroup($otherGroup)->byUser($this->editor)->create();

        // A Livewire a mount() kivételét saját view-kivételbe csomagolja, ezért
        // az üzenetre assertálunk, ne a konkrét osztályra: az utóbbi
        // framework-verziónként változik (Ignition -> Laravel 9+).
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessageMatches('/No query results for model/');

        Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id, 'new' => $foreign->id]);
    }

    // --- létrehozás és szerkesztés ---

    public function test_creating_a_news_item_persists_it_with_translations(): void
    {
        $locale = config('app.locale', 'hu');

        Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id])
            ->set('state.date', now()->toDateString())
            ->set('state.status', 1)
            ->set('state.lang', [$locale => ['title' => 'Új hír', 'content' => 'Hír tartalma']])
            ->call('editNews')
            ->assertHasNoErrors();

        $news = GroupNews::where('group_id', $this->group->id)->first();

        $this->assertNotNull($news);
        $this->assertSame($this->editor->id, $news->user_id);
        $this->assertSame('Új hír', $news->translate($locale)->title);
    }

    public function test_editing_an_existing_item_updates_it_instead_of_creating_a_second(): void
    {
        $news = GroupNews::factory()->forGroup($this->group)->byUser($this->editor)->create();
        $locale = config('app.locale', 'hu');

        Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id, 'new' => $news->id])
            ->set('state.status', 0)
            ->set('state.lang', [$locale => ['title' => 'Módosított cím', 'content' => 'Módosított tartalom']])
            ->call('editNews')
            ->assertHasNoErrors();

        $this->assertSame(1, GroupNews::where('group_id', $this->group->id)->count());
        $this->assertSame('Módosított cím', $news->fresh()->translate($locale)->title);
        $this->assertSame(0, (int) $news->fresh()->status);
    }

    public function test_validation_rejects_a_missing_date_and_an_invalid_status(): void
    {
        Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id])
            ->set('state.status', 1)
            ->call('editNews')
            ->assertHasErrors(['date']);

        Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id])
            ->set('state.date', now()->toDateString())
            ->set('state.status', 7)
            ->call('editNews')
            ->assertHasErrors(['status']);
    }

    // --- fájlfeltöltés ---

    public function test_uploading_an_allowed_file_adds_it_to_the_attachment_list(): void
    {
        $file = UploadedFile::fake()->create('dokumentum.pdf', 100, 'application/pdf');

        $component = Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id])
            ->set('files', [$file])
            ->assertHasNoErrors();

        $attached = $component->get('attached_files');

        $this->assertCount(1, $attached);
        $this->assertSame('dokumentum.pdf', $attached[0]['name']);
        $this->assertTrue($attached[0]['new']);
    }

    public function test_uploading_a_disallowed_file_type_is_rejected(): void
    {
        // A file_types lista: jpg, jpeg, png, pdf, doc, docx, xls, xlsx.
        // Nem .php kiterjesztést használunk: azt a futtatókörnyezet nem
        // engedi ideiglenes fájlként létrehozni.
        $file = UploadedFile::fake()->create('archivum.zip', 10, 'application/zip');

        Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id])
            ->set('files', [$file])
            ->assertHasErrors(['files.*']);
    }

    public function test_uploading_an_oversized_file_is_rejected(): void
    {
        // A szabály max:2048 kilobájt.
        $file = UploadedFile::fake()->create('nagy.pdf', 3000, 'application/pdf');

        Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id])
            ->set('files', [$file])
            ->assertHasErrors(['files.*']);
    }

    public function test_saving_stores_the_uploaded_file_on_the_news_files_disk(): void
    {
        $locale = config('app.locale', 'hu');
        $file = UploadedFile::fake()->create('melleklet.pdf', 50, 'application/pdf');

        Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id])
            ->set('state.date', now()->toDateString())
            ->set('state.status', 1)
            ->set('state.lang', [$locale => ['title' => 'Hír melléklettel', 'content' => 'Tartalom']])
            ->set('files', [$file])
            ->call('editNews')
            ->assertHasNoErrors();

        $stored = GroupNewsFile::first();

        $this->assertNotNull($stored, 'The attachment row was not created.');
        $this->assertSame('melleklet.pdf', $stored->name);
        Storage::disk('news_files')->assertExists($stored->file);
    }

    // --- fájl eltávolítása ---

    public function test_removing_a_freshly_uploaded_file_drops_it_before_saving(): void
    {
        $file = UploadedFile::fake()->create('torlendo.pdf', 20, 'application/pdf');

        $component = Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id])
            ->set('files', [$file]);

        $component->call('confirmFileDelete', 0, 'torlendo.pdf')
            ->assertSet('file_beeingRemoved', 0)
            ->assertDispatchedBrowserEvent('show-fileDelete-confirmation');

        $component->call('deleteFileConfirmed')
            ->assertSet('file_beeingRemoved', null);

        $this->assertCount(0, $component->get('attached_files'));
        // Új feltöltés még nem került a lemezre, ezért nincs mit takarítani.
        $this->assertCount(0, $component->get('removed_files'));
    }

    public function test_removing_an_already_stored_file_queues_it_for_deletion_and_purges_it_on_save(): void
    {
        $news = GroupNews::factory()->forGroup($this->group)->byUser($this->editor)->create();
        Storage::disk('news_files')->put('regi.pdf', 'tartalom');
        $stored = GroupNewsFile::factory()->forNews($news)->create(['name' => 'regi.pdf', 'file' => 'regi.pdf']);

        $component = Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id, 'new' => $news->id]);

        $component->call('confirmFileDelete', 0, 'regi.pdf')->call('deleteFileConfirmed');

        // A már mentett fájl a törlési sorba kerül...
        $this->assertCount(1, $component->get('removed_files'));

        $component->call('editNews')->assertHasNoErrors();

        // ...és mentéskor eltűnik a DB-ből és a lemezről is.
        $this->assertNull(GroupNewsFile::find($stored->id));
        Storage::disk('news_files')->assertMissing('regi.pdf');
    }

    // --- törlés ---

    public function test_confirm_delete_dispatches_the_browser_event(): void
    {
        $news = GroupNews::factory()->forGroup($this->group)->byUser($this->editor)->create();

        Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id, 'new' => $news->id])
            ->call('confirmNewDelete')
            ->assertDispatchedBrowserEvent('show-newsDelete-confirmation');
    }

    public function test_delete_confirmed_soft_deletes_the_item_and_its_files(): void
    {
        $news = GroupNews::factory()->forGroup($this->group)->byUser($this->editor)->create();
        Storage::disk('news_files')->put('csatolt.pdf', 'tartalom');
        $stored = GroupNewsFile::factory()->forNews($news)->create(['name' => 'csatolt.pdf', 'file' => 'csatolt.pdf']);

        Livewire::actingAs($this->editor)
            ->test(NewsEdit::class, ['group' => $this->group->id, 'new' => $news->id])
            ->call('deleteConfirmed');

        $this->assertNull(GroupNews::find($news->id));
        $this->assertNull(GroupNewsFile::find($stored->id));
        Storage::disk('news_files')->assertMissing('csatolt.pdf');
    }
}
