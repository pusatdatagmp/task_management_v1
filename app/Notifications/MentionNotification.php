<?php

/**
 * ==========================================================
 * MODUL       : MentionNotification
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Notifikasi @mention di komentar (v1.0 H3, F-114) — kategori
 *               KOLABORASI, SENGAJA class TERPISAH dari TaskNotification (yang
 *               eksplisit "SATU class untuk 10 trigger LIFECYCLE F-35", genap 10
 *               sejak v0.8 H6). Mention bukan trigger lifecycle ke-11 — beda kelas
 *               kejadian sepenuhnya, jadi beda class, BUKAN dipaksa masuk enum yang
 *               sudah ditutup "genap 10".
 * DIPANGGIL   : CommentObserver (created/updated)
 * MEMANGGIL   : Comment, Task (baca title/project untuk susun pesan + link balik)
 * DATA MASUK  : Comment (penulis + body), Task pemilik comment
 * DATA KELUAR : notifications.data (JSON) — BENTUK SAMA dengan TaskNotification
 *               (type/task_id/project_id/message) supaya NotificationController &
 *               notification-bell.tsx (generic, baca key itu apa adanya) TIDAK
 *               PERLU DIUBAH sama sekali untuk kategori baru ini.
 * RISIKO      : type SELALU 'mentioned' — TIDAK dipakai guard idempotency F-80
 *               (itu urusan trigger #4/#5 due/overdue via cron, mention murni
 *               event-driven satu kali per aksi user, tidak ada risiko duplikat cron).
 * ==========================================================
 */

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Notifications\Notification;

class MentionNotification extends Notification
{
    public const MENTIONED = 'mentioned';

    public function __construct(
        public Comment $comment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => self::MENTIONED,
            'task_id' => $this->comment->task_id,
            'project_id' => $this->comment->task->project_id,
            'task_title' => $this->comment->task->title,
            'message' => "{$this->comment->user->name} menyebut kamu di komentar task \"{$this->comment->task->title}\".",
        ];
    }
}
