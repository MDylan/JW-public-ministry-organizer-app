<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Statistics extends Model
{
    use HasFactory;

    // A statistics táblában nincs created_at/updated_at oszlop.
    public $timestamps = false;

    protected $fillable = [
        'type',
        'date',
        'number'
    ];
}
