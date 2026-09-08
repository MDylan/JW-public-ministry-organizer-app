<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Contracts\Translation\HasLocalePreference;
use App\Support\Email\MustVerifyNewEmail;
use App\Support\Gdpr\AnonymizationPolicy;
use App\Support\Gdpr\Portable;
use App\Support\Gdpr\Anonymizable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail, HasLocalePreference
{
    use HasFactory, Notifiable, LogsActivity, Portable, Anonymizable, TwoFactorAuthenticatable, MustVerifyNewEmail {
        Anonymizable::anonymize as protected anonymizeAttributes;
    }

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
        // 'hidden_fields',
        'firstDay',
        'opted_out_of_notifications',
        'show_fields',
        'congregation'
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
        'two_factor_confirmed_at' => 'datetime',
        'calendars' => 'array',
        'phone_number' => 'encrypted',
        // 'hidden_fields' => 'array',
        'name' => 'encrypted',
        'opted_out_of_notifications' => 'array',
        'show_fields' => 'array',
        'congregation' => 'encrypted',
    ];

    /**
     * The attributes that should NOT be visible in the downloadable data.
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
        // 'hidden_fields',
        'show_fields',
        'opted_out_of_notifications'
    ];

    /**
     * The relations to include in the downloadable data.
     *
     * @var array
     */
    protected $gdprWith = ['eventsOnly', 'groupsAccepted'];

    /**
     * Columns that get a replacement VALUE when the user is anonymized.
     *
     * A keyless entry needs a getAnonymized{Column}() method on this model to
     * supply the value; App\Support\Gdpr\Anonymizable throws if one is missing,
     * rather than writing the column's own name into it the way the old
     * Dialect package did.
     *
     * Columns with nothing worth keeping belong in $gdprNullFields below.
     */
    protected $gdprAnonymizableFields = [
        'email',                        // getAnonymizedEmail()
        'password',                     // getAnonymizedPassword()
        'name' => 'Anonym',
        'role' => 'registered',
        'isAnonymized' => 1,
    ];

    /**
     * Columns that are simply emptied. TODO 33.2.
     *
     * There is nothing worth storing in any of these once a user has asked to
     * be forgotten, so they get NULL rather than a placeholder. Every column
     * here is nullable in the schema - check before adding one.
     *
     * The last seven were not anonymized at all before TODO 33.2. Two of them
     * mattered more than the rest: two_factor_secret / _recovery_codes are
     * encrypted credentials that used to outlive the anonymization forever, and
     * remember_token kept a live "remember me" cookie working, because
     * Auth::logout() only runs on the profile-initiated path and never in the
     * nightly command.
     *
     * NOTE: emptying email_verified_at drops the row into
     * users:purge-unverified's selection, which hard-deletes. That command
     * therefore carries an isAnonymized filter - see PurgeUnverifiedUsers.
     */
    protected $gdprNullFields = [
        'phone_number',
        'congregation',
        'show_fields',
        'opted_out_of_notifications',
        'last_login_ip',
        'firstDay',
        'two_factor_secret',
        'two_factor_recovery_codes',
        // TODO 39.2 moved this here from $gdprAnonymizableFields. It used to
        // need a replacement value because the boolean it replaced was NOT
        // NULL; the timestamp is nullable, so "never confirmed" is expressible.
        'two_factor_confirmed_at',
        'remember_token',
        'calendars',
        'last_login_time',
        'email_verified_at',
        'accepted_gdpr',
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
                    ->withPivot(['group_role','message_use', 'message_send_priority'])
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
                    ->wherePivotNotNull('accepted_at')
                    ->wherePivot('deleted_at', null)
                    ->withTimestamps()
                    ->using(GroupUser::class);
    }

    public function posterReads() {
        return $this->hasMany(GroupPosterRead::class);
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
     * TODO 12.2: the condition for anonymization is succession.
     *
     * The guard sits here rather than in the nightly command on purpose: four
     * code paths anonymize a user - the command, the profile-initiated GDPR
     * request, DeleteGroupDataProcess and the one-off backfill migration - and
     * a rule placed in any one of them is bypassed by the other three.
     * TODO 33.2 removed a fifth path with the Dialect package's own command.
     *
     * Callers should check with AnonymizationPolicy beforehand if the
     * blocking has a consequence (a message to the user, skipping the
     * membership dissolution) - this method silently does nothing in that case.
     *
     * @return bool whether the anonymization happened
     */
    public function anonymize($modelChecker = [])
    {
        if (! AnonymizationPolicy::for($this)->allows()) {
            return false;
        }

        $this->anonymizeAttributes($modelChecker);

        // The pending email address does NOT get anonymized on its own: the
        // pending_user_emails row lives in a separate table, has no foreign
        // key to it, and none of the eight observers touch it. So users.email
        // gets replaced while the user's REAL address stays in the pending
        // table indefinitely - exactly the data whose deletion was requested.
        //
        // For the other half of the signed link that was issued, see App\Models\PendingUserEmail.
        $this->clearPendingEmail();

        return true;
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
     * The replacement for the password hash.
     *
     * users.password is NOT NULL, so it cannot simply be emptied. Before
     * TODO 33.2 the original hash survived anonymization untouched and only the
     * replaced email address made the account unreachable - a working
     * credential kept for someone who asked to be forgotten. This hashes 64
     * random characters that are never stored anywhere, so no password opens
     * the account again.
     */
    public function getAnonymizedPassword()
    {
        return Hash::make(Str::random(64));
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

    /**
     * Whether this user has proved they can read their own authenticator.
     *
     * TODO 39.2 added this accessor so that nothing outside the model has to
     * name the column the answer is stored in. Fortify's own
     * hasEnabledTwoFactorAuthentication() answers a different question - it
     * only asks whether a secret exists - and enabling without confirming is
     * exactly the state this project keeps apart from a confirmed one.
     *
     * The timestamp is only ever asked whether it is null. Its value is a
     * marker rather than a measurement for every row that predates the
     * TODO 39.2 migration, which is where that is written down.
     */
    public function hasConfirmedTwoFactorAuth(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    public function confirmTwoFactorAuth($code)
    {
        $codeIsValid = app(TwoFactorAuthenticationProvider::class)
            ->verify(decrypt($this->two_factor_secret), $code);

        if ($codeIsValid) {
            $this->two_factor_confirmed_at = $this->freshTimestamp();
            $this->save();

            return true;
        }

        return false;
    }

    //this check if user email is valid or this is an anonymized user
    public function routeNotificationFor($driver, $notification = null)
    {
        if($driver == "mail") {
            // (string) because filter_var() takes a non-nullable parameter and
            // PHP 8.1 deprecates passing null to it. The column is NOT NULL
            // today, so this costs nothing and closes the deprecation early.
            if (!filter_var((string) $this->email, FILTER_VALIDATE_EMAIL)
                    || $this->isAnonymized == 1) {
                return null;
            } 
            return $this->email;
        }        
    }
}
