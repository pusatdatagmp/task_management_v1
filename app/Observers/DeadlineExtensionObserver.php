<?php

/**
 * ==========================================================
 * MODUL       : DeadlineExtensionObserver
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Alur approve/reject perpanjangan deadline (F-50). Approve mengubah
 *               Task.original_due_date/due_date/estimated_minutes SECARA OTOMATIS
 *               di sini — bukan dipanggil manual dari controller (F-22). Juga
 *               trigger notifikasi #9 (diajukan -> admin) & #10 (diputuskan ->
 *               pemohon) — genap 10 trigger F-35 (v0.8 H6).
 * DIPANGGIL   : Laravel (event Eloquent) via #[ObservedBy] di App\Models\DeadlineExtension
 * MEMANGGIL   : ActivityLog, Task, TaskNotification, User
 * DATA MASUK  : Perubahan status pending -> approved/rejected
 * DATA KELUAR : activity_logs, tasks.original_due_date/due_date/estimated_minutes,
 *               notifications
 * RISIKO      : SUMBER : F-47 — original_due_date hanya diisi KALAU MASIH NULL.
 *               Extension kedua pada task yang sama tidak boleh menimpa jejak
 *               due_date ASLI yang pertama, atau metrik on-time jadi bohong.
 *               F-36 — pelaku (Auth::id()) TIDAK BOLEH masuk daftar penerima
 *               notifikasi atas aksinya sendiri (relevan kalau admin mengajukan
 *               ATAU admin memutuskan pengajuannya sendiri, matriks BF §6
 *               mengizinkan admin ajukan extension juga).
 *               notifyAdmins() DIDUPLIKASI KECIL dari TaskObserver (bukan
 *               diekstrak ke trait bersama) — scope hari ini cuma extension flow,
 *               TaskObserver tidak disentuh (JANGAN refactor di luar scope).
 * ==========================================================
 */

namespace App\Observers;

use App\Models\DeadlineExtension;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNotification;
use App\Observers\Concerns\LogsActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class DeadlineExtensionObserver
{
    use LogsActivity;

    public function created(DeadlineExtension $extension): void
    {
        $this->logActivity($extension, 'extension_requested', null, $extension->only([
            'task_id', 'requested_due_date', 'additional_minutes', 'reason',
        ]));

        // F-35 trigger #9: diajukan -> ADMIN (bukan assignee/pemohon).
        $extension->loadMissing('task');
        $this->notifyAdmins($extension->task, TaskNotification::EXTENSION_REQUESTED);
    }

    public function updated(DeadlineExtension $extension): void
    {
        if (! $extension->wasChanged('status')) {
            return;
        }

        if ($extension->status === 'approved') {
            $task = $extension->task;

            $task->update([
                'original_due_date' => $task->original_due_date ?? $task->due_date, // F-47
                'due_date' => $extension->requested_due_date,
                'estimated_minutes' => $task->estimated_minutes + $extension->additional_minutes,
            ]);

            $this->logActivity($extension, 'extension_approved', null, ['task_id' => $extension->task_id]);
        }

        if ($extension->status === 'rejected') {
            $this->logActivity($extension, 'extension_rejected', null, ['review_note' => $extension->review_note]);
        }

        // F-35 trigger #10: diputuskan (approve/reject) -> PEMOHON, exclude
        // pelaku sendiri (F-36 — admin bisa mengajukan lalu memutuskan pengajuan
        // orang lain, tapi kalau kebetulan memutuskan pengajuannya sendiri,
        // tidak perlu dinotifikasi atas aksinya sendiri).
        if (in_array($extension->status, ['approved', 'rejected'], true) && $extension->requested_by !== Auth::id()) {
            $extension->loadMissing('task', 'requestedBy');

            Notification::send($extension->requestedBy, new TaskNotification(
                $extension->task,
                TaskNotification::EXTENSION_DECIDED,
                $extension->review_note,
                $extension->status,
            ));
        }
    }

    /**
     * KONTRAK: kirim TaskNotification ke semua admin di organisasi TASK ini,
     * kecuali pelaku (F-36). Duplikasi kecil dari TaskObserver::notifyAdmins()
     * (lihat RISIKO header) — query role_name='admin' stabil karena role SISTEM
     * tidak bisa di-rename lewat UI Role Management (sama alasan TaskObserver).
     */
    private function notifyAdmins(Task $task, string $type): void
    {
        $admins = User::where('organization_id', $task->organization_id)
            ->whereHas('role', fn ($q) => $q->where('is_system', true)->where('role_name', 'admin'))
            ->get()
            ->reject(fn (User $user) => $user->id === Auth::id());

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new TaskNotification($task, $type));
        }
    }
}
