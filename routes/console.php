<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// v0.8 H4 (F-46/F-81): generate instance recurring dari task_templates aktif.
// Dijadwalkan LEBIH AWAL dari notify-due-soon/notify-overdue di bawah supaya
// task yang baru lahir hari ini ikut kena cek due-soon/overdue di jam yang sama
// (bukan menunggu sampai besok). F-81: cron HARIAN untuk recurring itu SAH —
// larangan F-38 khusus scheduler untuk COUNTER, bukan ini.
Schedule::command('tasks:generate-recurring')->dailyAt('00:05');

// AE-2 (F-151/158): Automation Engine BARU, BERDAMPINGAN dengan engine lama di
// atas -- cutover (ganti total) baru terjadi di AE-3 (F-160), BUKAN sekarang.
// dailyAt('00:01') + ->timezone() eksplisit (F-69) -- WAJIB, scheduler Laravel
// pakai config('app.timezone') default kalau tidak di-set, tapi eksplisit di
// sini menjaga niat WIB terbaca jelas tanpa bergantung config tersembunyi.
Schedule::command('automation:run')->dailyAt('00:01')->timezone('Asia/Jakarta');

// F-35 trigger #4/#5, F-81: cron HARIAN untuk notifikasi (BUKAN scheduler untuk
// counter — F-38 tetap berlaku penuh untuk itu). Windows tidak punya cron asli,
// diuji manual lewat `php artisan schedule:run`; cron sungguhan dipasang saat
// deploy ke server (bukan pekerjaan Hari-6).
Schedule::command('tasks:notify-due-soon')->dailyAt('06:00');
Schedule::command('tasks:notify-overdue')->dailyAt('06:05');
