<?php

use App\Console\Commands\Discord\SendDailyCoffeeMessage;
use App\Console\Commands\Discord\SendDailyWeatherReport;
use App\Console\Commands\LiveStreams\CheckTwitchLiveStatus;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CheckTwitchLiveStatus::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command(SendDailyCoffeeMessage::class)
    ->dailyAt('10:00')
    ->timezone('America/New_York');

Schedule::command(SendDailyWeatherReport::class)
    ->dailyAt('08:00')
    ->timezone('America/New_York');
