<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'date',
        'date_start',
        'date_end',
        'date_status',
        'note',
        'date_min_publishers',
        'date_max_publishers',
        'date_min_time',
        'date_max_time',
        'run_job',
        'disabled_slots'
    ];

    protected $casts = [
        'disabled_slots' => 'array'
    ];

    public function group() {
        return $this->belongsTo(Group::class);
    }
}
