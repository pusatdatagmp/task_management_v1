<?php

/**
 * ==========================================================
 * MODUL       : TimeDeltaGuard
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Guard #2 rantai (F-151/161 B2) — inti self-heal miss-run (F-152).
 *               Template belum "jatuh tempo" -> Skip. Ini PENGGANTI cek "hari ini"
 *               kaku F-100 lama: due-line dihitung dari last_generated_date +
 *               interval, BUKAN tanggal kalender tetap.
 * DIPANGGIL   : Pipeline (guard KEDUA, setelah ActiveTemplateGuard)
 * MEMANGGIL   : TaskTemplate::last_generated_date/interval_value/interval_unit,
 *               AutomationContext::nowWib (F-69 — WIB eksplisit, BUKAN UTC)
 * DATA MASUK  : TaskTemplate, now_WIB
 * DATA KELUAR : null (Pass, sudah jatuh tempo) | Decision::skip('belum-waktunya')
 * RISIKO      : last_generated_date NULL (belum pernah generate sama sekali) WAJIB
 *               Pass langsung -- kalau tidak, template baru tidak akan pernah
 *               generate pertama kalinya. interval_unit di luar day/week/month untuk
 *               template is_active=true adalah data korup -- SENGAJA tidak ditangani
 *               di sini (biar UnhandledMatchError menembus ke try/catch per-template
 *               command, F-160, tercatat sebagai Decision::error, bukan diam-diam
 *               di-skip seolah valid).
 * ==========================================================
 */

namespace App\Services\Automation\Guards;

use App\Models\TaskTemplate;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;

class TimeDeltaGuard implements AutomationGuard
{
    public function check(TaskTemplate $template, AutomationContext $ctx): ?Decision
    {
        if ($template->last_generated_date === null) {
            return null; // Pass -- belum pernah generate, tidak ada due-line untuk dibandingkan
        }

        $dueline = match ($template->interval_unit) {
            'day' => $template->last_generated_date->copy()->addDays($template->interval_value),
            'week' => $template->last_generated_date->copy()->addWeeks($template->interval_value),
            'month' => $template->last_generated_date->copy()->addMonthsNoOverflow($template->interval_value),
        };

        // copy() WAJIB -- nowWib satu instance Carbon dipakai bersama SELURUH
        // template dalam satu run (AutomationContext); startOfDay() memutasi
        // in-place, tanpa copy() akan merusak nowWib untuk template berikutnya.
        if ($ctx->nowWib->copy()->startOfDay()->lessThan($dueline->copy()->startOfDay())) {
            return Decision::skip('belum-waktunya');
        }

        return null;
    }
}
