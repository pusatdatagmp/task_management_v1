<?php

/**
 * ==========================================================
 * MODUL       : TaskUserObserver
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Log assigned/unassigned (F-22) + notifikasi trigger #1/#2 (F-35)
 *               saat pivot task_user dibuat/dihapus lewat
 *               Task::assignees()->attach()/detach()/sync().
 * DIPANGGIL   : Laravel (event Eloquent) via #[ObservedBy] di App\Models\TaskUser
 * MEMANGGIL   : ActivityLog, Task, TaskNotification
 * DATA MASUK  : task_id, user_id dari baris pivot yang dibuat/dihapus
 * DATA KELUAR : activity_logs (subject = Task, bukan pivot-nya sendiri — supaya
 *               riwayat assign muncul di histori task, bukan tabel terpisah),
 *               notifications (assignee baru/lama)
 * RISIKO      : F-36 — pelaku (Auth::id(), selalu admin lewat F-29) TIDAK boleh
 *               dapat notifikasi kalau kebetulan meng-assign dirinya sendiri.
 * ==========================================================
 */

namespace App\Observers;

use App\Models\Task;
use App\Models\TaskUser;
use App\Models\User;
use App\Notifications\TaskNotification;
use App\Observers\Concerns\LogsActivity;
use Illuminate\Support\Facades\Auth;

class TaskUserObserver
{
    use LogsActivity;

    public function created(TaskUser $pivot): void
    {
        $task = Task::find($pivot->task_id);

        if ($task) {
            $this->logActivity($task, 'assigned', null, ['user_id' => $pivot->user_id]);
            $this->notifyUnlessActor($pivot->user_id, $task, TaskNotification::ASSIGNED);
        }
    }

    public function deleted(TaskUser $pivot): void
    {
        $task = Task::find($pivot->task_id);

        if ($task) {
            $this->logActivity($task, 'unassigned', ['user_id' => $pivot->user_id], null);
            $this->notifyUnlessActor($pivot->user_id, $task, TaskNotification::UNASSIGNED);
        }
    }

    private function notifyUnlessActor(int $userId, Task $task, string $type): void
    {
        if ($userId === Auth::id()) {
            return;
        }

        User::find($userId)?->notify(new TaskNotification($task, $type));
    }
}
