<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements MustVerifyEmail, HasLocalePreference
{
    use HasFactory, Notifiable, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'role',
        'last_login_time',
        'last_login_ip'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'language',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'created_at',
        'updated_at'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = ['full_name'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logOnly(['role'])->useLogName('userRole')->logOnlyDirty()->dontSubmitEmptyLogs();
        // Chain fluent methods for configuration options
    }

    /**
     * Ellenőrzi van e ilyen joga a usernek
     */
    public function hasRole(string $role) {
        return $this->role === $role ? true : null;
    }

    public function userGroupsNotAccepted() {
        return $this->belongsToMany(Group::class)
                    ->wherePivot('accepted_at', null)
                    ->wherePivot('deleted_at', null)
                    ->using(GroupUser::class);
    }

    public function userGroupsNotAcceptedNumber() {
        return $this->belongsToMany(Group::class)
                    ->wherePivot('accepted_at', null)
                    ->wherePivot('deleted_at', null)
                    ->using(GroupUser::class)
                    ->count();
    }

    public function userGroups() {
        return $this->belongsToMany(Group::class)
                    ->withPivot(['group_role', 'note', 'accepted_at'])
                    ->withTimestamps()
                    ->wherePivot('deleted_at', null)
                    ->using(GroupUser::class);
    }

    public function groupsAccepted() {
        return $this->userGroups()->wherePivotNotNull('accepted_at');
    }

    public function groupsAcceptedFiltered() {
        return $this->belongsToMany(Group::class)
                        ->withPivot(['group_role', 'note', 'accepted_at'])
                        ->wherePivotNotNull('accepted_at')
                        ->wherePivotNull('deleted_at')
                        // ->select(['groups.id', 'groups.name', 'groups.min_publishers', 'groups.max_publishers', 'groups.max_extend_days', 'groups.created_at'])
                        ->with('days');
    }

    public function groupsAcceptedNumber() {
        return $this->userGroups()->wherePivotNotNull('accepted_at')
                    ->count();
    }

    public function events() {
        return $this->hasMany(Event::class)
                ->orderBy('start')
                ->with('groups');
    }

    public function feature_events($start = false) {
        if(!$start) $start = date("Y-m-d H:i:s");
        return $this->events()
                ->where('start', '>=', $start)
                ->whereIn('status', [1]);
    }

    public function getFullNameAttribute() {
        return "{$this->last_name} {$this->first_name}";
    }

    function scopeWhereFullName($query, $value) {
        $query->where(DB::raw('concat(first_name, " ", last_name)'), 'LIKE', "%{$value}%");
    }

    public function canSetEventUser() {
        return $this->belongsToMany(Group::class)
                    ->withPivot(['group_role'])
                    ->wherePivotIn('group_role', ['admin', 'roler', 'helper'])
                    ->wherePivotNotNull('accepted_at')
                    ->wherePivot('deleted_at', null);
                    // ->using(GroupUser::class);
    }

    public function userGroupsEditable() {
        return $this->belongsToMany(Group::class)
                    ->withPivot(['group_role'])
                    ->wherePivotIn('group_role', ['admin', 'roler'])
                    ->wherePivotNotNull('accepted_at')
                    ->wherePivot('deleted_at', null)
                    ->withTimestamps()
                    ->using(GroupUser::class);
    }

    public function userGroupsDeletable() {
        return $this->belongsToMany(Group::class)
                    ->withPivot(['group_role'])
                    ->wherePivot('group_role','admin')
                    ->wherePivot('deleted_at', null)
                    ->withTimestamps()
                    ->using(GroupUser::class);
    }

    /**
     * Get the user's preferred locale.
     *
     * @return string
     */
    public function preferredLocale()
    {
        return $this->language;
    }

}
