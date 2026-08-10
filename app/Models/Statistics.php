<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Statistics extends Model
{
    use HasFactory;

    // The statistics table has no created_at/updated_at column.
    public $timestamps = false;

    protected $fillable = [
        'type',
        'date',
        'number'
    ];
}
