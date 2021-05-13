<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;


class Group extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name',
        'max_extend_days',
        'min_publishers',
        'max_publishers',
        'min_time',
        'max_time',
    ];

    public function groupUsers() {
        return $this->belongsToMany('App\Models\User')
                ->withPivot('group_role', 'note', 'accepted_at')
                ->withTimestamps();
    }

    public function groupAdmins() {
        return $this->belongsToMany('App\Models\User')
                ->wherePivot('group_role','admin')
                ->withTimestamps();
    }

    /**
     * Akik szerkeszteni tudják a csoportot
     */
    public function editors() {
        return $this->belongsToMany('App\Models\User')
                ->wherePivotIn('group_role',['roler', 'admin'])
                ->withTimestamps()
                ->as('group_editors');
    }

    public function currentList() {
        return $this->belongsToMany('App\Models\User')
                ->wherePivot('user_id', '=', Auth::id())
                ->withPivot('group_role')
                ->withTimestamps();
    }

    public function days() {
        return $this->hasMany('App\Models\GroupDay');
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
