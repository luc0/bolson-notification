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

];
