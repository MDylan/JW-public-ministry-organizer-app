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

    public function groupUsers_old() {
        return $this->belongsToMany('App\Models\User')
                ->withPivot('group_role', 'note', 'accepted_at')
                ->withTimestamps();
    }

    public function groupAdmins() {
        return $this->belongsToMany('App\Models\User')
                ->wherePivot('group_role','admin')
                ->withTimestamps()
                ->using(GroupUser::class);
    }

    /**
     * Akik szerkeszteni tudják a csoportot
     */
    public function editors() {
        return $this->belongsToMany('App\Models\User')
                ->wherePivotIn('group_role',['roler', 'admin'])
                ->withTimestamps()
                ->as('group_editors')
                ->using(GroupUser::class);
    }

    public function currentList() {
        return $this->belongsToMany('App\Models\User')
                ->wherePivot('user_id', '=', Auth::id())
                ->withPivot('group_role')
                ->withTimestamps()
                ->using(GroupUser::class);
    }

    public function days() {
        return $this->hasMany('App\Models\GroupDay');
    }

    public function events() {
        return $this->hasMany('App\Models\Event')
                    ->orderBy('start')
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

    // public function eventUser() {
    //     return $this->hasManyThrough(Event::class, User::class);
    // }

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
