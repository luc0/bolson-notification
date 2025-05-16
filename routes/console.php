<?php

use App\Console\Commands\AnalyzeRentals;
use Illuminate\Support\Facades\Schedule;

//Schedule::call(new AnalyzeRentals())->weekdays()
//    ->at('19:00');

$analyzeRentalsDebugMode = config('app.analyze_rentals_debug_mode');

if ($analyzeRentalsDebugMode) {
    Schedule::command( AnalyzeRentals::class)
        ->everyThreeMinutes();
//    ->sendOutputTo(storage_path('logs/analyze.log'));
} else {
    Schedule::command( AnalyzeRentals::class)
        ->weekdays()->at('17:00');
}
