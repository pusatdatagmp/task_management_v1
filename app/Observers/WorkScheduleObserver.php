<?php

/**
 * ==========================================================
 * MODUL       : WorkScheduleObserver
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Log created/updated WorkSchedule ke activity_logs (F-51 — celah
 *               yang sudah ada sejak fitur edit/arsip versi FUTURE dibuat
 *               2026-08-10, WorkSchedule sebelumnya NOL Observer). Sumber data
 *               untuk "Log Perubahan" di halaman Pengaturan > Jam Kerja (F-169).
 * DIPANGGIL   : Laravel (event Eloquent) via #[ObservedBy] di App\Models\WorkSchedule
 * MEMANGGIL   : ActivityLog (lewat LogsActivity), WorkSchedule (cari versi sebelumnya)
 * DATA MASUK  : Perubahan WorkSchedule — created (WorkScheduleController::store()/
 *               quickEdit()/activateNow(), F-40 semua lewat INSERT), updated
 *               (update()/archive(), TERBATAS versi FUTURE)
 * DATA KELUAR : activity_logs
 * RISIKO      : created() SENGAJA membandingkan ke versi SEBELUMNYA (bukan cuma
 *               catat nilai baru) supaya "Log Perubahan" di UI bisa tampilkan
 *               delta (mis. "jam 08:00-17:00 -> 08:00-17:30") walau F-40
 *               mewajibkan tiap edit = baris BARU, bukan update baris lama.
 *               Query pencarian versi sebelumnya pakai organization_id EKSPLISIT
 *               (bukan scope otomatis) — pola sama WorkSchedule::active(), supaya
 *               tetap benar dipanggil dari seeder/console tanpa user login.
 * ==========================================================
 */

namespace App\Observers;

use App\Models\WorkSchedule;
use App\Observers\Concerns\LogsActivity;

class WorkScheduleObserver
{
    use LogsActivity;

    public function created(WorkSchedule $workSchedule): void
    {
        $previous = WorkSchedule::where('organization_id', $workSchedule->organization_id)
            ->where('id', '!=', $workSchedule->id)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        $this->logActivity(
            $workSchedule,
            'created',
            $previous?->only(['days_of_week', 'start_time', 'end_time', 'daily_capacity_minutes']),
            $workSchedule->only(['days_of_week', 'start_time', 'end_time', 'daily_capacity_minutes'])
        );
    }

    public function updated(WorkSchedule $workSchedule): void
    {
        $this->logActivity(
            $workSchedule,
            'updated',
            array_intersect_key($workSchedule->getOriginal(), $workSchedule->getChanges()),
            $workSchedule->getChanges()
        );
    }
}
