<?php

/**
 * ==========================================================
 * MODUL       : TaskObserver
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Tulang punggung alur task (F-22, F-51). Menangani F-21 (completed_at),
 *               F-41 (TUTUP task_time_segments saat keluar work_state — H7/F-138:
 *               BUKA segmen DIPINDAH ke TaskTransitionService::start()/resume(),
 *               observer ini TIDAK PERNAH membuka segmen lagi), rejection_count++
 *               saat ditolak, F-39 (freeze actual_minutes saat approve), F-79
 *               (description_plain untuk FULLTEXT search), dan trigger notifikasi
 *               #3/#6/#7/#8 (F-35) — SEMUA otomatis dari perubahan atribut Task,
 *               BUKAN dipanggil manual di controller.
 * DIPANGGIL   : Laravel (event Eloquent) via #[ObservedBy] di App\Models\Task
 * MEMANGGIL   : ActivityLog, TaskStatus, TaskTimeSegment, TaskNotification
 * DATA MASUK  : Perubahan atribut Task (khususnya task_status_id, description)
 * DATA KELUAR : activity_logs, task_time_segments (TUTUP saja), tasks.completed_at/
 *               actual_minutes/rejection_count/description_plain, notifications
 * RISIKO      : SUMBER : 03-BUSINESS-FLOW §1/§2 — logika di sini TIDAK BOLEH cek nama
 *               status (F-44), hanya flag is_work_state/is_review/is_completed.
 *               Freeze actual_minutes (F-39) sekarang pakai Task::calculateActualMinutes()
 *               (F-57 — cap jendela kerja via BusinessHoursCalculator), BUKAN jumlah
 *               mentah. Kalau logika F-57 salah, angka yang dibekukan di sini SALAH
 *               PERMANEN — tidak bisa dihitung ulang selamanya.
 *               F-79 — description_plain WAJIB ikut ter-update setiap description
 *               berubah, kalau tidak FULLTEXT search mengindeks teks basi.
 *               F-36 — pelaku (Auth::id()) TIDAK BOLEH masuk daftar penerima
 *               notifikasi atas aksinya sendiri, di SEMUA trigger di file ini.
 *               F-84 — trigger #3 (generik) DIAM kalau transisi sudah ditangkap
 *               #6/#7/#8 (lebih spesifik), supaya 1 aksi tidak pernah kirim 2 notif.
 *               H7/F-138 — `resolveSegmentWorker()` (C3, v1.0 H2) DIHAPUS TOTAL:
 *               dead code sejak buka-segmen-otomatis dicabut, TIDAK ADA pemanggil
 *               tersisa. Disambiguasi pelaku-vs-assignee yang dulu dilakukannya
 *               kini tidak perlu lagi — start()/resume() SELALU personal (F-95,
 *               assignee yang klik = assignee yang dicatat, nol ambiguitas).
 * ==========================================================
 */

