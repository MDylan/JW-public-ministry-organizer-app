<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Event extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;
    protected static $logName = 'event';
    protected static $logOnlyDirty = true;

    protected $fillable = [
        'day',
        'start',
        'end',
        'user_id',
        'accepted_by',
        'accepted_at',
    ];

    protected $casts = [
        'day' => 'datetime:Y-m-d',
        'start' => 'datetime:Y-m-d H:i',
        'end' => 'datetime:Y-m-d H:i',
    ];

    public function groups() {
        return $this->belongsTo(Group::class);
    }
}
