<?php

use App\Domains\Market\Series\CommercialRentals;
use App\Domains\Market\Series\HousesForSale;
use App\Domains\Market\Series\LandsForSale;
use App\Domains\Market\Series\ResidentialRentals;

/*
|--------------------------------------------------------------------------
| Market
|--------------------------------------------------------------------------
|
| Price stats of the Análisis section (Market domain).
|
| - dollar: dollar type used to convert prices (argentinadatos.com: "blue", "oficial", "bolsa"...).
|   Real estate is priced with the blue dollar. After changing it, run market:snapshot --backfill.
| - series: one class per chart (App\Domains\Market\Contracts\PriceSeries), in this order.
| - size_ranges: m² bounds of the size ranges per series key ([1000, 5000] = up to 1000, 1000 to 5000,
|   more than 5000). Lands by plot area, houses by built area. After changing them, run market:snapshot --backfill.
|
*/

return [

    'dollar' => env('MARKET_DOLLAR', 'blue'),

    'series' => [
        ResidentialRentals::class,
        CommercialRentals::class,
        HousesForSale::class,
        LandsForSale::class,
    ],

    'size_ranges' => [
        'lands_for_sale' => [1000, 5000],
        'houses_for_sale' => [80, 150],
    ],

];
