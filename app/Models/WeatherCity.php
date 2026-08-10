<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeatherCity extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'city',
        'country',
        'current_weather',
        'forecast_weather',
        'last_try'
    ];

    protected $casts = [
        'current_weather' => 'json',
        'forecast_weather' => 'json',
        'last_try' => 'datetime'
    ];

    /**
     * The groups using this city for weather.
     *
     * The key is named explicitly: by default `hasMany(Group::class)` would
     * have looked for `weather_city_id` in the groups table, but no such
     * column exists - its real name is `city_id`. The relation therefore
     * pointed at a nonexistent column, so every call to it would have errored
     * out. For lack of use, this hadn't surfaced until now (v1-patch C).
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'city_id', 'id');
    }
}
