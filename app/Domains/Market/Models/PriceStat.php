<?php

namespace App\Domains\Market\Models;

use App\Domains\Locations\Enums\City;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Enums\PriceUnit;
use Illuminate\Database\Eloquent\Model;

/**
 * Price stats of the listings of a series published in a month (see MarketSnapshot).
 * city / bedrooms null = every city / every bedroom count.
 */
class PriceStat extends Model
{
    protected $fillable = [
        'period',
        'series',
        'city',
        'bedrooms',
        'currency',
        'unit',
        'listings_count',
        'median',
        'average',
        'p25',
        'p75',
    ];

    protected $casts = [
        'period' => 'immutable_date',
        'city' => City::class,
        'bedrooms' => 'integer',
        'currency' => Currency::class,
        'unit' => PriceUnit::class,
        'listings_count' => 'integer',
        'median' => 'float',
        'average' => 'float',
        'p25' => 'float',
        'p75' => 'float',
    ];
}
