<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Both times below are in the application timezone, which is UTC — the
// scheduler reads them the same way everything else is stored. 23:15 UTC is
// therefore 01:15 the next morning in Germany, which is if anything the
// quieter hour for a dump.
Schedule::command('app:backup')
    ->dailyAt('23:15');

// Only ever has anything to do while TELESCOPE_RECORD_EVERYTHING is on, but
// then the entries pile up fast, so keep no more than the last two days.
Schedule::command('telescope:prune --hours=48')
    ->daily();

// 05:00 UTC is early morning in Germany, so the digest is waiting rather
// than arriving during the day.
Schedule::command('app:mail-errors')
    ->dailyAt('05:00');
