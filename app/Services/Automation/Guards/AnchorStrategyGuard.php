<?php

/**
 * ==========================================================
 * MODUL       : AnchorStrategyGuard
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Guard #5 rantai (F-161 B5), guard TERAKHIR — jembatan Guard chain
 *               ke Strategy pattern (§8.3). Delegasi murni, tidak punya logika
 *               anchor sendiri.
 * DIPANGGIL   : Pipeline (guard KELIMA/terakhir, setelah QuotaGuard)
 * MEMANGGIL   : AnchorStrategyRegistry::resolve(), AnchorStrategy::evaluate()
 * DATA MASUK  : TaskTemplate::anchor_strategy (key), AutomationContext
 * DATA KELUAR : null (Pass, lanjut ke HolidayShiftResolver) | Decision (Skip dari Strategy)
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Services\Automation\Guards;

use App\Models\TaskTemplate;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;
use App\Services\Automation\Strategies\AnchorStrategyRegistry;

class AnchorStrategyGuard implements AutomationGuard
{
    public function __construct(private readonly AnchorStrategyRegistry $registry = new AnchorStrategyRegistry) {}

    public function check(TaskTemplate $template, AutomationContext $ctx): ?Decision
    {
        return $this->registry->resolve($template->anchor_strategy)->evaluate($template, $ctx);
    }
}
