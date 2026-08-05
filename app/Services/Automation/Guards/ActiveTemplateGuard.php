<?php

/**
 * ==========================================================
 * MODUL       : ActiveTemplateGuard
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Guard #1 rantai (F-161 B1) — template non-aktif tidak boleh generate.
 * DIPANGGIL   : Pipeline (guard PERTAMA dalam rantai)
 * MEMANGGIL   : TaskTemplate::is_active
 * DATA MASUK  : TaskTemplate
 * DATA KELUAR : null (Pass) | Decision::skip (template tidak aktif)
 * RISIKO      : RunAutomationEngineCommand SUDAH memfilter WHERE is_active=true
 *               saat fetch (F1), jadi guard ini SELALU Pass di produksi — tetap
 *               dipertahankan sebagai unit TERPISAH (F-158) supaya bisa diuji
 *               sendiri lepas dari command, dan supaya trigger lain (mis. future
 *               EventTrigger §8.1) yang tidak pre-filter tetap aman.
 * ==========================================================
 */

namespace App\Services\Automation\Guards;

use App\Models\TaskTemplate;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;

class ActiveTemplateGuard implements AutomationGuard
{
    public function check(TaskTemplate $template, AutomationContext $ctx): ?Decision
    {
        return $template->is_active ? null : Decision::skip('template-tidak-aktif');
    }
}
