<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'day_number',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function group() {
        return $this->belongsTo('App\Models\Group');
    }

    //vissza alakítom, hogy az űrlapok működjenek rendesen
    public function getStartTimeAttribute($value) {
        // return $value;
        return date( 'H:i', strtotime($value) );
    }
    public function getEndTimeAttribute($value) {
        return date( 'H:i', strtotime($value) );
    }
}
