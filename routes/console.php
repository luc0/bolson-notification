<?php

use App\Console\Commands\AnalyzeLands;
use App\Console\Commands\AnalyzeRentals;
use Illuminate\Support\Facades\Schedule;

$analyzeRentals = Schedule::command(AnalyzeRentals::class);
$analyzeLands = Schedule::command(AnalyzeLands::class);

// Debug mode runs every 3 minutes to test the whole flow locally.
$isDebugMode = (bool) config('app.analyze_rentals_debug_mode');
if ($isDebugMode) {
    $analyzeRentals->everyThreeMinutes();
    $analyzeLands->everyThreeMinutes();

    return;
}

$analyzeRentals->weekdays()->at('17:00');

// Lands change slowly and nobody is notified: once a day, after the rentals run.
$analyzeLands->daily()->at('18:00');
