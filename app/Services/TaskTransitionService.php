<?php

/**
 * ==========================================================
 * MODUL       : TaskTransitionService
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : SATU-SATUNYA jalur mengubah task_status_id (F-45 transisi berurutan,
 *               F-28 approve/reject admin-only, F-127 gate checklist ->review).
 *               Semua mutasi status task — dropdown, drag board, member submit
 *               kerja, admin approve/reject — WAJIB lewat sini supaya validasi
 *               tidak pernah bisa dilewati oleh jalur lain (F-111).
 * DIPANGGIL   : TaskController::updateStatus()/approve()/reject() — updateStatus()
 *               dipakai BAIK oleh dropdown (TaskStatusCell) MAUPUN drag board (F-111),
 *               keduanya endpoint HTTP yang sama, jadi gate F-127 di sini otomatis
 *               berlaku ke keduanya tanpa kode terpisah.
 * MEMANGGIL   : Task::update() (memicu TaskObserver -> F-21/F-39/F-41/F-51 otomatis),
 *               Task::checklistItems() (F-127 gate)
 * DATA MASUK  : Task, TaskStatus tujuan, User pelaku (dari controller, sudah lolos FormRequest)
 * DATA KELUAR : tasks.task_status_id (+ approved_at/approved_by/quality_rating saat approve)
 * RISIKO      : SUMBER : F-45 — maju cuma boleh position+1, mundur bebas. F-28 — status
 *               is_review CUMA bisa keluar lewat approve()/reject(), generic changeStatus()
 *               MENOLAK task yang sedang is_review supaya quality_rating tidak pernah
 *               terlewat diisi. JANGAN hardcode nama status (F-44) — semua keputusan
 *               di sini pakai flag is_work_state/is_review/is_completed & position.
 *               F-127 — gate checklist DICEK SAAT TRANSISI (bukan retroaktif): task
 *               yang SUDAH di review lalu ditambah item baru TIDAK ditendang mundur
 *               otomatis — gate cuma jalan lagi kalau ada transisi BARU ke review.
 * ==========================================================
 */

