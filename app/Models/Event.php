<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use DateTime;

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

    protected $appends = ['full_time', 'day_name'];

    public function groups() {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function accept_user() {
        return $this->belongsTo(User::class, 'accepted_by');
    }



    //módosítja az adatbázisból visszanyert értéket unixtime-ra
    public function getStartAttribute($value) {
        $d = new DateTime( $value );
        return $d->getTimestamp();
    }

    //módosítja az adatbázisból visszanyert értéket unixtime-ra
    public function getEndAttribute($value) {
        $d = new DateTime( $value );
        return $d->getTimestamp();
    }

    public function getFullTimeAttribute() {
        return date("H:i", $this->start)." - ".date("H:i", $this->end);
    }

    public function getDayNameAttribute() {
        $d = new DateTime( $this->day );
        $weekDay = $d->format("w");
        return $d->format(__('app.format.date'))." ".__('event.weekdays_short.'.$weekDay);
    }


}
