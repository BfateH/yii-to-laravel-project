<?php

use Illuminate\Support\Facades\Schedule;

// Обновления курсов валют
Schedule::command('currency:update-rates')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->description('Обновление курсов валют от ЦБ РФ');
