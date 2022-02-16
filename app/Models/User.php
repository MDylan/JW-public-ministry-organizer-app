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
use Dialect\Gdpr\Portable;
use Dialect\Gdpr\Anonymizable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail, HasLocalePreference
{
    use HasFactory, Notifiable, LogsActivity, Portable, Anonymizable;

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
        'last_login_ip',
        'last_activity',
        'accepted_gdpr',
        'isAnonymized'
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
        'updated_at',
        'accepted_gdpr',
        'isAnonymized',
        'events.groups'
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

    /**
     * The attributes that should be visible in the downloadable data.
     *
     * @var array
     */
    protected $gdprHidden  = [
        'id',
        'role',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'accepted_gdpr'
    ];

    /**
     * The relations to include in the downloadable data.
     *
     * @var array
     */
    protected $gdprWith = ['eventsOnly', 'groupsAccepted'];

    protected $gdprAnonymizableFields = [
        'email',
        'last_name' => 'Anonym',
        'first_name' => 'Anonym',
        'phone' => '0',
        'role' => 'registered',
        'last_login_ip' => '',
        'isAnonymized' => 1
    ];


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

    public function eventsOnly() {
        return $this->hasMany(Event::class)
                ->orderBy('start');
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
                ->whereIn('status', [0,1]);
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

    /**
    * Using getAnonymized{column} to return anonymizable data
    */
    public function getAnonymizedEmail()
    {
        // return random_bytes(10);
        return Str::random(10);
    }

        /**
     * Get the GDPR compliant data portability array for the model.
     *
     * @return array
     */
    public function toPortableArray()
    {
        $array = $this->toArray();

        //filter out some other fields
        $hidden_events_fields = [
            'id',
            'group_id',
            'user_id',
            'accepted_by',
            'accepted_at',
            'start',
            'end',
        ];

        if(count($array['events_only'])) {
            foreach($array['events_only'] as $key => $event) {
                foreach($hidden_events_fields as $field) {
                    unset($array['events_only'][$key][$field]);
                }
            }
        }
        if(count($array['groups_accepted'])) {
            $groups = [];
            foreach($array['groups_accepted'] as $group) {
                $groups[] = [
                    'name' => $group['name'],
                    'accepted_at' => $group['pivot']['accepted_at']
                ];
            }
            $array['groups'] = $groups;
            unset($array['groups_accepted']);
        }       

        // dd($array);

        return $array;
    }

}
