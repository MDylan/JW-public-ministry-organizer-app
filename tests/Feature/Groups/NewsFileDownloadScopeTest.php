<?php

namespace Tests\Feature\Groups;

use App\Models\GroupNewsFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FeatureTestCase;

/**
 * v1-patch H2: a hírmelléklet-letöltés csoporthoz kötése.
 *
 * A `groupMember` middleware KIZÁRÓLAG az útvonal `{group}` paraméterét nézi -
 * azt igazolja, hogy a kérő tagja ANNAK a csoportnak. A `{file}` puszta
 * azonosító szerint kötődik modellhez, és a controller korábban nem nézte meg,
 * hogy a fájl ahhoz a csoporthoz tartozik-e.
 *
 * Ebből következett, hogy bármely csoport bármely elfogadott tagja letölthette
 * BÁRMELY másik csoport privát mellékletét: elég volt a SAJÁT csoportjának
 * azonosítóját megadni - amivel a middleware elégedett - és mellé egy idegen
 * fájlazonosítót. A fájlok a `news_files` privát diszken, a docrooton kívül
 * ülnek, tehát ez a controller volt az egyetlen út hozzájuk.
 */
class NewsFileDownloadScopeTest extends FeatureTestCase
{
    public function test_a_member_cannot_download_another_groups_file_through_their_own_group_id(): void
    {
        Storage::fake('news_files');

        $ownGroup = $this->createGroup();
        $otherGroup = $this->createGroup();

        $intruder = $this->createUser(['email' => 'file-scope-intruder@example.test']);
        $otherAdmin = $this->createUser(['email' => 'file-scope-owner@example.test']);

        $this->attachUserToGroup($intruder, $ownGroup, 'member', true);
        $this->attachUserToGroup($otherAdmin, $otherGroup, 'roler', true);

        $this->actingAs($otherAdmin);
        $otherNews = $this->createGroupNews($otherGroup, $otherAdmin);
        $otherFile = GroupNewsFile::create([
            'group_new_id' => $otherNews->id,
            'name' => 'titkos.pdf',
            'file' => 'titkos.pdf',
        ]);
        Storage::disk('news_files')->put('titkos.pdf', 'masik csoport tartalma');

        // A saját csoportazonosító átviszi a middleware-en, az idegen fájl
        // azonosítója viszont nem oldódhat fel.
        $this->actingAs($intruder)
            ->get(route('groups.news.filedownload', [
                'group' => $ownGroup->id,
                'file'  => $otherFile->id,
            ]))
            ->assertNotFound();
    }

    public function test_the_answer_is_404_not_403_so_it_does_not_confirm_the_file_exists(): void
    {
        Storage::fake('news_files');

        $group = $this->createGroup();
        $member = $this->createUser(['email' => 'file-scope-probe@example.test']);
        $this->attachUserToGroup($member, $group, 'member', true);

        // Nem létező azonosító és idegen azonosító ugyanazt a választ adja,
        // tehát a státuszkódból nem derül ki, melyik fájl létezik.
        $this->actingAs($member)
            ->get(route('groups.news.filedownload', ['group' => $group->id, 'file' => 999999]))
            ->assertNotFound();
    }

    public function test_a_member_can_still_download_their_own_groups_file(): void
    {
        Storage::fake('news_files');

        $group = $this->createGroup();
        $admin = $this->createUser(['email' => 'file-scope-admin@example.test']);
        $member = $this->createUser(['email' => 'file-scope-member@example.test']);

        $this->attachUserToGroup($admin, $group, 'roler', true);
        $this->attachUserToGroup($member, $group, 'member', true);

        $this->actingAs($admin);
        $news = $this->createGroupNews($group, $admin);
        $file = GroupNewsFile::create([
            'group_new_id' => $news->id,
            'name' => 'sajat.pdf',
            'file' => 'sajat.pdf',
        ]);
        Storage::disk('news_files')->put('sajat.pdf', 'sajat tartalom');

        $this->actingAs($member)
            ->get(route('groups.news.filedownload', ['group' => $group->id, 'file' => $file->id]))
            ->assertOk();
    }

    public function test_an_outsider_is_still_stopped_by_the_group_member_middleware(): void
    {
        Storage::fake('news_files');

        $group = $this->createGroup();
        $admin = $this->createUser(['email' => 'file-scope-outsider-admin@example.test']);
        $outsider = $this->createUser(['email' => 'file-scope-outsider@example.test']);

        $this->attachUserToGroup($admin, $group, 'roler', true);

        $this->actingAs($admin);
        $news = $this->createGroupNews($group, $admin);
        $file = GroupNewsFile::create([
            'group_new_id' => $news->id,
            'name' => 'kivul.pdf',
            'file' => 'kivul.pdf',
        ]);
        Storage::disk('news_files')->put('kivul.pdf', 'tartalom');

        $this->actingAs($outsider)
            ->get(route('groups.news.filedownload', ['group' => $group->id, 'file' => $file->id]))
            ->assertForbidden();
    }
}
