<?php

/*
|--------------------------------------------------------------------------
| Harvesting
|--------------------------------------------------------------------------
|
| Where the scraper stores its files (Harvesting domain).
|
| Per catalog (rentals: sources.rental_sites, lands: sources.land_sites, houses: sources.house_sites):
| - capture_path: last HTML capture of each source (+ "<key>.images.json").
| - snapshot_path: last capture that was fully processed, to skip unchanged sources.
|
| Rentals keep the original folders so existing snapshots stay valid after the upgrade.
|
| price_changes.max_ratio: a listing's new price is only taken if it's at most this many times higher or lower
|   than the known one; a bigger jump is most likely the AI misreading it (an extra zero) and is ignored (logged).
| relisting_after_days: a listing that comes back (same URL or agency id) after being unpublished this long is
|   a new availability: it's stored and notified as a new listing instead of reactivating the old one. Shorter gaps
|   are the AI skipping it in a run or the agency pausing it.
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

    'houses' => [
        'capture_path' => storage_path('app/captures/houses'),
        'snapshot_path' => storage_path('app/snapshots/houses'),
    ],

    'price_changes' => [
        'max_ratio' => 3,
    ],

    'relisting_after_days' => 30,

];
