<?php

namespace Tests\Feature\Models;

use App\Models\GroupNewsFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 37: GroupNewsFile::getSizeAttribute(), which had no coverage at all
 * before this file - not the happy path, not the guard, not the defect.
 *
 * The accessor is in $appends (GroupNewsFile:19), so it runs on every
 * serialization of the model: every news listing, every API-shaped response,
 * every toArray(). Whatever it does, it does on a page a group member loads.
 *
 * Its guard used to be exists()-then-size(), and FlysystemThreeSemanticsTest
 * measures why that pairing is not safe on Flysystem 3: exists() answers true
 * for a directory and for the empty path, and size() is one of the two readers
 * that 'throw' => false does not cover. The last case below is that gap
 * reached through real application code - it recorded the 500 first and now
 * records the fix.
 */
class GroupNewsFileSizeTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('news_files');
    }

    /**
     * The happy path, which nothing asserted before.
     */
    public function test_the_size_attribute_is_the_byte_count_of_the_stored_file()
    {
        $file = GroupNewsFile::factory()->create(['file' => 'stored.pdf']);

        Storage::disk('news_files')->put('stored.pdf', '0123456789');

        $this->assertSame(10, $file->size);
    }

    /**
     * The guard doing its job: a row whose file is gone reports zero rather
     * than raising.
     *
     * This is the case the accessor was written for, and it still holds on
     * Flysystem 3 - fileExists() and exists() agree about a name that is
     * simply not there. Only the directory and empty-path cases diverge.
     */
    public function test_the_size_attribute_falls_back_to_zero_when_the_file_is_gone()
    {
        $file = GroupNewsFile::factory()->create(['file' => 'never-stored.pdf']);

        $this->assertSame(0, $file->size);
    }

    /**
     * Why the two cases above are not academic: the accessor runs on every
     * serialization, because it is appended.
     *
     * A throw from size() is therefore not a broken download link, it is a
     * 500 on whatever page lists the news item.
     */
    public function test_the_size_attribute_runs_on_every_serialization()
    {
        $file = GroupNewsFile::factory()->create(['file' => 'stored.pdf']);

        Storage::disk('news_files')->put('stored.pdf', 'abc');

        $this->assertArrayHasKey('size', $file->toArray());
        $this->assertSame(3, $file->toArray()['size']);
    }

    /**
     * THE DEFECT, now fixed - this assertion is the reversal of the one this
     * file opened with.
     *
     * The file column is a non-nullable string (2021_06_17_230555:20), and
     * NewsEdit:109 writes the return value of TemporaryUploadedFile::store()
     * into it without checking. A failed store returns false, which reaches a
     * string column as ''. So an empty file name is a value this application
     * can produce, not a contrived one.
     *
     * On Laravel 8 such a row reported 0 bytes: Flysystem 1's has()
     * short-circuited an empty path to false, so the guard's else branch ran.
     * On Laravel 9 the empty path names the disk root, which is a directory,
     * which exists - so the guard passed and size() threw straight through
     * 'throw' => false, turning a harmless zero into a 500 on the news
     * listing.
     *
     * The guard now uses fileExists(), which answers false for the disk root,
     * so the else branch runs again and the Laravel 8 answer is restored.
     *
     * The first assertion is kept as the control: it states WHY the old guard
     * let this through, so a future edit back to exists() fails here with the
     * reason attached rather than somewhere downstream.
     */
    public function test_an_empty_file_column_reports_zero_bytes()
    {
        $file = GroupNewsFile::factory()->create(['file' => '']);

        $this->assertTrue(
            Storage::disk('news_files')->exists(''),
            'The empty path no longer passes exists(); the guard below is no longer load-bearing.'
        );

        $this->assertFalse(
            Storage::disk('news_files')->fileExists(''),
            'fileExists() now accepts the disk root, which is what the guard relies on.'
        );

        $this->assertSame(0, $file->toArray()['size']);
    }
}
