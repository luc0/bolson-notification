<?php

use App\Console\Commands\AnalyzeRentals;
use Illuminate\Support\Facades\Schedule;

$analyzeRentals = Schedule::command(AnalyzeRentals::class);

// Debug mode runs every 3 minutes to test the whole flow locally.
$isDebugMode = (bool) config('app.analyze_rentals_debug_mode');
if ($isDebugMode) {
    $analyzeRentals->everyThreeMinutes();

    return;
}

$analyzeRentals->weekdays()->at('17:00');
