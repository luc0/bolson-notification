<?php

/*
|--------------------------------------------------------------------------
| Harvesting
|--------------------------------------------------------------------------
|
| Where the scraper stores its files (Harvesting domain).
|
| Per catalog (rentals: sources.rental_sites, lands: sources.land_sites):
| - capture_path: last HTML capture of each source (+ "<key>.images.json").
| - snapshot_path: last capture that was fully processed, to skip unchanged sources.
|
| Rentals keep the original folders so existing snapshots stay valid after the upgrade.
|
*/

return [

    'rentals' => [
        'capture_path' => storage_path('app/captures'),
        'snapshot_path' => storage_path('app/snapshots'),
    ],

    'lands' => [
        'capture_path' => storage_path('app/captures/lands'),
        'snapshot_path' => storage_path('app/snapshots/lands'),
    ],

];
