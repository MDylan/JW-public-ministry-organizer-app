<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Dialect\Gdpr\Portable;
use Dialect\Gdpr\Anonymizable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail, HasLocalePreference
{
    use HasFactory, Notifiable, LogsActivity, Portable, Anonymizable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'password',
        'role',
        'last_login_time',
        'last_login_ip',
        'last_activity',
        'accepted_gdpr',
        'isAnonymized',
        'calendars',
        'language',
        'name',
        'phone_number',
        'email_verified_at',
        'hidden_fields',
        'firstDay',
        'opted_out_of_notifications'
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
        'calendars' => 'array',
        'phone_number' => 'encrypted',
        'hidden_fields' => 'array',
        'name' => 'encrypted',
        'opted_out_of_notifications' => 'array',
    ];

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
        'accepted_gdpr',
        'hidden_fields',
        'opted_out_of_notifications'
    ];

    /**
     * The relations to include in the downloadable data.
     *
     * @var array
     */
    protected $gdprWith = ['eventsOnly', 'groupsAccepted'];

    protected $gdprAnonymizableFields = [
        'email',
        'name' => 'Anonym',
        'phone' => '0',
        'role' => 'registered',
        'last_login_ip' => '',
        'isAnonymized' => 1,
        'opted_out_of_notifications' => ''
    ];


    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logOnly(['role'])->useLogName('userRole')->logOnlyDirty()->dontSubmitEmptyLogs();
        // Chain fluent methods for configuration options
    }

    /**
     * Check user's rule
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
                    ->withPivot(['group_role', 'note', 'accepted_at','list_order'])
                    ->withTimestamps()
                    ->wherePivot('deleted_at', null)
                    ->using(GroupUser::class);
    }

    public function groupsAccepted() {
        return $this->userGroups()->wherePivotNotNull('accepted_at');
    }

    public function userGroupsAcceptedOnly() {
        return $this->belongsToMany(Group::class)
                    ->withPivot(['group_role',])
                    ->wherePivot('deleted_at', null)
                    ->wherePivotNotNull('accepted_at')
                    ->using(GroupUser::class);
    }

    public function groupsAcceptedFiltered() {
        return $this->belongsToMany(Group::class)
                        ->withPivot(['group_role', 'note', 'accepted_at'])
                        ->wherePivotNotNull('accepted_at')
                        ->wherePivotNull('deleted_at')
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

    public function canSetEventUser() {
        return $this->belongsToMany(Group::class)
                    ->withPivot(['group_role'])
                    ->wherePivotIn('group_role', ['admin', 'roler', 'helper'])
                    ->wherePivotNotNull('accepted_at')
                    ->wherePivot('deleted_at', null);
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
        return $array;
    }

    public function confirmTwoFactorAuth($code)
    {
        $codeIsValid = app(TwoFactorAuthenticationProvider::class)
            ->verify(decrypt($this->two_factor_secret), $code);

        if ($codeIsValid) {
            $this->two_factor_confirmed = true;
            $this->save();

            return true;
        }

        return false;
    }

}
