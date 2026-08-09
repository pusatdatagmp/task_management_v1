<?php

/**
 * ==========================================================
 * MODUL       : KpiScoringStrategy
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Kontrak Strategy pattern skor KPI (F-166, pola sama Automation
 *               Engine F-158) — SimpleTimelinessStrategy sekarang, WeightedStrategy
 *               dkk nanti berbagi kontrak yang sama. Tambah strategy baru = tambah
 *               1 class implement ini + daftar di KpiStrategyRegistry, NOL if-else
 *               membengkak di TaskTransitionService::approve().
 * DIPANGGIL   : TaskTransitionService::approve() (via KpiStrategyRegistry::resolve())
 * MEMANGGIL   : (implementasi masing-masing) Task
 * DATA MASUK  : Task yang SEDANG di-approve (organization sudah di-load, lihat
 *               TaskTransitionService)
 * DATA KELUAR : int skor — DIBEKUKAN ke tasks.kpi_score oleh pemanggil (F-167),
 *               TIDAK PERNAH ditulis strategy sendiri
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Services\Kpi;

use App\Models\Task;

interface KpiScoringStrategy
{
    public function score(Task $task): int;
}
