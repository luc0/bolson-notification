<?php

use App\Console\Commands\AnalyzeLands;
use App\Console\Commands\AnalyzeRentals;
use App\Console\Commands\SendExpiryReminders;
use Illuminate\Support\Facades\Schedule;

// Once a day, at a reasonable hour to get a message.
Schedule::command(SendExpiryReminders::class)->dailyAt('10:00');

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

// Lands change slowly and nobody is notified: once a week, after the rentals run.
$analyzeLands->weeklyOn(1, '18:00');
