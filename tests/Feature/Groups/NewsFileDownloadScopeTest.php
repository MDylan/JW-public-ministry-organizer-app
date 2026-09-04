<?php

namespace Tests\Feature\Groups;

use App\Models\GroupNewsFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FeatureTestCase;

/**
 * v1-patch H2: scoping the news-attachment download to its group.
 *
 * The `groupMember` middleware ONLY looks at the route's `{group}`
 * parameter - it confirms that the requester is a member of THAT group. The
 * `{file}` binds to a model by a bare identifier, and the controller
 * previously never checked whether the file belongs to that group.
 *
 * The consequence was that any accepted member of any group could download
 * ANY other group's private attachment: it was enough to supply THEIR OWN
 * group's identifier - which satisfied the middleware - together with a
 * foreign file identifier. The files sit on the `news_files` private disk,
 * outside the docroot, so this controller was the only path to them.
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

        // The own group identifier gets through the middleware, but the
        // foreign file's identifier must not resolve.
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

        // A non-existent identifier and a foreign identifier give the same
        // answer, so the status code does not reveal which file exists.
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

    /**
     * TODO 37: the same Flysystem 3 gap GroupNewsFileSizeTest reaches through
     * the model, reached here through the route.
     *
     * The controller guards with exists()-then-download() (:36-37), and
     * download() builds its Content-Length from size() (FilesystemAdapter:283
     * via response()). An empty file column passes the guard - the empty path
     * names the disk root, which is a directory - and then size() throws
     * straight through 'throw' => false.
     *
     * So the member gets a 500 where the else branch was written to answer
     * 404. On Laravel 8 this route answered 404, because Flysystem 1's has()
     * returned false for an empty path.
     *
     * Stated as the broken behaviour first, so the fix reverses it visibly.
     */
    public function test_an_empty_file_column_answers_500_today_where_the_guard_intends_404(): void
    {
        Storage::fake('news_files');

        $group = $this->createGroup();
        $admin = $this->createUser(['email' => 'file-scope-empty-admin@example.test']);
        $member = $this->createUser(['email' => 'file-scope-empty-member@example.test']);

        $this->attachUserToGroup($admin, $group, 'roler', true);
        $this->attachUserToGroup($member, $group, 'member', true);

        $this->actingAs($admin);
        $news = $this->createGroupNews($group, $admin);
        $file = GroupNewsFile::create([
            'group_new_id' => $news->id,
            'name' => 'ures.pdf',
            // What NewsEdit:109 writes when TemporaryUploadedFile::store()
            // fails: its false return reaching a non-nullable string column.
            'file' => '',
        ]);

        $this->actingAs($member)
            ->get(route('groups.news.filedownload', ['group' => $group->id, 'file' => $file->id]))
            ->assertStatus(500);
    }
}
