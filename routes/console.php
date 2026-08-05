<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// AE-3 (F-162 DITUTUP): `tasks:generate-recurring` (engine lama, F-46/F-81) DICABUT
// dari scheduler -- automation:run SATU-SATUNYA generator terjadwal, mencegah
// double-generation dua engine jalan bersamaan (F-162). CODE command lama TIDAK
// dihapus (masih ada di app/Console/Commands/GenerateRecurringTasksCommand.php,
// ditandai @deprecated) -- rollback = pasang ulang baris Schedule::command()
// di sini DAN cabut baris automation:run di bawah, bukan tulis ulang dari nol.
//
// AE-2/AE-3 (F-151/158/162): satu-satunya generator recurring. dailyAt('00:01')
// + ->timezone() eksplisit (F-69) -- scheduler Laravel pakai config('app.timezone')
// default kalau tidak di-set, tapi eksplisit di sini menjaga niat WIB terbaca
// jelas tanpa bergantung config tersembunyi.
Schedule::command('automation:run')->dailyAt('00:01')->timezone('Asia/Jakarta');

// F-35 trigger #4/#5, F-81: cron HARIAN untuk notifikasi (BUKAN scheduler untuk
// counter — F-38 tetap berlaku penuh untuk itu). Windows tidak punya cron asli,
// diuji manual lewat `php artisan schedule:run`; cron sungguhan dipasang saat
// deploy ke server (bukan pekerjaan Hari-6).
Schedule::command('tasks:notify-due-soon')->dailyAt('06:00');
Schedule::command('tasks:notify-overdue')->dailyAt('06:05');
