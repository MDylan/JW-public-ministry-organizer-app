<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNewsletterRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'admin_newsletter_id'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function newsletter() {
        return $this->belongsTo(AdminNewsletter::class);
    }

}
