<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Settings extends Model
{
    use HasFactory,LogsActivity;

    protected $fillable = [
        'name',
        'value',
        'comment'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logOnly(['name', 'value'])->useLogName('settings')->logOnlyDirty()->dontSubmitEmptyLogs();
        // Chain fluent methods for configuration options
    }
}