namespace App\Observers;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTimeSegment;
use App\Models\User;
use App\Notifications\TaskNotification;
use App\Observers\Concerns\LogsActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class TaskObserver
{
    use LogsActivity;

    public function created(Task $task): void
    {
        $this->logActivity($task, 'created', null, $task->only([
            'title', 'task_type', 'task_status_id', 'due_date', 'points', 'estimated_minutes',
        ]));
    }

    /**
     * BUSINESS RULE: F-79 — description_plain adalah SATU-SATUNYA sumber yang
     * dipakai FULLTEXT search (lihat migration move_fulltext_index_to_description_plain).
     * DIJALANKAN SEBELUM save (creating & updating) supaya kolom ini selalu ikut
     * INSERT/UPDATE yang sama dengan description, tidak pernah tertinggal basi.
     *
     * URUTAN transformasi wajib begini, tidak boleh dibalik:
     * 1. strip_tags   -> buang tag HTML ("<p>Kerjakan <strong>laporan</strong></p>" -> "Kerjakan laporan")
     * 2. html_entity_decode -> tanpa ini &nbsp;/&amp; dkk jadi sampah literal di hasil pencarian
     * 3. normalisasi spasi -> tag yang dibuang meninggalkan spasi ganda/baris kosong
     * 4. trim
     */
    public function saving(Task $task): void
    {
        if (! $task->isDirty('description')) {
            return;
        }

        $task->description_plain = $task->description === null
            ? null
            : trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($task->description), ENT_QUOTES | ENT_HTML5)));
    }

    /**
     * BUSINESS RULE: dijalankan SEBELUM save supaya completed_at/rejection_count/
     * actual_minutes ikut satu query UPDATE yang sama dengan task_status_id (F-21,F-39,F-41).
     */
    public function updating(Task $task): void
    {
        if (! $task->isDirty('task_status_id')) {
            return;
        }

        $oldStatus = TaskStatus::find($task->getOriginal('task_status_id'));
        $newStatus = TaskStatus::find($task->task_status_id);

        // F-21: completed_at diisi saat MASUK is_completed, dikosongkan saat KELUAR.
        if ($newStatus?->is_completed && ! $oldStatus?->is_completed) {
            $task->completed_at = now();
        } elseif (! $newStatus?->is_completed && $oldStatus?->is_completed) {
            $task->completed_at = null;
        }

        // F-41: REVIEW -> work_state (mundur) = admin menolak.
        if ($oldStatus?->is_review && $newStatus?->is_work_state) {
            $task->rejection_count++;
        }

        // F-39: freeze actual_minutes SEKALI saja saat pertama kali masuk is_completed.
        // Kalau sudah pernah frozen (tidak null), JANGAN dihitung ulang — angka
        // penilaian tidak boleh berubah retroaktif walau task diedit lagi.
        if ($newStatus?->is_completed && is_null($task->actual_minutes)) {
            $task->actual_minutes = $task->calculateActualMinutes();
        }
    }

    public function updated(Task $task): void
    {
        $this->logActivity($task, 'updated', array_intersect_key($task->getOriginal(), $task->getChanges()), $task->getChanges());

        if (! $task->wasChanged('task_status_id')) {
            return;
        }

        $oldStatusId = $task->getOriginal('task_status_id');
        $oldStatus = TaskStatus::find($oldStatusId);
        $newStatus = TaskStatus::find($task->task_status_id);

        $this->logActivity($task, 'status_changed', ['task_status_id' => $oldStatusId], ['task_status_id' => $task->task_status_id]);

        // BUSINESS RULE: F-84 — keputusan Boss opsi (b). Trigger #3 (generik) DIAM
        // kalau transisi ini SUDAH ditangkap #6/#7/#8 (lebih spesifik) — sebelum ini,
        // approve/reject mengirim 2 notif untuk 1 aksi (#3 + #7, atau #3 + #8),
        // menabrak F-36 (kegagalan yang sama persis coba dicegah F-36: inbox banjir
        // bikin orang berhenti membaca, termasuk sinyal "lewat deadline" yang KPI
        // paling dasar). Flag saja (F-44), BUKAN nama status:
        //  - #6 : masuk is_review
        //  - #8 : is_review -> is_work_state (ditolak)
        //  - #7 : masuk is_completed DENGAN approved_at terisi (approve) — approved_at
        //         sudah di-set TaskTransitionService::approve() SEBELUM update() ini,
        //         jadi sudah terbaca di titik ini, tidak perlu tunggu blok di bawah.
        $capturedByMoreSpecificTrigger =
            ($newStatus?->is_review && ! $oldStatus?->is_review)
            || ($oldStatus?->is_review && $newStatus?->is_work_state)
            || ($newStatus?->is_completed && ! $oldStatus?->is_completed && $task->approved_at);

        if (! $capturedByMoreSpecificTrigger) {
            $this->notifyAssignees($task, TaskNotification::STATUS_CHANGED);
        }

        // H7/F-138a/c/d: masuk work_state TIDAK LAGI membuka segmen di sini --
        // BLOK LAMA DIHAPUS (dulu buka otomatis lewat resolveSegmentWorker()).
        // Segmen SEKARANG HANYA terbuka lewat aksi eksplisit Mulai/Lanjut
        // (TaskTransitionService::start()/resume()), jadi drag ke kolom dikerjakan
        // (F-138c) DAN reject admin (F-138d, mundur review->work_state) sekarang
        // status SAJA, nol efek segmen — task mendarat di JEDA (F-138b, turunan
        // dari nol segmen terbuka), assignee klik Lanjut sendiri.

        // F-41: keluar work_state (mis. submit ke REVIEW) -> tutup segmen berjalan.
        // TIDAK BERUBAH oleh H7 -- berlaku SEMUA jalur keluar work_state (dropdown,
        // drag ke review, TaskTransitionService::submit()), SATU tempat menutup.
        if (! $newStatus?->is_work_state && $oldStatus?->is_work_state) {
            TaskTimeSegment::where('task_id', $task->id)->whereNull('ended_at')->update(['ended_at' => now()]);
        }

        // F-35 trigger #6: masuk status review -> ADMIN (bukan assignee).
        if ($newStatus?->is_review && ! $oldStatus?->is_review) {
            $this->notifyAdmins($task, TaskNotification::ENTERED_REVIEW);
        }

        if ($oldStatus?->is_review && $newStatus?->is_work_state) {
            $this->logActivity($task, 'rejected', null, ['rejection_count' => $task->rejection_count]);

            // F-35 trigger #8: ditolak + alasan -> assignee. Alasan datang dari
            // properti transient (lihat Task::$rejectionReasonTransient), diisi
            // TaskTransitionService::reject() SEBELUM update() dipanggil.
            $this->notifyAssignees($task, TaskNotification::REJECTED, $task->rejectionReasonTransient);
        }

        if ($newStatus?->is_completed && ! $oldStatus?->is_completed) {
            $this->logActivity($task, 'completed', null, ['completed_at' => $task->completed_at]);

            // BUSINESS RULE: F-53 — realisasi > 3x estimasi hanya DITANDAI untuk
            // ditinjau admin, BUKAN diblokir/dihukum otomatis. Ini rem terhadap
            // Goodhart's Law (F-4): sistem mendeteksi anomali tanpa memutuskan
            // sendiri itu kesalahan siapa.
            if ($task->actual_minutes !== null && $task->estimated_minutes > 0
                && $task->actual_minutes > $task->estimated_minutes * 3) {
                $this->logActivity($task, 'anomaly_flagged', null, [
                    'actual_minutes' => $task->actual_minutes,
                    'estimated_minutes' => $task->estimated_minutes,
                ]);
            }

            if ($task->approved_at) {
                $this->logActivity($task, 'approved', null, ['approved_by' => $task->approved_by, 'quality_rating' => $task->quality_rating]);

                // F-35 trigger #7: di-approve -> assignee.
                $this->notifyAssignees($task, TaskNotification::APPROVED);
            }
        }
    }

    public function deleted(Task $task): void
    {
        $this->logActivity($task, 'deleted', null, null);
    }

    /**
     * KONTRAK: kirim TaskNotification ke semua assignee task ini KECUALI pelaku
     * (F-36). DIPAKAI: trigger #3/#7/#8. reject() (Collection) dipakai, BUKAN
     * whereKeyNot(Auth::id()), karena Auth::id() bisa null (console/queue) —
     * whereKeyNot(null) menghasilkan `WHERE id != NULL` yang salah secara SQL
     * (selalu UNKNOWN, diam-diam mengecualikan SEMUA baris).
     */
    private function notifyAssignees(Task $task, string $type, ?string $reason = null): void
    {
        $recipients = $task->assignees()->get()->reject(fn (User $user) => $user->id === Auth::id());

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TaskNotification($task, $type, $reason));
        }
    }

    /**
     * KONTRAK: kirim TaskNotification ke semua admin DI ORGANISASI YANG SAMA
     * dengan task ini, kecuali pelaku (F-36). DIPAKAI: trigger #6.
     *
     * SUMBER: F-88/F-90 — "admin" di sini BUKAN cek permission (RBAC menjawab
     * "boleh apa", bukan "siapa penerima notifikasi"), jadi TETAP query
     * role_name='admin' — bukan hardcode nama role sembarangan (F-44) karena
     * role SISTEM (is_system=true) tidak bisa di-rename lewat UI Role
     * Management (Fase E1), jadi kriteria ini stabil selamanya. Role CUSTOM
     * (mis. "Supervisor") sengaja TIDAK ikut ke sini walau punya task.approve —
     * perilaku identik enum lama, bukan perluasan diam-diam (D2).
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
