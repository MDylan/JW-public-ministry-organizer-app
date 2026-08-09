<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Dialect\Gdpr\Anonymizable;

class Group extends Model
{
    use HasFactory, SoftDeletes, Anonymizable;

    // TODO 29: a `protected $dates = ['deleted_at']` sor innen eltűnt. A
    // Laravel 10 a propertyt megszüntette, és a `deleted_at` castolását
    // amúgy is a `SoftDeletes` trait végzi (`initializeSoftDeletes()` beírja
    // a `$casts`-ba, ha nincs ott), tehát a sor eleve redundáns volt.

    protected $fillable = [
        'name',
        'max_extend_days',
        'min_publishers',
        'max_publishers',
        'min_time',
        'max_time',
        'need_approval',
        'color_default',
        'color_empty',
        'color_someone',
        'color_minimum',
        'color_maximum',
        'parent_group_id',
        'signs',
        'languages',
        'replyTo',
        'showPhone',
        'copy_from_parent',
        'messages_on',
        'messages_write',
        'messages_priority',
        'auto_approval',
        'auto_back',
        'city_id',
        'weather_enabled'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'signs' => 'array',
        'languages' => 'array',
        'copy_from_parent' => 'array',
        'replyTo' => 'encrypted',
        'name' => 'encrypted',
    ];

    protected $appends = ['colors'];

    protected $gdprAnonymizableFields = [];
    
    public function histories()
    {
        return $this->morphMany(LogHistory::class, 'model');
    }

    public function groupUsers() {
        return $this->belongsToMany(User::class)
                        ->withPivot('id', 'group_role', 'note', 'accepted_at', 'hidden', 'deleted_at', 'signs', 'message_use', 'message_send_priority')
                        ->withTimestamps()
                        ->whereNull('deleted_at')
                        ->where('isAnonymized', 0)
                        ->using(GroupUser::class)
                        ->orderByRaw('name_index, email');
    }

    public function groupUsersAll() {
        return $this->belongsToMany(User::class)
                        ->withPivot('group_role', 'note', 'accepted_at', 'hidden', 'deleted_at', 'signs')
                        ->withTimestamps()
                        ->using(GroupUser::class);
    }

    public function groupUsersAllOnly() {
        return $this->belongsToMany(User::class)
                        ->using(GroupUser::class);
    }

    public function currentUser() {
        return $this->belongsToMany(User::class)
                        ->withPivot('group_role')
                        ->wherePivot('deleted_at', null)
                        ->using(GroupUser::class);
    }

    public function users() {
        return $this->belongsToMany(User::class)
                        ->select(['users.id', 'users.name'])
                        ->where('isAnonymized', 0)
                        ->withPivot('group_role', 'signs')
                        ->wherePivot('deleted_at', null)
                        ->whereNotNull('accepted_at')
                        ->using(GroupUser::class)
                        ->orderBy('name_index', 'asc');
    }

    public function groupAdmins() {
        return $this->belongsToMany(User::class)
                ->wherePivot('group_role','admin')
                ->withTimestamps()
                ->wherePivot('deleted_at', null)
                ->using(GroupUser::class);
    }

    /**
     * Azok az adminok, akik valóban át tudják venni a csoportot.
     *
     * A groupAdmins() nem szűri sem az isAnonymized-et, sem az accepted_at-ot,
     * ezért egy anonimizált felhasználó vagy egy még el nem fogadott meghívott
     * is adminnak látszik rajta keresztül. Az utódlási vizsgálatnak
     * (pwbs_check_group_other_admins(), TODO 12.2) ez nem elég: a csomag napi
     * anonimizálója meghagyja a tagságot, így az anonimizált sor utódnak
     * számítana, és a csoport összes valódi adminja egymás után kiüríthető lenne.
     *
     * A groupAdmins() maga szándékosan változatlan: a további hívási helyei a
     * SAJÁT jogosultságot ellenőrzik (wherePivot('user_id', Auth::id())), ott a
     * szűrés felesleges.
     */
    public function activeAdmins() {
        return $this->groupAdmins()
                ->where('isAnonymized', 0)
                ->wherePivotNotNull('accepted_at');
    }

    /**
     * Akik szerkeszteni tudják a csoportot
     */
    public function editors() {
        return $this->belongsToMany(User::class)
                ->wherePivotIn('group_role',['roler', 'admin'])
                ->wherePivot('deleted_at', null)
                ->withTimestamps()
                ->as('group_editors')
                ->using(GroupUser::class);
    }

    public function currentList() {
        return $this->belongsToMany(User::class)
                ->wherePivot('user_id', '=', Auth::id())
                ->withPivot('group_role')
                ->wherePivot('deleted_at', null)
                ->withTimestamps()
                ->using(GroupUser::class);
    }

    public function days() {
        return $this->hasMany(GroupDay::class)
                    ->select(['group_id', 'day_number', 'start_time', 'end_time'])
                    ->orderBy('day_number');
    }

    public function disabled_slots() {
        return $this->hasMany(GroupDayDisabledSlots::class)
                    ->select(['group_id', 'day_number', 'slot'])
                    ->orderBy('day_number');
    }

    public function justEvents() {
        return $this->hasMany(Event::class)
                    ->orderBy('start');
    }

    public function eventsNotAccepted() {
        return $this->justEvents()
                ->where('status', '=', '0');
    }

    public function events() {
        return $this->justEvents()
                    ->with('user')          //join users table
                    ->with('accept_user');  //join users table
    }
  
    public function day_events($day) {
        return $this->events()
                    ->where('day', '=', $day)
                    ->whereIn('status', [0,1]);
    }

    public function day_events_accepted($day) {
        return $this->events()
                    ->where('day', '=', $day)
                    ->where('status', '=', '1');
    }

    public function between_events($start, $end) {
        return $this->events()
            ->whereBetween('day', [$start, $end])
            ->whereIn('status', [0,1]);
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

    public function latest_new() {
        return $this->hasOne(GroupNews::class)->ofMany(
            [ 'id' => 'max'], 
            function($q) { 
                $q->where('status', '1');
                $q->whereDate('date', '<=', now());
                // $q->whereDate('updated_at', '>', auth()->user()->last_login_time);
            });
    }

    public function news_log_old() {
        return $this->hasMany(GroupNewsUserLogs::class);
    }

    public function news_log() {
        return $this->hasOne(GroupNewsUserLogs::class)->ofMany(
            [ 'updated_at' => 'max'], 
            function($q) { 
                $q->where('user_id', Auth()->user()->id);
            });
    }

    public function literatures() {
        return $this->hasMany(GroupLiterature::class);
    }

    public function dates() {
        return $this->hasMany(GroupDate::class);
    }

    public function current_date() {
        return $this->hasOne(GroupDate::class);
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

    public function childGroups() {
        return $this->hasMany(Group::class, 'parent_group_id', 'id');
    }

    public function parentGroup() {
        return $this->belongsTo(Group::class, 'parent_group_id', 'id');
    }

    public function getColorsAttribute() {
        $colors = config('events.default_colors');
        foreach($colors as $color => $rgb) {
            if(!empty($this->$color)) {
                $colors[$color] = $this->$color;
            } 
        }
        return $colors;
    }

    public function posters() {
        return $this->hasMany(GroupPosters::class)
                    ->with('userRead')
                    ->orderBy('show_date', 'asc');
    }

    public function futureChanges() {
        return $this->hasOne(GroupFutureChange::class);
    }

    public function messages() {
        return $this->hasMany(GroupMessage::class);
    }

    public function weather() {
        return $this->belongsTo(WeatherCity::class, 'city_id', 'id');
    }
}
