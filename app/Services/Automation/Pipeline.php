<?php

/**
 * ==========================================================
 * MODUL       : Pipeline
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Orkestrator inti Automation Engine (F-158 §8) — jalankan Guard
 *               chain berurutan, kalau semua Pass hitung target_date lewat
 *               Resolver, lalu eksekusi Action. Trigger-agnostic: Pipeline tidak
 *               tahu dipanggil dari Cron/Manual/future EventTrigger.
 * DIPANGGIL   : RunAutomationEngineCommand (1x per template per run)
 * MEMANGGIL   : AutomationGuard[] (rantai), HolidayShiftResolver, GenerateTaskAction
 * DATA MASUK  : TaskTemplate + AutomationContext (data preload F-85 organisasi ini)
 * DATA KELUAR : Decision (SATU per template, siap ditulis ke automation_run_log)
 * RISIKO      : Urutan guard di default() WAJIB Active->TimeDelta->DateWindow->
 *               Quota->Anchor (F-161) -- dibalik bisa mengubah reason yang ter-log
 *               atau (untuk Anchor Opsi B) melewatkan side-effect blocked_since
 *               semestinya tidak pernah dievaluasi (mis. template non-aktif jangan
 *               sampai men-trigger cek completion Opsi B).
 * ==========================================================
 */

namespace App\Services\Automation;

use App\Models\TaskTemplate;
use App\Services\Automation\Actions\GenerateTaskAction;
use App\Services\Automation\Guards\ActiveTemplateGuard;
use App\Services\Automation\Guards\AnchorStrategyGuard;
use App\Services\Automation\Guards\AutomationGuard;
use App\Services\Automation\Guards\DateWindowGuard;
use App\Services\Automation\Guards\QuotaGuard;
use App\Services\Automation\Guards\TimeDeltaGuard;
use App\Services\Automation\Resolvers\HolidayShiftResolver;

class Pipeline
{
    /**
     * @param  AutomationGuard[]  $guards
     */
    public function __construct(
        private readonly array $guards,
        private readonly HolidayShiftResolver $resolver,
        private readonly GenerateTaskAction $action,
    ) {}

    public static function default(): self
    {
        return new self(
            guards: [
                new ActiveTemplateGuard,
                new TimeDeltaGuard,
                new DateWindowGuard,
                new QuotaGuard,
                new AnchorStrategyGuard,
            ],
            resolver: new HolidayShiftResolver,
            action: new GenerateTaskAction,
        );
    }

    public function run(TaskTemplate $template, AutomationContext $ctx): Decision
    {
        // F-151: dihitung SEKALI di sini (bukan diulang tiap Guard) supaya SEMUA
        // Decision (skip/generate/shift/error) punya delta_days konsisten untuk
        // observability, terlepas Guard/Strategy mana yang menghentikan rantai.
        $deltaDays = $template->last_generated_date !== null
            ? (int) $template->last_generated_date->diffInDays($ctx->nowWib)
            : null;

        foreach ($this->guards as $guard) {
            $decision = $guard->check($template, $ctx);

            if ($decision !== null) {
                return $decision->withDeltaDays($deltaDays); // Skip pertama menghentikan rantai (F-158)
            }
        }

        $targetDate = $this->resolver->resolve($ctx->nowWib, $ctx->schedules, $ctx->holidays);

        if ($targetDate === null) {
            return Decision::error('resolver-gagal-cari-hari-kerja')->withDeltaDays($deltaDays);
        }

        return $this->action->execute($template, $targetDate, $ctx->nowWib, $ctx->schedules)->withDeltaDays($deltaDays);
    }
}
