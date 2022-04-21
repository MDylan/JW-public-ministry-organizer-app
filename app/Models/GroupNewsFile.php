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

    public function getSizeAttribute() {
        if (Storage::disk('news_files')->exists($this->file)) {
            return Storage::disk('news_files')->size($this->file);
        } else {
            return 0;
        }
    }
}
