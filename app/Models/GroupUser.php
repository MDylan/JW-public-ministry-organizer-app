<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

final class GroupUser extends Pivot
{
    use HasFactory, SoftDeletes;

    public $incrementing = true;

    protected $table 	= 'group_user';
    protected $fillable = ['user_id', 'group_id', 'group_role', 'accepted_at', 'note', 'hidden','deleted_at', 'signs'];
    protected $dates = ['created_at','updated_at','deleted_at'];

    protected $casts = [
        'signs' => 'array',
        'note' => 'encrypted',
    ];

    public function histories()
    {
        return $this->morphMany(LogHistory::class, 'model');
    }
}
