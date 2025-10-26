<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryCurrency extends Model
{
    use HasFactory;
    protected $table = 'country_currencies';
    protected $fillable = [
        'name',
        'capital',
        'region',
        'population',
        'currency_code',
        'exchange_rate',
        'estimated_gdp',
        'flag_url',
        'last_refreshed_at'
    ];
    protected $casts = [
        'population' => 'integer',
        'exchange_rate' => 'decimal:6',
        'estimated_gdp' => 'decimal:4',
        'last_refreshed_at' => 'datetime',
    ];
}
