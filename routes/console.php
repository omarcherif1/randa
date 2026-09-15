<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Section 3.2 / étape 10 — sauvegarde automatique quotidienne.
Schedule::command('randa:backup --label=auto')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();
