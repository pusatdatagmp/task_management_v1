<?php

/**
 * ==========================================================
 * MODUL       : AnchorStrategyRegistry
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Registry key->class (F-158 "registry, BUKAN if-else membengkak")
 *               — satu-satunya tempat pemetaan task_templates.anchor_strategy
 *               (enum DB) ke instance Strategy konkret.
 * DIPANGGIL   : AnchorStrategyGuard
 * MEMANGGIL   : TimeBasedStrategy, CompletionBasedStrategy, CalendarAnchoredStrategy
 * DATA MASUK  : string key (nilai kolom task_templates.anchor_strategy)
 * DATA KELUAR : instance AnchorStrategy
 * RISIKO      : Key di luar 3 yang terdaftar untuk template is_active=true adalah
 *               data korup (enum DB seharusnya sudah membatasi ini) -- SENGAJA
 *               dibiarkan lempar UnhandledMatchError, ditangkap try/catch
 *               per-template command (F-160) sebagai Decision::error, bukan
 *               ditelan diam-diam jadi salah satu strategy secara tebakan.
 * ==========================================================
 */

namespace App\Services\Automation\Strategies;

class AnchorStrategyRegistry
{
    public function resolve(string $key): AnchorStrategy
    {
        return match ($key) {
            'time_based' => new TimeBasedStrategy,
            'completion_based' => new CompletionBasedStrategy,
            'calendar_anchored' => new CalendarAnchoredStrategy,
        };
    }
}
