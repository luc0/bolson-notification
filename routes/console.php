<?php

use App\Console\Commands\AnalyzeRentals;
use Illuminate\Support\Facades\Schedule;

//Schedule::call(new AnalyzeRentals())->weekdays()
//    ->at('19:00');

$analyzeRentalsDebugMode = env('ANALYZE_RENTALS_DEBUG_MODE', 'true');

if ($analyzeRentalsDebugMode) {
    Schedule::command( AnalyzeRentals::class)
        ->everyThreeMinutes();
//    ->sendOutputTo(storage_path('logs/analyze.log'));
} else {
    Schedule::command( AnalyzeRentals::class)
        ->weekdays()->at('17:00');
}
