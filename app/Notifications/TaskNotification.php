<?php

/**
 * ==========================================================
 * MODUL       : TaskNotification
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : SATU class untuk seluruh 10 trigger notifikasi task (F-35, genap
 *               sejak v0.8 H6 — trigger #9/#10 extension) — dibedakan lewat $type,
 *               bukan 10 class terpisah, karena bentuk datanya identik (task, pesan,
 *               tujuan). Channel database saja (F-6 — Firebase ditunda v3.0).
 * DIPANGGIL   : TaskObserver, TaskUserObserver, NotifyDueSoonCommand, NotifyOverdueCommand,
 *               DeadlineExtensionObserver (trigger #9/#10, v0.8 H6)
 * MEMANGGIL   : Task (baca title/project/due_date untuk susun pesan)
 * DATA MASUK  : Task + tipe trigger + data tambahan (mis. reason untuk REJECTED)
 * DATA KELUAR : notifications.data (JSON) — dibaca NotificationController & bell dropdown
 * RISIKO      : type dipakai NotifyDueSoonCommand/NotifyOverdueCommand untuk guard
 *               idempotency F-80 (query whereJsonContains 'data->type' + 'data->task_id'
 *               + tanggal hari ini). Kalau string type di sini berubah tanpa mengubah
 *               command, guard idempotency diam-diam berhenti bekerja.
 * ==========================================================
 */

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;

class TaskNotification extends Notification
{
    public const ASSIGNED = 'assigned';

    public const UNASSIGNED = 'unassigned';

    public const STATUS_CHANGED = 'status_changed';

    public const ENTERED_REVIEW = 'entered_review';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const DUE_SOON = 'due_soon';

    public const OVERDUE = 'overdue';

    public const EXTENSION_REQUESTED = 'extension_requested';

    public const EXTENSION_DECIDED = 'extension_decided';

    public function __construct(
        public Task $task,
        public string $type,
        public ?string $reason = null,
        // KONTRAK: trigger #10 (EXTENSION_DECIDED) SATU trigger untuk DUA hasil
        // (approve/reject, F-35 "genap 10" — bukan trigger #11 terpisah). Nilai
        // 'approved'|'rejected', dipakai message() untuk membedakan pesan.
        public ?string $extensionOutcome = null,
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
            'type' => $this->type,
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'task_title' => $this->task->title,
            'message' => $this->message(),
            'reason' => $this->reason,
            'extension_outcome' => $this->extensionOutcome,
        ];
    }

    /**
     * KONTRAK: true kalau notifikasi $type untuk $task ini SUDAH terkirim hari ini.
     * DIPAKAI: NotifyDueSoonCommand/NotifyOverdueCommand (F-80) — guard idempotency
     * SEBELUM kirim, dicek per TASK+TIPE+HARI (bukan per penerima), pola sama
     * dengan last_generated_date (F-61). Tanpa ini, cron yang jalan 2x (retry
     * atau dijalankan manual) mengirim notif due/overdue berulang -> inbox
     * banjir -> orang berhenti membaca notifikasi (F-36 sama sekali kehilangan artinya).
     */
    public static function alreadySentToday(Task $task, string $type): bool
    {
        return DatabaseNotification::where('type', self::class)
            ->whereJsonContains('data->task_id', $task->id)
            ->whereJsonContains('data->type', $type)
            ->whereDate('created_at', today())
            ->exists();
    }

    /**
     * BUSINESS RULE: pesan dalam Bahasa Indonesia (§0 CLAUDE.md — UI Bahasa Indonesia).
     * F-44 TIDAK relevan di sini — $type di kelas ini adalah nama TRIGGER notifikasi,
     * bukan nama status task, jadi aman di-switch langsung.
     */
    private function message(): string
    {
        return match ($this->type) {
            self::ASSIGNED => "Kamu di-assign ke task \"{$this->task->title}\".",
            self::UNASSIGNED => "Kamu di-unassign dari task \"{$this->task->title}\".",
            self::STATUS_CHANGED => "Status task \"{$this->task->title}\" berubah.",
            self::ENTERED_REVIEW => "Task \"{$this->task->title}\" masuk status review.",
            self::APPROVED => "Task \"{$this->task->title}\" di-approve.",
            self::REJECTED => "Task \"{$this->task->title}\" ditolak: {$this->reason}",
            self::DUE_SOON => "Task \"{$this->task->title}\" jatuh tempo besok.",
            self::OVERDUE => "Task \"{$this->task->title}\" sudah lewat deadline.",
            self::EXTENSION_REQUESTED => "Ada pengajuan perpanjangan deadline untuk task \"{$this->task->title}\".",
            self::EXTENSION_DECIDED => $this->extensionOutcome === 'approved'
                ? "Pengajuan perpanjangan deadline task \"{$this->task->title}\" DISETUJUI."
                : "Pengajuan perpanjangan deadline task \"{$this->task->title}\" DITOLAK".($this->reason ? ": {$this->reason}" : '.'),
            default => "Ada perubahan pada task \"{$this->task->title}\".",
        };
    }
}
