<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// use Spatie\Activitylog\Traits\LogsActivity;
// use Spatie\Activitylog\LogOptions;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class GroupNews extends Model
{
    use HasFactory, SoftDeletes, Translatable;

    public $translatedAttributes = ['title', 'content'];

    public $fillable = [
        'group_id',
        'user_id',
        'status',
        'date',
        // 'title',
        // 'content'
    ];

    // protected static $logFillable = true;
    // protected static $logName = 'news';
    // protected static $logOnlyDirty = true;

    protected $casts = [
        'date' => 'datetime:Y-m-d',
    ];

    // protected static $recordEvents = ['updated', 'deleted'];

    // public function getActivitylogOptions(): LogOptions
    // {
    //     return LogOptions::defaults()->logFillable()->useLogName('group_news')->logOnlyDirty()->dontSubmitEmptyLogs();
    //     // ->logOnly(['name', 'value']);
    //     // Chain fluent methods for configuration options
    // }

    public function histories()
    {
        return $this->morphMany(LogHistory::class, 'model');
    }

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
