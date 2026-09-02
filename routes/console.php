<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Mostrar una frase inspiradora');

Schedule::command('instagram:generate-publications')->everyMinute()->withoutOverlapping();
Schedule::command('instagram:fail-stale-publications')->everyFiveMinutes()->withoutOverlapping();
