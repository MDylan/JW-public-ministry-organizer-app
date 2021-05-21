<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

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
        'role'
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

    /**
     * Ellenőrzi van e ilyen joga a usernek
     */
    public function hasRole(string $role) {
        return $this->role === $role ? true : null;
    }

    public function userGroupsNotAccepted() {
        return $this->belongsToMany('App\Models\Group')
                    ->wherePivot('accepted_at', null)
                    ->using(GroupUser::class);
    }

    public function userGroups() {
        return $this->belongsToMany(Group::class)
                    ->withPivot(['group_role', 'note', 'accepted_at'])
                    ->withTimestamps()
                    ->using(GroupUser::class);
    }

    public function userGroups_old() {
        return $this->belongsToMany('App\Models\Group')
                    ->withPivot(['group_role', 'note', 'accepted_at'])
                    ->withTimestamps();
    }

    public function userGroupsEditable() {
        return $this->belongsToMany('App\Models\Group')
                    ->withPivot(['group_role'])
                    ->wherePivotIn('group_role', ['admin', 'roler'])
                    ->wherePivotNotNull('accepted_at')
                    ->withTimestamps()
                    ->using(GroupUser::class);
    }

    public function userGroupsDeletable() {
        return $this->belongsToMany('App\Models\Group')
                    ->withPivot(['group_role'])
                    ->wherePivot('group_role','admin')
                    ->withTimestamps()
                    ->using(GroupUser::class);
    }

}
