<?php

/**
 * ==========================================================
 * MODUL       : QuotaGuard
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Guard #4 rantai (F-161 B4) — cegah backlog menumpuk tanpa batas.
 *               max_active_instances NULL = tak terbatas (Pass selalu).
 * DIPANGGIL   : Pipeline (guard KEEMPAT, setelah DateWindowGuard)
 * MEMANGGIL   : AutomationContext::activeInstanceCounts (preload F-85 -- jumlah
 *               task template ini yang taskStatus.is_completed=false, dihitung
 *               SEKALI per organisasi oleh RunAutomationEngineCommand, BUKAN
 *               query per template di sini)
 * DATA MASUK  : TaskTemplate, AutomationContext
 * DATA KELUAR : null (Pass) | Decision::skip('kuota-penuh')
 * RISIKO      : Guard ini TIDAK query DB -- kalau activeInstanceCounts belum
 *               di-preload untuk template_id ini, ?? 0 dianggap kuota kosong
 *               (aman, arah gagal = boleh generate, bukan macet total).
 * ==========================================================
 */

namespace App\Services\Automation\Guards;

use App\Models\TaskTemplate;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;

class QuotaGuard implements AutomationGuard
{
    public function check(TaskTemplate $template, AutomationContext $ctx): ?Decision
    {
        if ($template->max_active_instances === null) {
            return null; // Pass -- tak terbatas
        }

        $activeCount = $ctx->activeInstanceCounts[$template->id] ?? 0;

        if ($activeCount >= $template->max_active_instances) {
            return Decision::skip('kuota-penuh', meta: [
                'active_count' => $activeCount,
                'max_active_instances' => $template->max_active_instances,
            ]);
        }

        return null;
    }
}
