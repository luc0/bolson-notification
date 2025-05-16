<?php

use App\Console\Commands\AnalyzeRentals;
use Illuminate\Support\Facades\Schedule;

//Schedule::call(new AnalyzeRentals())->weekdays()
//    ->at('19:00');

Schedule::command( AnalyzeRentals::class)
//    ->weekdays()->at('4:26')
    ->everyThreeMinutes();
//    ->sendOutputTo(storage_path('logs/analyze.log'));
