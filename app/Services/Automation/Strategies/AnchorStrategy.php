<?php

/**
 * ==========================================================
 * MODUL       : AnchorStrategy
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Kontrak Strategy pattern anchor (F-158/161 §8.3) — TimeBased(A)/
 *               CompletionBased(B)/CalendarAnchored(C) berbagi kontrak yang sama.
 *               Tambah anchor baru = tambah 1 class implement ini + daftar di
 *               AnchorStrategyRegistry, NOL if-else membengkak.
 * DIPANGGIL   : AnchorStrategyGuard (via AnchorStrategyRegistry::resolve())
 * MEMANGGIL   : (implementasi masing-masing) TaskTemplate, AutomationContext
 * DATA MASUK  : TaskTemplate yang dievaluasi + AutomationContext
 * DATA KELUAR : null (Pass, lanjut ke Resolver) ATAU Decision (Skip)
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Services\Automation\Strategies;

use App\Models\TaskTemplate;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;

interface AnchorStrategy
{
    public function evaluate(TaskTemplate $template, AutomationContext $ctx): ?Decision;
}
