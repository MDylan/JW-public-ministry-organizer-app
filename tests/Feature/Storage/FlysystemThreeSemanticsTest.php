<?php

namespace Tests\Feature\Storage;

use Illuminate\Filesystem\Filesystem as IlluminateFilesystem;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;
use Tests\TestCase;

/**
 * TODO 37: the Flysystem 1 -> 3 semantic changes, measured on real disks built
 * from this application's own configuration.
 *
 * The upgrade itself happened in TODO 34 and the suite stayed green, so this
 * file is not a migration - it is the measurement nobody took at the time. It
 * exists because three of these behaviours changed silently:
 *
 *   delete() on a missing file    Laravel 8: false   Laravel 9: true
 *   exists('') on the disk root   Laravel 8: false   Laravel 9: true
 *   size() on anything missing    both throw, but 'throw' => false does NOT
 *                                 cover it - see below
 *
 * WHY Storage::build() AND NOT Storage::fake().
 *
 * Storage::fake() merges its OWN $config parameter with a temporary root
 * (Facades/Storage.php:105) - it never reads filesystems.disks.*. So the three
 * existing Storage::fake('news_files') tests in this suite have never
 * exercised this application's 'throw' => false, 'visibility' or 'permissions'
 * keys; they ran against a bare local driver that happens to behave the same
 * way for the operations they use. Storage::build() takes the real disk entry
 * and overrides only the root, which is what makes the assertions below
 * statements about THIS application rather than about Flysystem.
 *
 * THE ONE THAT MATTERS: 'throw' => false does not cover size().
 *
 * FilesystemAdapter wraps get(), put(), delete(), mimeType() and others in
 * try/catch with throw_if($this->throwsExceptions(), $e). size() (:553-556)
 * and lastModified() (:599-602) are not wrapped - they call the driver
 * straight through. So a disk configured never to throw still throws from
 * those two, and every exists()-then-size() guard in the application is
 * load-bearing rather than defensive.
 */
class FlysystemThreeSemanticsTest extends TestCase
{
    /**
     * Every disk this application configures.
     *
     * The list is spelled out rather than read from the config, so that adding
     * a disk without deciding what these semantics mean for it fails
     * FilesystemDiskContractTest instead of silently widening this file.
     */
    private const LOCAL_DISKS = ['local', 'public', 'web', 'news_files'];

    protected function tearDown(): void
    {
        (new IlluminateFilesystem)->deleteDirectory(storage_path('framework/testing/flysystem'));

        parent::tearDown();
    }

    /**
     * A disk built from the real configuration entry, rooted somewhere
     * disposable.
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    private function temporaryDisk(string $name)
    {
        $root = storage_path('framework/testing/flysystem/'.$name);

        (new IlluminateFilesystem)->cleanDirectory($root);

        return Storage::build(array_merge(
            config("filesystems.disks.{$name}"),
            ['root' => $root]
        ));
    }

    /**
     * The change the roadmap named, on every disk this application can build.
     *
     * Laravel 8 called assertPresent() first and turned the resulting
     * FileNotFoundException into a false return; Flysystem 3's local adapter
     * returns early when the path does not exist (LocalFilesystemAdapter:141),
     * so nothing is raised and the call reports success.
     */
    public function test_delete_on_a_missing_file_now_reports_success_on_every_disk()
    {
        foreach (self::LOCAL_DISKS as $name) {
            $disk = $this->temporaryDisk($name);

            $this->assertTrue(
                $disk->delete('no-such-file.txt'),
                "Disk '{$name}' reported failure deleting a file that was not there."
            );
        }
    }

    /**
     * Control for the previous case: the return value is not a constant true.
     *
     * Without this, "delete() reports success on a missing file" would be
     * satisfied by a delete() that always reports success, and the assertion
     * would measure nothing.
     */
    public function test_delete_reports_success_when_it_actually_removes_the_file()
    {
        $disk = $this->temporaryDisk('news_files');
        $disk->put('present.txt', 'content');

        $this->assertTrue($disk->delete('present.txt'));
        $this->assertFalse($disk->exists('present.txt'));
    }

