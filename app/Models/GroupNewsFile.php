<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GroupNewsFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_new_id',
        'name',
        'file'
    ];

    protected $appends = ['url', 'size'];

    public function new() {
        return $this->belongsTo(GroupNews::class, 'group_new_id');
    }

    public function getUrlAttribute() {
        $new = $this->new()->first();
        if($new->group_id !== null)
            return '/news_file/'.$new->group_id.'/'.$this->id;
        else {
            return '';
        }
    }

    /**
     * TODO 37: fileExists(), not exists(), and the difference is load-bearing.
     *
     * size() is one of the two readers that a disk's 'throw' => false does not
     * cover: FilesystemAdapter::size() calls the driver straight through with
     * no try/catch, unlike get(), put() or delete(). So whatever passes this
     * guard had better be a file.
     *
     * exists() is not that check on Flysystem 3 - it is fileExists() ||
     * directoryExists(), so a directory and the empty path both satisfy it,
     * the empty path because it names the disk root. An empty file column is
     * reachable: NewsEdit:109 writes TemporaryUploadedFile::store()'s return
     * value into this non-nullable string column without checking, and a
     * failed store returns false.
     *
     * On Flysystem 1 has() short-circuited an empty path to false, so such a
     * row reported 0 bytes. Since the Laravel 9 hop it threw instead - and
     * this accessor is in $appends, so that was a 500 on every page listing
     * the news item, not a broken download link.
     */
    public function getSizeAttribute() {
        if (Storage::disk('news_files')->fileExists($this->file)) {
            return Storage::disk('news_files')->size($this->file);
        } else {
            return 0;
        }
    }
}
