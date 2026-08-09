<?php

/**
 * ==========================================================
 * MODUL       : KpiStrategyRegistry
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Registry key->class (F-166, pola sama AnchorStrategyRegistry
 *               F-158) — satu-satunya tempat pemetaan organizations.kpi_strategy
 *               (kolom config, F-166) ke instance Strategy konkret.
 * DIPANGGIL   : TaskTransitionService::approve()
 * MEMANGGIL   : SimpleTimelinessStrategy
 * DATA MASUK  : string key (nilai kolom organizations.kpi_strategy)
 * DATA KELUAR : instance KpiScoringStrategy
 * RISIKO      : Key di luar yang terdaftar adalah data korup/typo config admin —
 *               SENGAJA dibiarkan lempar UnhandledMatchError (pola F-160), bukan
 *               ditelan diam-diam jadi salah satu strategy secara tebakan —
 *               approve() akan gagal jelas, bukan membekukan skor yang salah.
 * ==========================================================
 */

namespace App\Services\Kpi;

use App\Services\Kpi\Strategies\SimpleTimelinessStrategy;

class KpiStrategyRegistry
{
    public function resolve(string $key): KpiScoringStrategy
    {
        return match ($key) {
            'simple_timeliness' => new SimpleTimelinessStrategy,
        };
    }
}
