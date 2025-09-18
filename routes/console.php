<?php

use Illuminate\Support\Facades\Schedule;

// Обновления курсов валют
Schedule::command('currency:update-rates')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->description('Обновление курсов валют от ЦБ РФ');

// Регулярный опрос посылок через API Почты России
Schedule::command('tracking:poll-shipments')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Опрос посылок для обновления статусов и событий отслеживания');
