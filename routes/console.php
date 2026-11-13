<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('airs:sync')->everyMinute()->withoutOverlapping();
Schedule::command('amira:prune-guidance')->daily()->withoutOverlapping();
Schedule::command('amira:prune')->dailyAt('03:00')->withoutOverlapping();
