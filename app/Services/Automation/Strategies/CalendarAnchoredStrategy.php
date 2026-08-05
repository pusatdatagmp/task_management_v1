<?php

/**
 * ==========================================================
 * MODUL       : CalendarAnchoredStrategy
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Anchor Opsi C (F-161 §8.3) — hanya boleh generate pada hari TETAP
 *               (mis. tanggal 1 tiap bulan, atau Senin tiap minggu), bukan interval
 *               bebas seperti Opsi A.
 * DIPANGGIL   : AnchorStrategyRegistry::resolve('calendar_anchored')
 * MEMANGGIL   : TaskTemplate::anchor_config (json: day_of_month|day_of_week)
 * DATA MASUK  : TaskTemplate, AutomationContext::nowWib
 * DATA KELUAR : null (Pass, now_WIB cocok hari tetap) | Decision::skip('bukan-hari-tetap'
 *               / 'anchor-config-kosong')
 * RISIKO      : anchor_config KOSONG untuk strategy ini adalah data tidak lengkap
 *               (beda dari DateWindowGuard yang kosong=lolos) -- di sini kosong
 *               berarti tidak ada "hari tetap" yang bisa dicocokkan, jadi Skip
 *               eksplisit dengan alasan jelas, bukan diam-diam Pass tiap hari.
 * ==========================================================
 */

namespace App\Services\Automation\Strategies;

use App\Models\TaskTemplate;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;

class CalendarAnchoredStrategy implements AnchorStrategy
{
    public function evaluate(TaskTemplate $template, AutomationContext $ctx): ?Decision
    {
        $config = $template->anchor_config ?? [];
        $now = $ctx->nowWib;

        if (! isset($config['day_of_month']) && ! isset($config['day_of_week'])) {
            return Decision::skip('anchor-config-kosong');
        }

        if (isset($config['day_of_month']) && $now->day !== (int) $config['day_of_month']) {
            return Decision::skip('bukan-hari-tetap');
        }

        if (isset($config['day_of_week']) && $now->isoWeekday() !== (int) $config['day_of_week']) {
            return Decision::skip('bukan-hari-tetap');
        }

        return null;
    }
}
