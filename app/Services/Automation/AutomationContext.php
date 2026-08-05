<?php

/**
 * ==========================================================
 * MODUL       : AutomationContext
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Wadah data PRELOAD satu organisasi per run (F-85) — Guard/Strategy/
 *               Resolver membaca dari sini, BUKAN query DB sendiri-sendiri per
 *               template. Tanpa ini, tiap Guard akan N+1 query holidays/schedules/
 *               task-sebelumnya per template.
 * DIPANGGIL   : RunAutomationEngineCommand (dibuat 1x per grup organisasi per chunk),
 *               Pipeline, seluruh Guard, seluruh Strategy, HolidayShiftResolver
 * MEMANGGIL   : Carbon, WorkSchedule, Holiday, Task (data sudah dimuat oleh command)
 * DATA MASUK  : now_WIB (F-69) + hasil query preload command per organisasi
 * DATA KELUAR : dibaca murni (readonly) oleh seluruh komponen pipeline
 * RISIKO      : SUMBER F-85 — kalau field baru butuh data tambahan (mis. Guard baru),
 *               tambah preload DI SINI (via command), JANGAN query ad-hoc di dalam
 *               Guard/Strategy — itu jalan pintas yang mengembalikan N+1.
 * ==========================================================
 */

namespace App\Services\Automation;

use App\Models\Holiday;
use App\Models\Task;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class AutomationContext
{
    /**
     * @param  Collection<int, WorkSchedule>  $schedules  seluruh versi WorkSchedule organisasi ini (F-40/F-66)
     * @param  Collection<int, Holiday>  $holidays  seluruh Holiday organisasi ini (F-43)
     * @param  array<int, Task|null>  $latestTaskByTemplateId  keyed task_template_id -> instance period_key TERBESAR (Opsi B, CompletionBasedStrategy)
     * @param  array<int, int>  $activeInstanceCounts  keyed task_template_id -> jumlah task belum-selesai (QuotaGuard)
     */
    public function __construct(
        public readonly Carbon $nowWib,
        public readonly Collection $schedules,
        public readonly Collection $holidays,
        public readonly array $latestTaskByTemplateId,
        public readonly array $activeInstanceCounts,
    ) {}
}
