<?php

/**
 * ==========================================================
 * MODUL       : NotifyOverdueCommand
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-35 trigger #5 — notifikasi assignee + admin untuk task yang
 *               SUDAH lewat deadline. Dijadwalkan HARIAN (F-81 — cron notifikasi
 *               sah, F-38 tetap melarang scheduler untuk COUNTER).
 * DIPANGGIL   : routes/console.php (Schedule::command), manual (php artisan tasks:notify-overdue)
 * MEMANGGIL   : Task, User, TaskNotification
 * DATA MASUK  : tasks.due_date, tasks.task_status_id (join taskStatus.is_completed)
 * DATA KELUAR : notifications (assignee + admin)
 * RISIKO      : F-80 — guard idempotency sama seperti NotifyDueSoonCommand. F-60 —
 *               task overdue TIDAK dihapus/diubah otomatis di sini, cuma dinotifikasi;
 *               task tetap overdue selamanya sampai admin/assignee bertindak.
 * ==========================================================
 */

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyOverdueCommand extends Command
{
    protected $signature = 'tasks:notify-overdue';

    protected $description = 'Kirim notifikasi ke assignee + admin untuk task yang lewat deadline (F-35 trigger #5)';

    /**
     * DEFINISI: due_date < now() DAN status BUKAN is_completed (F-44).
     */
    public function handle(): int
    {
        $tasks = Task::query()
            ->where('due_date', '<', now())
            ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false))
            ->with('assignees')
            ->get();

        $sent = 0;

        foreach ($tasks as $task) {
            if (TaskNotification::alreadySentToday($task, TaskNotification::OVERDUE)) {
                continue;
            }

            // SUMBER: F-88/F-90 — "admin" di sini penerima notifikasi, BUKAN cek
            // permission. role_name='admin' aman (bukan hardcode F-44) karena role
            // SISTEM tidak bisa di-rename (Fase E1) — lihat penjelasan lengkap di
            // TaskObserver::notifyAdmins().
            $admins = User::where('organization_id', $task->organization_id)
                ->whereHas('role', fn ($q) => $q->where('is_system', true)->where('role_name', 'admin'))
                ->get();
            $recipients = $task->assignees->concat($admins)->unique('id');

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new TaskNotification($task, TaskNotification::OVERDUE));
                $sent++;
            }
        }

        $this->info("Diperiksa: {$tasks->count()} task overdue. Dikirim: {$sent}.");

        return self::SUCCESS;
    }
}
