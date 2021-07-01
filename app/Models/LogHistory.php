<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'event',
        'group_id',
        'model_id',
        'model_type',
        'causer_id',
        'changes'
    ];

    public function model()
    {
        return $this->morphTo();
    }
}
