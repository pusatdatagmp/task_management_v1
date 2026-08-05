<?php

/**
 * ==========================================================
 * MODUL       : TemplateBlockedNotification
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Notifikasi Anchor Opsi B deadlock (F-154) — instance BARU template
 *               tidak bisa lahir karena instance periode sebelumnya belum SELESAI.
 *               Kategori KOLABORASI (F-114), SENGAJA class TERPISAH dari
 *               TaskNotification (10 trigger LIFECYCLE F-35, genap, TERTUTUP) —
 *               pola identik MentionNotification: kejadian ini bukan salah satu
 *               dari 10 itu, jadi beda class, bukan dipaksa masuk enum yang sudah tutup.
 * DIPANGGIL   : CompletionBasedStrategy (saat blocked_since baru pertama di-set)
 * MEMANGGIL   : TaskTemplate (baca title/project untuk susun pesan + link balik)
 * DATA MASUK  : TaskTemplate yang ter-block
 * DATA KELUAR : notifications.data (JSON) — BENTUK SAMA dengan TaskNotification/
 *               MentionNotification (type/task_id/project_id/message) supaya
 *               NotificationController & notification-bell.tsx (generic) TIDAK
 *               PERLU DIUBAH.
 * RISIKO      : task_id SENGAJA null — kejadian ini level TEMPLATE, bukan task
 *               spesifik (tidak ada satu task tunggal yang "menyebabkan" block,
 *               yang ada adalah SELURUH instance periode sebelumnya yang belum
 *               selesai). project_id tetap diisi supaya bell dropdown bisa
 *               menaut ke halaman project yang relevan.
 * ==========================================================
 */

namespace App\Notifications;

use App\Models\TaskTemplate;
use Illuminate\Notifications\Notification;

class TemplateBlockedNotification extends Notification
{
    public const BLOCKED = 'template_blocked';

    public function __construct(
        public TaskTemplate $template,
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
            'type' => self::BLOCKED,
            'task_id' => null,
            'project_id' => $this->template->project_id,
            'task_title' => $this->template->title,
            'message' => "Template \"{$this->template->title}\" ter-block: instance periode sebelumnya belum selesai.",
        ];
    }
}
