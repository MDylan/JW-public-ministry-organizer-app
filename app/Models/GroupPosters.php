<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupPosters extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'info',
        'show_date',
        'hide_date',
    ];

    protected $casts = [
        'info' => 'encrypted'
    ];

    public function group() {
        return $this->belongsTo(Group::class);
    }

    public function reads() {
        return $this->hasMany(GroupPosterRead::class, 'poster_id');
    }

    public function userRead() {
        return $this->reads()->where('user_id', auth()->id());
    }
}
