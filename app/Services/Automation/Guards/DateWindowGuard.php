<?php

/**
 * ==========================================================
 * MODUL       : DateWindowGuard
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Guard #3 rantai (F-161 B3) — batasi hari/tanggal boleh generate,
 *               mis. hanya hari kerja tertentu atau tanggal 1-25 (tutup buku).
 *               Config KOSONG = LOLOS (tidak ada batasan), bukan ditolak semua.
 * DIPANGGIL   : Pipeline (guard KETIGA, setelah TimeDeltaGuard)
 * MEMANGGIL   : TaskTemplate::date_window_config (json: weekdays[]/dom_min/dom_max)
 * DATA MASUK  : TaskTemplate, now_WIB
 * DATA KELUAR : null (Pass) | Decision::skip('di-luar-jendela-tanggal')
 * RISIKO      : SUMBER SPEK v1.3 §2 -- "Kosong=lolos". Kalau guard ini diam-diam
 *               menolak config null/[], SEMUA template time_based/completion_based
 *               tanpa jendela tanggal (mayoritas) akan macet total.
 * ==========================================================
 */

namespace App\Services\Automation\Guards;

use App\Models\TaskTemplate;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;

class DateWindowGuard implements AutomationGuard
{
    public function check(TaskTemplate $template, AutomationContext $ctx): ?Decision
    {
        $config = $template->date_window_config;

        if (empty($config)) {
            return null; // Pass -- kosong = tak ada batasan
        }

        $now = $ctx->nowWib;

        if (! empty($config['weekdays']) && ! in_array($now->isoWeekday(), $config['weekdays'], true)) {
            return Decision::skip('di-luar-jendela-tanggal');
        }

        if (isset($config['dom_min']) && $now->day < (int) $config['dom_min']) {
            return Decision::skip('di-luar-jendela-tanggal');
        }

        if (isset($config['dom_max']) && $now->day > (int) $config['dom_max']) {
            return Decision::skip('di-luar-jendela-tanggal');
        }

        return null;
    }
}
