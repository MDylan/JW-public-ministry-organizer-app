<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Astrotomic\Translatable\Translatable;

class GroupNews extends Model
{
    use HasFactory, SoftDeletes, Translatable;

    public $translatedAttributes = ['title', 'content'];

    public $fillable = [
        'group_id',
        'user_id',
        'status',
        'date',
    ];


    protected $casts = [
        'date' => 'datetime:Y-m-d',
    ];

    public function histories()
    {
        return $this->morphMany(LogHistory::class, 'model');
    }

    public function group() {
        return $this->belongsTo(Group::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function files() {
        return $this->hasMany(GroupNewsFile::class, 'group_new_id');
    }
}
