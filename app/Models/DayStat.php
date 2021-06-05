<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DayStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'day',
        'time_slot',
        'events'
    ];

    protected $casts = [
        'day' => 'datetime:Y-m-d',
        'time_slot' => 'datetime:Y-m-d H:i'
    ];

    public function group() {
        return $this->belongsTo(Group::class);
    }
}
