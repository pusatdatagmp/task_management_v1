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
 * DATA KELUAR : null (Pass, now_WIB cocok hari tetap ATAU akhir bulan hasil clamp)
 *               | Decision::skip('bukan-hari-tetap' / 'anchor-config-kosong')
 * RISIKO      : F-164 — day_of_month DIBANDINGKAN terhadap min(day_of_month,
 *               $now->daysInMonth), BUKAN exact match langsung. Tanpa clamp ini,
 *               day_of_month=31 SKIP TOTAL sepanjang bulan yang harinya < 31
 *               (Februari, April, dst) -- perilaku LAMA (E4 sebelum F-164),
 *               diubah SENGAJA atas keputusan Boss supaya "tanggal 31"/"akhir
 *               bulan" tetap generate di hari terakhir bulan pendek (pola sama
 *               F-101 clamp di GenerateRecurringTasksCommand::naturalMonthlyDate()).
 *               anchor_config KOSONG tetap Skip eksplisit (beda dari DateWindowGuard
 *               yang kosong=lolos) -- di sini kosong berarti tidak ada "hari
 *               tetap" yang bisa dicocokkan.
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

        if (isset($config['day_of_month'])) {
            // F-164/F-101: bulan yang tak punya tanggal sebesar config (mis. 31 di
            // Februari) -> target CLAMP ke hari TERAKHIR bulan berjalan, bukan skip.
            $effectiveDay = min((int) $config['day_of_month'], $now->daysInMonth);

            if ($now->day !== $effectiveDay) {
                return Decision::skip('bukan-hari-tetap');
            }
        }

        if (isset($config['day_of_week']) && $now->isoWeekday() !== (int) $config['day_of_week']) {
            return Decision::skip('bukan-hari-tetap');
        }

        return null;
    }
}
