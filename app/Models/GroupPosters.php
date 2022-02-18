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

    public function group() {
        return $this->belongsTo(Group::class);
    }
}
