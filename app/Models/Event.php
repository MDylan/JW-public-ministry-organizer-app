<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// use Spatie\Activitylog\Traits\LogsActivity;
// use Spatie\Activitylog\LogOptions;
use Spatie\CalendarLinks\Link;
use DateTime;

class Event extends Model
{
    use HasFactory, SoftDeletes; //, LogsActivity;

    // protected static $logFillable = true;
    // protected static $logName = 'event';
    // protected static $logOnlyDirty = true;

    protected $fillable = [
        'day',
        'start',
        'end',
        'user_id',
        'accepted_by',
        'accepted_at',
        'group_id',
        'status'
    ];

    protected $casts = [
        'day' => 'datetime:Y-m-d',
        'start' => 'datetime:Y-m-d H:i',
        'end' => 'datetime:Y-m-d H:i',
    ];

    protected $appends = ['full_time', 'day_name', 'service_hour', 
        // 'calendar_google', 'calendar_ics'
    ];

    // protected static $recordEvents = ['created', 'updated', 'deleted'];

    // public function getActivitylogOptions(): LogOptions
    // {
    //     return LogOptions::defaults()->logOnly([
    //         'day',
    //         'start',
    //         'end',
    //         'user_id',
    //         'accepted_by',
    //         'accepted_at',
    //         'group_id'
    //     ])->useLogName('event')->logOnlyDirty()->dontSubmitEmptyLogs();
    //     // ->logOnly(['name', 'value']);
    //     // Chain fluent methods for configuration options
    // }

    public function histories()
    {
        return $this->morphMany(LogHistory::class, 'model');
    }


    public function groups() {
        return $this->belongsTo(Group::class, 'group_id');
                    // ->whereNotNull('name');
    }

    public function user() {
        return $this->belongsTo(User::class)
                        ->addselect(['id', 'first_name', 'last_name']);
    }

    public function accept_user() {
        return $this->belongsTo(User::class, 'accepted_by')
                        ->addselect(['id', 'first_name', 'last_name']);;
    }

    public function serviceReports() {
        return $this->hasMany(EventServiceReport::class);
    }

    public function current_date() {
        return $this->hasOne(GroupDate::class, 'group_id', 'group_id');
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

    public function getServiceHourAttribute() {
        $startTime = Carbon::parse($this->start);
        $finishTime = Carbon::parse($this->end);
        return round(($finishTime->diffInMinutes($startTime) / 60), 2);
    }

    public function calendarLink() {
        $name = optional($this->groups)->name;
        if(!$name) $name = '';
        // dd($this->start, $this->end);
        return Link::create(
            $name,
            DateTime::createFromFormat('U', $this->start),
            DateTime::createFromFormat('U', $this->end)
        );
    }

    public function getCalendarGoogleAttribute() {
        return $this->calendarLink()->google();
    }

    public function getCalendarIcsAttribute() {
        return $this->calendarLink()->ics();
    }


}
