<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
// use Spatie\Activitylog\Traits\LogsActivity;
// use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\SoftDeletes;

final class GroupUser extends Pivot
{
    use HasFactory, SoftDeletes;

    public $incrementing = true;

    protected $table 	= 'group_user';
    protected $fillable = ['user_id', 'group_id', 'group_role', 'accepted_at', 'note', 'hidden','deleted_at', 'signs'];
    protected $dates = ['created_at','updated_at','deleted_at'];

    protected $casts = [
        'signs' => 'array',
    ];

    // Activity Log
    // protected static $logFillable = true;
    // protected static $submitEmptyLogs = false;
    // protected static $logOnlyDirty = true;
    // protected static $logName = 'groupUser';

    // public function getActivitylogOptions(): LogOptions
    // {
    //     return LogOptions::defaults()->logFillable()->useLogName('groupUser')->logOnlyDirty()->dontSubmitEmptyLogs();
    //     // ->logOnly(['name', 'value']);
    //     // Chain fluent methods for configuration options
    // }

    public function histories()
    {
        return $this->morphMany(LogHistory::class, 'model');
    }
}
