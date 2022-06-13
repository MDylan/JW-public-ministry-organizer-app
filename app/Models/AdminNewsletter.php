<?php

namespace App\Models;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNewsletter extends Model
{
    use HasFactory, Translatable;

    public $translatedAttributes = ['subject', 'content'];

    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime:Y-m-d',
        'sent_time' => 'datetime:Y-m-d H:i:s',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

}
