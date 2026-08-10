<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupPosterRead extends Model
{
    use HasFactory;

    // The group_poster_reads table has no created_at/updated_at column.
    public $timestamps = false;

    protected $fillable = [
        'poster_id',
        'user_id',
    ];

    public function poster() {
        return $this->belongsTo(GroupPosters::class);
    }

    public function users() {
        return $this->hasMany(User::class);
    }
}
