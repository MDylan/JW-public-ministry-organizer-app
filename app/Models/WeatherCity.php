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
     * Az ezt a települést használó csoportok.
     *
     * A kulcsot expliciten megnevezzük: a `hasMany(Group::class)` alapértelmezés
     * szerint `weather_city_id`-t keresett volna a groups táblában, ilyen oszlop
     * viszont nincs - a valódi neve `city_id`. A reláció így egy nem létező
     * oszlopra mutatott, tehát minden hívása hibára futott volna. Használat
     * híján ez eddig nem derült ki (v1-patch C).
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'city_id', 'id');
    }
}
