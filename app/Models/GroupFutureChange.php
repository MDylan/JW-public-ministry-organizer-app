<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupFutureChange extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'group' => 'array',
        'days' => 'array',
        'disabled_slots' => 'array',
    ];

    public function group() {
        return $this->belongsTo(Group::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
