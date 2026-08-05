<?php

/**
 * ==========================================================
 * MODUL       : CompletionBasedStrategy
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Anchor Opsi B (F-154/161 §8.3) — instance BARU boleh lahir HANYA
 *               kalau instance periode SEBELUMNYA sudah SELESAI. Mencegah backlog
 *               task belum-kelar menumpuk. AE-3: notif admin SEKALI saat deadlock
 *               terdeteksi (F-154), kategori kolaborasi (F-114).
 * DIPANGGIL   : AnchorStrategyRegistry::resolve('completion_based')
 * MEMANGGIL   : AutomationContext::latestTaskByTemplateId (preload F-85),
 *               TaskStatus::is_completed (F-44 -- flag, BUKAN nama status hardcode),
 *               TemplateBlockedNotification, User (query admin, pola sama
 *               TaskObserver/DeadlineExtensionObserver::notifyAdmins())
 * DATA MASUK  : TaskTemplate, AutomationContext
 * DATA KELUAR : null (Pass, previous selesai/tidak ada previous) |
 *               Decision::skip('sebelumnya-belum-selesai') + SIDE EFFECT:
 *               template.blocked_since + last_block_notified_at di-set (F-154,
 *               HANYA transisi PERTAMA null->terisi) + notifications (admin)
 * RISIKO      : SUMBER F-159 poin 2 -- anti-spam: notif dikirim HANYA saat
 *               blocked_since BERUBAH dari null (transisi PERTAMA kali block),
 *               ditandai bersamaan dengan last_block_notified_at. Run berikutnya
 *               selama masih block, blocked_since SUDAH terisi -> guard null-check
 *               ini sendiri yang mencegah kirim ulang (F-154 "sekali", bukan cron
 *               mengetuk tiap hari). blocked_since & last_block_notified_at
 *               di-CLEAR BERSAMAAN begitu previous selesai, supaya siklus block
 *               berikutnya (kalau terjadi lagi nanti) mulai bersih dan notif
 *               terkirim lagi (bukan dianggap "sudah pernah notif" selamanya).
 * ==========================================================
 */

namespace App\Services\Automation\Strategies;

use App\Models\TaskTemplate;
use App\Models\User;
use App\Notifications\TemplateBlockedNotification;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;
use Illuminate\Support\Facades\Notification;

class CompletionBasedStrategy implements AnchorStrategy
{
    public function evaluate(TaskTemplate $template, AutomationContext $ctx): ?Decision
    {
        $previousTask = $ctx->latestTaskByTemplateId[$template->id] ?? null;

        if ($previousTask === null) {
            return null; // Pass -- belum pernah ada instance, tidak ada yang ditunggu
        }

        if (! $previousTask->taskStatus->is_completed) {
            // F-154: transisi PERTAMA null->terisi -- notif SEKALI di sini.
            // Run berikutnya blocked_since SUDAH terisi, blok if ini tidak
            // dimasuki lagi -> anti-spam TANPA perlu cek terpisah last_block_notified_at
            // (kolom itu tetap dicatat untuk audit "kapan dinotif", bergerak
            // BERSAMA blocked_since, bukan guard independen).
            if ($template->blocked_since === null) {
                $template->update([
                    'blocked_since' => $ctx->nowWib->toDateString(),
                    'last_block_notified_at' => $ctx->nowWib,
                ]);

                $this->notifyAdmins($template);
            }

            return Decision::skip('sebelumnya-belum-selesai', meta: ['previous_task_id' => $previousTask->id]);
        }

        if ($template->blocked_since !== null) {
            $template->update(['blocked_since' => null, 'last_block_notified_at' => null]);
        }

        return null;
    }

    /**
     * KONTRAK: kirim TemplateBlockedNotification ke semua admin organisasi
     * template ini. Duplikasi KECIL dari TaskObserver/DeadlineExtensionObserver
     * ::notifyAdmins() (role SISTEM stabil, tidak bisa di-rename lewat UI Role
     * Management) -- SENGAJA tidak diekstrak ke trait bersama, pola yang sama
     * sudah dipilih 2x sebelumnya di codebase ini (JANGAN refactor di luar scope).
     */
    private function notifyAdmins(TaskTemplate $template): void
    {
        $admins = User::where('organization_id', $template->organization_id)
            ->whereHas('role', fn ($q) => $q->where('is_system', true)->where('role_name', 'admin'))
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new TemplateBlockedNotification($template));
        }
    }
}
