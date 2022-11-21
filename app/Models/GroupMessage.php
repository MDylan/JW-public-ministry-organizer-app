<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'user_id',
        'message',
        'priority',
    ];

    protected $casts = [
        'message' => 'encrypted'
    ];

    public function group() {
        return $this->belongsTo(Group::class)
                        ->select(['groups.id', 'groups.name', 'groups.messages_on', 'groups.messages_write', 'groups.messages_priority']);
    }

    public function user() {
        return $this->belongsTo(User::class)
                    ->select(['users.id', 'users.name', 'users.last_activity']);
    }
}
