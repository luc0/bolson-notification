<?php

use App\Console\Commands\AnalyzeRentals;
use Illuminate\Support\Facades\Schedule;

Schedule::call(new AnalyzeRentals())->weekdays()
    ->at('19:00');
