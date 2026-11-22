<?php

namespace App\Domains\Market\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Monthly average of a dollar type ("blue") in pesos, see ExchangeRates.
 */
class ExchangeRate extends Model
{
    protected $fillable = ['period', 'dollar', 'ars_per_usd'];

    protected $casts = [
        'period' => 'immutable_date',
        'ars_per_usd' => 'float',
    ];
}
