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
    ];

    public function groupUsers() {
        return $this->belongsToMany('App\Models\User')
                ->withPivot('group_role')
                ->withTimestamps();
    }

    public function groupAdmins() {
        return $this->belongsToMany('App\Models\User')
                ->wherePivot('group_role','admin')
                ->withTimestamps();
    }

    public function currentList() {
        return $this->belongsToMany('App\Models\User')
                ->wherePivot('user_id', '=', Auth::id())
                ->withPivot('group_role')
                ->withTimestamps();
    }
}