namespace App\Services;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TaskTransitionService
{
    /**
     * KONTRAK: transisi status "biasa" — dipakai member submit kerja (TODO->IN_PROGRESS->REVIEW)
     * maupun admin menggeser task di luar konteks review. TIDAK BISA dipakai untuk
     * masuk/keluar status is_review — itu KHUSUS approve()/reject() supaya quality_rating
     * (F-28) dan rejection_count (F-39) tidak pernah bisa dilewati diam-diam.
     */
    public function changeStatus(Task $task, TaskStatus $targetStatus, User $actor): void
    {
        if ($targetStatus->project_id !== $task->project_id) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Status tujuan bukan milik project ini.',
            ]);
        }

        // BUSINESS RULE F-29/E2: bukan soal input salah (bukan 422) — member yang
        // bukan assignee task ini memang TIDAK BOLEH melakukan aksi ini sama sekali,
        // jadi 403 (authorization), bukan validation error.
        // F-90: task.manage (bukan isAdmin()) — perilaku IDENTIK hari ini (admin
        // selalu punya task.manage, member tidak), tapi sekarang role custom
        // dengan task.manage juga bisa ubah status task siapa pun, bukan cuma
        // "yang namanya admin" (D2 — ini bukan perluasan hak, admin TETAP satu-
        // satunya role sistem yang punya permission ini sampai ada yang sengaja
        // memberi role lain permission yang sama lewat UI Role Management).
        abort_unless($actor->can('task.manage') || $task->assignees()->whereKey($actor->id)->exists(), 403, 'Kamu tidak di-assign ke task ini.');

        $currentStatus = $task->taskStatus;

        // BUSINESS RULE F-28: status is_review CUMA bisa ditinggalkan lewat approve()
        // atau reject() (admin-only, mengisi quality_rating/rejection_count). Endpoint
        // generic ini menolak SEMUA transisi selama task masih di status is_review,
        // baik maju maupun mundur.
        if ($currentStatus->is_review) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Task sedang di-review — gunakan approve/reject, bukan ubah status biasa.',
            ]);
        }

        if ($targetStatus->is_completed) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Task hanya bisa masuk status selesai lewat approve admin (F-28).',
            ]);
        }

        // BUSINESS RULE F-45: maju cuma boleh persis position+1, mundur bebas ke
        // position berapa pun yang lebih rendah (revisi/reset).
        if ($targetStatus->position > $currentStatus->position && $targetStatus->position !== $currentStatus->position + 1) {
            throw ValidationException::withMessages([
                'task_status_id' => "Transisi maju hanya boleh ke status berikutnya (F-45) — dari posisi {$currentStatus->position} cuma boleh ke posisi ".($currentStatus->position + 1).'.',
            ]);
        }

        // BUSINESS RULE F-127 (gate-only, RESOLVED): transisi ke status is_review
        // DITOLAK kalau ada item checklist yang belum dicentang. Checklist KOSONG
        // -> LOLOS (bukan setiap task wajib punya item). Ditegakkan DI SINI (F-111)
        // supaya SEMUA jalur (dropdown TaskStatusCell, drag board) otomatis ikut —
        // keduanya lewat changeStatus() ini, nol jalur kedua untuk dilewati.
        if ($targetStatus->is_review && $task->checklistItems()->where('is_done', false)->exists()) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Centang semua checklist dulu sebelum submit ke review (F-127).',
            ]);
        }

        $task->update(['task_status_id' => $targetStatus->id]);
    }

    /**
     * KONTRAK: approve — admin only (F-28). Task WAJIB sedang di status is_review.
     * Pindah ke status is_completed project ini (tepat 1, dijamin F-74), isi
     * approved_at/approved_by/quality_rating DALAM SATU update() supaya TaskObserver
     * (yang cek $task->approved_at setelah save untuk log event 'approved') melihat
     * nilai yang benar, dan actual_minutes ikut FREEZE (F-39) di query yang sama.
     */
    public function approve(Task $task, User $admin, int $qualityRating): void
    {
        $currentStatus = $task->taskStatus;

        if (! $currentStatus->is_review) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Task belum di status review — tidak bisa di-approve.',
            ]);
        }

        $completedStatus = TaskStatus::where('project_id', $task->project_id)
            ->where('is_completed', true)
            ->first();

        if (! $completedStatus) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Project ini tidak punya status selesai (F-19) — hubungi admin lain / cek pengaturan status.',
            ]);
        }

        $task->update([
            'task_status_id' => $completedStatus->id,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'quality_rating' => $qualityRating,
        ]);
    }

    /**
     * KONTRAK: reject — admin only (F-28). Task WAJIB sedang di status is_review.
     * Mundur ke status is_work_state TERDEKAT (position tertinggi yang masih di
     * bawah posisi review) — bukan hardcode nama status (F-44). rejection_count++
     * dan segmen kerja baru dibuka otomatis oleh TaskObserver begitu task_status_id
     * berubah dari is_review ke is_work_state.
     *
     * @param  string  $reason  F-35 trigger #8 — WAJIB diisi admin, dikirim ke
     *                          TaskObserver lewat properti transient (BUKAN kolom
     *                          DB, lihat Task::$rejectionReasonTransient) supaya
     *                          notifikasi "ditolak + alasan" bisa disusun di sana.
     */
    public function reject(Task $task, string $reason): void
    {
        $currentStatus = $task->taskStatus;

        if (! $currentStatus->is_review) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Task belum di status review — tidak bisa ditolak.',
            ]);
        }

        $workStateStatus = TaskStatus::where('project_id', $task->project_id)
            ->where('is_work_state', true)
            ->where('position', '<', $currentStatus->position)
            ->orderByDesc('position')
            ->first();

        if (! $workStateStatus) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Tidak ada status "sedang dikerjakan" di bawah posisi review — tidak bisa menolak task ini.',
            ]);
        }

        $task->rejectionReasonTransient = $reason;
        $task->update(['task_status_id' => $workStateStatus->id]);
    }
}
