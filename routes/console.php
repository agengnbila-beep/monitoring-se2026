<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Shared hosting tidak bisa menjalankan worker terus-menerus, jadi cron
 * menjalankan `schedule:run` setiap menit dan worker berhenti saat antrean kosong.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(15);
