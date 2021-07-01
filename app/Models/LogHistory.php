<?php

namespace App\Models;

use Carbon\Carbon;
use DateTime;
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

    protected $visible = [
        'event', 'created_at_format', 'model_name', 'changes_array', 'user'
    ];

    public $appends = ['model_name', 'changes_array', 'created_at_format'];

    public function model()
    {
        return $this->morphTo();
    }

    public function user() {
        return $this->belongsTo(User::class, 'causer_id')
                ->select(['id', 'first_name', 'last_name']);
    }

    public function getModelNameAttribute() {
        $parts = explode("\\", $this->model_type);
        return end($parts);
    }

    public function getChangesArrayAttribute() {
        $arr = json_decode($this->getAttribute('changes'), true);
        if(!is_array($arr)) $arr = array();
        return $arr;
    }

    public function getCreatedAtFormatAttribute() {
        $d = new DateTime( $this->created_at );
        return $d->format(__('app.format.datetime'));
        // return Carbon::parse($this->getAttribute('created_at'))->format(__('app.format.datetime'));
    }
}
