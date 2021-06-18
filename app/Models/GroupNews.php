<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class GroupNews extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    public $fillable = [
        'group_id',
        'user_id',
        'status',
        'date',
        'title',
        'content'
    ];

    protected static $logFillable = true;
    protected static $logName = 'news';
    protected static $logOnlyDirty = true;

    protected $casts = [
        'date' => 'datetime:Y-m-d',
    ];

    public function group() {
        return $this->belongsTo(Group::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function files() {
        return $this->hasMany(GroupNewsFile::class, 'group_new_id');
    }
}
