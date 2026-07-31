<?php

/**
 * ==========================================================
 * MODUL       : NotifyDueSoonCommand
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-35 trigger #4 — notifikasi assignee untuk task due BESOK.
 *               Dijadwalkan HARIAN (bukan per menit) — F-81 mengizinkan cron
 *               untuk notifikasi, F-38 tetap melarang scheduler untuk COUNTER.
 * DIPANGGIL   : routes/console.php (Schedule::command), manual (php artisan tasks:notify-due-soon)
 * MEMANGGIL   : Task, TaskNotification
 * DATA MASUK  : tasks.due_date, tasks.task_status_id (join taskStatus.is_completed)
 * DATA KELUAR : notifications (assignee)
 * RISIKO      : F-80 — TANPA guard idempotency, cron yang jalan 2x (retry/manual)
 *               mengirim notif due-besok berulang untuk task yang sama -> inbox
 *               banjir -> F-36 kehilangan artinya. Guard: TaskNotification::alreadySentToday().
 * ==========================================================
 */

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyDueSoonCommand extends Command
{
    protected $signature = 'tasks:notify-due-soon';

    protected $description = 'Kirim notifikasi ke assignee untuk task yang due besok (F-35 trigger #4)';

    /**
     * DEFINISI: DATE(due_date) = besok DAN status BUKAN is_completed (F-44 —
     * dicek lewat flag, bukan nama status).
     */
    public function handle(): int
    {
        $tomorrow = now()->addDay()->toDateString();

        $tasks = Task::query()
            ->whereDate('due_date', $tomorrow)
            ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false))
            ->with('assignees')
            ->get();

        $sent = 0;

        foreach ($tasks as $task) {
            if (TaskNotification::alreadySentToday($task, TaskNotification::DUE_SOON)) {
                continue;
            }

            if ($task->assignees->isNotEmpty()) {
                Notification::send($task->assignees, new TaskNotification($task, TaskNotification::DUE_SOON));
                $sent++;
            }
        }

        $this->info("Diperiksa: {$tasks->count()} task due besok. Dikirim: {$sent}.");

        return self::SUCCESS;
    }
}
