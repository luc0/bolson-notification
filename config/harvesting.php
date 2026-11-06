<?php

/*
|--------------------------------------------------------------------------
| Harvesting
|--------------------------------------------------------------------------
|
| Where the scraper stores its files (Harvesting domain).
|
| - capture_path: last HTML capture of each source (+ "<key>.images.json").
| - snapshot_path: last capture that was fully processed, to skip unchanged sources.
|
*/

return [

    'capture_path' => storage_path('app/captures'),

    'snapshot_path' => storage_path('app/snapshots'),

];