    /**
     * exists() answers for files, for directories, and for the disk root.
     *
     * Flysystem 3's has() is fileExists() || directoryExists()
     * (Filesystem.php:46-51). Flysystem 1 short-circuited an empty path to
     * false (strlen($path) === 0 ? false), so the third assertion below is the
     * one the hop reversed: an empty string now names the disk root, which is
     * a directory, which exists.
     *
     * That is what makes an exists()-then-size() guard unsafe: both a
     * directory and an empty path pass the guard and then throw.
     */
    public function test_exists_conflates_files_directories_and_the_disk_root()
    {
        $disk = $this->temporaryDisk('news_files');
        $disk->put('sub/file.txt', 'content');

        $this->assertTrue($disk->exists('sub/file.txt'));
        $this->assertTrue($disk->exists('sub'), 'A directory no longer satisfies exists().');
        $this->assertTrue($disk->exists(''), 'The empty path no longer names the disk root.');
        $this->assertFalse($disk->exists('nope.txt'));
    }

    /**
     * The two calls that separate what exists() conflates.
     *
     * Neither existed on the Laravel 8 FilesystemAdapter, which offered only
     * exists() and missing(). The tool needed to write a correct guard arrived
     * with the same hop that made the guard necessary.
     */
    public function test_file_exists_and_directory_exists_separate_what_exists_conflates()
    {
        $disk = $this->temporaryDisk('news_files');
        $disk->put('sub/file.txt', 'content');

        $this->assertTrue($disk->fileExists('sub/file.txt'));
        $this->assertFalse($disk->fileExists('sub'));
        $this->assertFalse($disk->fileExists(''));

        $this->assertTrue($disk->directoryExists('sub'));
        $this->assertTrue($disk->directoryExists(''));
        $this->assertFalse($disk->directoryExists('sub/file.txt'));
    }

    /**
     * The measurement this whole file exists for.
     *
     * The disk is built from an entry carrying 'throw' => false, and size()
     * throws anyway, because FilesystemAdapter::size() does not go through the
     * try/catch that honours the setting.
     */
    public function test_size_throws_on_a_missing_path_even_though_throw_is_false()
    {
        $disk = $this->temporaryDisk('news_files');

        $this->assertFalse(config('filesystems.disks.news_files.throw'));

        $this->expectException(UnableToRetrieveMetadata::class);

        $disk->size('no-such-file.txt');
    }

    /**
     * The two paths that pass an exists() guard and then throw from size().
     *
     * Separated from the missing-file case above because these are the ones a
     * guard cannot catch: exists() answers true for both.
     */
    public function test_size_throws_on_a_directory_and_on_the_empty_path()
    {
        $disk = $this->temporaryDisk('news_files');
        $disk->put('sub/file.txt', 'content');

        $threw = 0;

        foreach (['sub', ''] as $path) {
            $this->assertTrue($disk->exists($path), "exists('{$path}') did not pass the guard.");

            try {
                $disk->size($path);
            } catch (UnableToRetrieveMetadata $e) {
                $threw++;
            }
        }

        $this->assertSame(2, $threw, 'A path that passes exists() no longer throws from size().');
    }

    /**
     * size() on a file that is there returns the byte count.
     *
     * Control for the two cases above: they would also pass against a size()
     * that never worked at all.
     */
    public function test_size_returns_the_byte_count_of_a_present_file()
    {
        $disk = $this->temporaryDisk('news_files');
        $disk->put('present.txt', '0123456789');

        $this->assertSame(10, $disk->size('present.txt'));
    }

    /**
     * Control for the whole 'throw' => false story: the setting IS wired up.
     *
     * get() goes through the try/catch and returns null instead of raising, on
     * the same disk and for the same missing file that makes size() throw.
     * Without this, the size() assertions could be read as "the throw key does
     * nothing", which is the opposite of the finding.
     */
    public function test_get_on_a_missing_file_returns_null_because_throw_is_false()
    {
        $disk = $this->temporaryDisk('news_files');

        $this->assertNull($disk->get('no-such-file.txt'));
    }

    /**
     * The other metadata reader the throw key does not cover.
     *
     * No application code calls lastModified(), so this is a boundary marker
     * rather than a guard: it records that size() is not a special case but
     * one of a pair, so a future call site knows which side of the line it is
     * landing on.
     */
    public function test_last_modified_is_the_other_reader_the_throw_key_does_not_cover()
    {
        $disk = $this->temporaryDisk('news_files');

        $this->expectException(UnableToRetrieveMetadata::class);

        $disk->lastModified('no-such-file.txt');
    }
}
