<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupDayDisabledSlots extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'day_number',
        'slot',
    ];

    protected $casts = [
        'slot' => 'datetime:H:i',
    ];

    protected $appends = ['slot_ts'];

    public function group() {
        return $this->belongsTo(Group::class);
    }

    //return in timestamp format
    public function getSlotTsAttribute() {
        return strtotime($this->slot);
    }
}
