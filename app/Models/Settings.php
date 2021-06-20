<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Settings extends Model
{
    use HasFactory;
    use LogsActivity;

    protected static $logFillable = true;
    protected static $logName = 'settings';
    protected static $logOnlyDirty = true;

    protected $fillable = [
        'name',
        'value',
        'comment'
    ];
}
