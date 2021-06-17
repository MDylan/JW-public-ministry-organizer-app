<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;

class Group extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name',
        'max_extend_days',
        'min_publishers',
        'max_publishers',
        'min_time',
        'max_time',
    ];

    protected static $logFillable = true;
    protected static $logName = 'group';
    protected static $logOnlyDirty = true;


    public function groupUsers() {
        return $this->belongsToMany(User::class)
                        ->withPivot('group_role', 'note', 'accepted_at')
                        ->withTimestamps()
                        ->using(GroupUser::class);
    }

    public function users() {
        return $this->belongsToMany(User::class)
                        ->select(['users.id', 'users.first_name', 'users.last_name'])
                        ->withPivot('group_role')
                        ->using(GroupUser::class);
    }

    public function groupAdmins() {
        return $this->belongsToMany(User::class)
                ->wherePivot('group_role','admin')
                ->withTimestamps()
                ->using(GroupUser::class);
    }

    /**
     * Akik szerkeszteni tudják a csoportot
     */
    public function editors() {
        return $this->belongsToMany(User::class)
                ->wherePivotIn('group_role',['roler', 'admin'])
                ->withTimestamps()
                ->as('group_editors')
                ->using(GroupUser::class);
    }

    public function currentList() {
        return $this->belongsToMany(User::class)
                ->wherePivot('user_id', '=', Auth::id())
                ->withPivot('group_role')
                ->withTimestamps()
                ->using(GroupUser::class);
    }

    public function days() {
        return $this->hasMany(GroupDay::class)
                    ->orderBy('day_number');
    }

    public function justEvents() {
        return $this->hasMany(Event::class)
                    ->orderBy('start');
    }

    public function events() {
        return $this->justEvents()
                    ->with('user')          //join users table
                    ->with('accept_user');  //join users table
    }
    
    public function day_events($day) {
        return $this->events()->where('day', '=', $day);
    }

    public function between_events($start, $end) {
        return $this->events()->whereBetween('day', [$start, $end]);
    }

    public function stats() {
        return $this->hasMany(DayStat::class);
    }

    public function month_stats($start) {
        $end = date("Y-m-t", strtotime($start));
        return $this->stats()->whereBetween('day', [$start, $end]);
    }

    public function news() {
        return $this->hasMany(GroupNews::class)
                    ->orderBy('date', 'DESC')
                    ->with('user');
    }

    public function latest_news() {
        return $this->hasMany(GroupNews::class)
                ->where('status', 1)
                ->whereDate('date', '<=', now())
                ->orderBy('date', 'DESC');
    }

    public function news_log() {
        return $this->hasMany(GroupNewsUserLogs::class);
    }

    /**
     * Az adott css-t adja vissza, a megjelenítésnél van szerepe
     */
    public function getGroupRoleAttribute() {
        $css = [
            'member' => 'secondary',           
            'helper' => 'info',     
            'roler' => 'success',  
            'admin' => 'primary'
        ];
        return $css[$this->pivot->group_role];
    }
}
