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
 *               F-185: FcmChannel ditambah ke via() KALAU config('services.fcm.
 *               enabled') true -- channel database TETAP hidup, F-185 TAMBAHAN
 *               bukan pengganti (pola SAMA TaskNotification).
 * ==========================================================
 */

namespace App\Notifications;

use App\Models\Comment;
use App\Notifications\Channels\FcmChannel;
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
        $channels = ['database'];

        if (config('services.fcm.enabled')) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
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
            'message' => $this->message(),
        ];
    }

    /**
     * F-185: payload FCM -- body REUSE message() (SATU sumber teks, sama pola
     * TaskNotification::toFcm()).
     *
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => config('app.name'),
            'body' => $this->message(),
            'data' => [
                'type' => self::MENTIONED,
                'task_id' => (string) $this->comment->task_id,
                'project_id' => (string) $this->comment->task->project_id,
            ],
        ];
    }

    private function message(): string
    {
        return "{$this->comment->user->name} menyebut kamu di komentar task \"{$this->comment->task->title}\".";
    }
}
