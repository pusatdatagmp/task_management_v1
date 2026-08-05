<?php

/**
 * ==========================================================
 * MODUL       : TimeBasedStrategy
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Anchor Opsi A (F-161 §8.3) — jalan sesuai jadwal interval, tanpa
 *               syarat tambahan. Guard sebelumnya (TimeDelta/DateWindow/Quota)
 *               sudah cukup; strategy ini selalu meloloskan ke Resolver.
 * DIPANGGIL   : AnchorStrategyRegistry::resolve('time_based')
 * MEMANGGIL   : -
 * DATA MASUK  : TaskTemplate, AutomationContext (tidak dipakai)
 * DATA KELUAR : null (selalu Pass)
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Services\Automation\Strategies;

use App\Models\TaskTemplate;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;

class TimeBasedStrategy implements AnchorStrategy
{
    public function evaluate(TaskTemplate $template, AutomationContext $ctx): ?Decision
    {
        return null;
    }
}
