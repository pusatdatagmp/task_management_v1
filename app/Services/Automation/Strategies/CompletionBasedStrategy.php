<?php

/**
 * ==========================================================
 * MODUL       : CompletionBasedStrategy
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Anchor Opsi B (F-154/161 §8.3) — instance BARU boleh lahir HANYA
 *               kalau instance periode SEBELUMNYA sudah SELESAI. Mencegah backlog
 *               task belum-kelar menumpuk.
 * DIPANGGIL   : AnchorStrategyRegistry::resolve('completion_based')
 * MEMANGGIL   : AutomationContext::latestTaskByTemplateId (preload F-85),
 *               TaskStatus::is_completed (F-44 -- flag, BUKAN nama status hardcode)
 * DATA MASUK  : TaskTemplate, AutomationContext
 * DATA KELUAR : null (Pass, previous selesai/tidak ada previous) |
 *               Decision::skip('sebelumnya-belum-selesai') + SIDE EFFECT:
 *               template.blocked_since di-set (F-154, HANYA kalau belum ter-set)
 * RISIKO      : SUMBER F-159 poin 2 -- notif admin atas blocked_since ADALAH AE-3,
 *               SENGAJA TIDAK dibangun di sini (larangan eksplisit prompt AE-2).
 *               blocked_since di-CLEAR begitu previous selesai supaya siklus
 *               notif berikutnya (AE-3) mulai bersih, bukan mewarisi block lama.
 * ==========================================================
 */

namespace App\Services\Automation\Strategies;

use App\Models\TaskTemplate;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;

class CompletionBasedStrategy implements AnchorStrategy
{
    public function evaluate(TaskTemplate $template, AutomationContext $ctx): ?Decision
    {
        $previousTask = $ctx->latestTaskByTemplateId[$template->id] ?? null;

        if ($previousTask === null) {
            return null; // Pass -- belum pernah ada instance, tidak ada yang ditunggu
        }

        if (! $previousTask->taskStatus->is_completed) {
            if ($template->blocked_since === null) {
                $template->update(['blocked_since' => $ctx->nowWib->toDateString()]);
            }

            return Decision::skip('sebelumnya-belum-selesai', meta: ['previous_task_id' => $previousTask->id]);
        }

        if ($template->blocked_since !== null) {
            $template->update(['blocked_since' => null]);
        }

        return null;
    }
}
