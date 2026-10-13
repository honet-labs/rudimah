<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('rudimah:status', function () {
    $this->info('RUDIMAH (Ruang Digital Manajemen Harian) - Operational');
})->purpose('Menampilkan status platform RUDIMAH');

Schedule::command('queue:prune-failed --hours=48')->daily();
