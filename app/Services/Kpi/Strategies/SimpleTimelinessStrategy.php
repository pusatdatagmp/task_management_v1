<?php

/**
 * ==========================================================
 * MODUL       : SimpleTimelinessStrategy
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Strategy KPI default (F-166, blueprint §14.2, v1.4 KPI-1) — skor
 *               MURNI dari ketepatan-waktu: on-time vs telat, config poin org-level
 *               (admin override lewat Setelan, KPI-2 belum dibangun). Task
 *               tidak-selesai TIDAK relevan di sini — dipanggil HANYA saat approve
 *               (F-28), jadi task yang sampai ke strategy ini SELALU task disetujui.
 * DIPANGGIL   : KpiStrategyRegistry::resolve('simple_timeliness')
 * MEMANGGIL   : Task::isOnTime() (F-47/F-109 — REUSE, bukan penentu on-time kedua),
 *               Task::organization (baca config poin)
 * DATA MASUK  : Task yang sedang di-approve, relasi organization sudah di-load
 *               pemanggil (TaskTransitionService::approve(), cegah lazy-load
 *               violation F-85)
 * DATA KELUAR : int — kpi_points_ontime kalau on-time, kpi_points_late kalau telat
 * RISIKO      : F-167 — nilai config dibaca SAAT approve (dari organization live),
 *               BUKAN snapshot lama. Kalau admin ubah config SETELAH task ini
 *               di-approve, task LAMA TIDAK ikut berubah (kpi_score sudah beku di
 *               kolom, strategy ini tidak pernah dipanggil ulang untuk task lama).
 * ==========================================================
 */

namespace App\Services\Kpi\Strategies;

use App\Models\Task;
use App\Services\Kpi\KpiScoringStrategy;

class SimpleTimelinessStrategy implements KpiScoringStrategy
{
    public function score(Task $task): int
    {
        $organization = $task->organization;

        return $task->isOnTime()
            ? $organization->kpi_points_ontime
            : $organization->kpi_points_late;
    }
}
