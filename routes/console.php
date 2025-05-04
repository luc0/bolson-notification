<?php

use App\Console\Commands\AnalyzeRentals;
use Illuminate\Support\Facades\Schedule;

//Schedule::call(new AnalyzeRentals())->weekdays()
//    ->at('19:00');

Schedule::command( AnalyzeRentals::class)
//    ->weekdays()->at('4:26')
    ->everyMinute();
//    ->sendOutputTo(storage_path('logs/analyze.log'));
