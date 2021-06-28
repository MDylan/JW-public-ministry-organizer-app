<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventServiceReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_literature_id',
        'placements',
        'videos',
        'return_visits',
        'bible_studies',
        'note'
    ];

    public function event() {
        return $this->belongsTo(Event::class);
    }

    public function literature() {
        return $this->belongsTo(GroupLiterature::class, 'group_literature_id');
    }
}
