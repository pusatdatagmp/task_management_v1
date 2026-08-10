<?php

/**
 * ==========================================================
 * MODUL       : TaskTransitionService
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : SATU-SATUNYA jalur mengubah task_status_id (F-45 transisi berurutan,
 *               F-28 approve/reject admin-only, F-127 gate checklist ->review) DAN
 *               (H7/F-138) satu-satunya jalur buka/tutup task_time_segments lewat
 *               aksi EKSPLISIT Mulai/Hold/Lanjut/Submit. Semua mutasi status task —
 *               dropdown, drag board, member submit kerja, admin approve/reject —
 *               WAJIB lewat sini supaya validasi tidak pernah bisa dilewati jalur lain (F-111).
 * DIPANGGIL   : TaskController::updateStatus()/approve()/reject()/start()/hold()/
 *               resume()/submit() — updateStatus() dipakai BAIK oleh dropdown
 *               (TaskStatusCell) MAUPUN drag board (F-111), keduanya endpoint HTTP
 *               yang sama, jadi gate F-127 di sini otomatis berlaku ke keduanya
 *               tanpa kode terpisah. start()/hold()/resume()/submit() KHUSUS 4
 *               tombol detail task (F-132/F-138), TIDAK dipakai dropdown/drag.
 * MEMANGGIL   : Task::update() (memicu TaskObserver -> F-21/F-39/F-51 otomatis;
 *               F-41 SEKARANG HANYA menutup segmen saat KELUAR work_state, TIDAK
 *               PERNAH membuka — lihat TaskObserver), TaskTimeSegment (start/hold/
 *               resume BUKA/TUTUP segmen eksplisit DI SINI, bukan observer),
 *               Task::checklistItems() (F-127 gate), KpiStrategyRegistry (v1.4
 *               KPI-1, F-166/F-167 — freeze kpi_score DI approve(), lihat KONTRAK method)
 * DATA MASUK  : Task, TaskStatus tujuan, User pelaku (dari controller, sudah lolos FormRequest)
 * DATA KELUAR : tasks.task_status_id (+ approved_at/approved_by/quality_rating/kpi_score
 *               saat approve), task_time_segments.started_at/ended_at (F-138, start/hold/resume)
 * RISIKO      : SUMBER : F-45 — maju cuma boleh position+1, mundur bebas KECUALI dari
 *               is_completed (revisi 2026-08-06 item 3 — task Selesai terkunci permanen,
 *               nol jalan keluar, cegah retroaktif F-39). F-28 — status
 *               is_review CUMA bisa keluar lewat approve()/reject(), generic changeStatus()
 *               MENOLAK task yang sedang is_review supaya quality_rating tidak pernah
 *               terlewat diisi. JANGAN hardcode nama status (F-44) — semua keputusan
 *               di sini pakai flag is_work_state/is_review/is_completed & position.
 *               F-127 — gate checklist DICEK SAAT TRANSISI (bukan retroaktif): task
 *               yang SUDAH di review lalu ditambah item baru TIDAK ditendang mundur
 *               otomatis — gate cuma jalan lagi kalau ada transisi BARU ke review.
 *               F-95 — start/hold/resume/submit SENGAJA TIDAK reuse pengecekan
 *               admin-atau-assignee milik changeStatus(): 4 aksi ini murni personal
 *               "jam kerja SAYA", admin TIDAK BOLEH memicu atas nama assignee lain
 *               (beda dari changeStatus() yang memang boleh dipakai admin menggeser
 *               status task siapa pun via dropdown/drag/board).
 * ==========================================================
 */

namespace App\Services;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTimeSegment;
use App\Models\User;
use App\Services\Kpi\KpiStrategyRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskTransitionService
{
    // F-166: default instance (pola sama AnchorStrategyGuard) — TIDAK butuh
    // container binding khusus, registry ini stateless.
    public function __construct(private readonly KpiStrategyRegistry $kpiRegistry = new KpiStrategyRegistry) {}

    /**
     * KONTRAK: transisi status "biasa" — dipakai member submit kerja (TODO->IN_PROGRESS->REVIEW)
     * maupun admin menggeser task di luar konteks review. TIDAK BISA dipakai untuk
     * masuk/keluar status is_review — itu KHUSUS approve()/reject() supaya quality_rating
     * (F-28) dan rejection_count (F-39) tidak pernah bisa dilewati diam-diam.
     */
    public function changeStatus(Task $task, TaskStatus $targetStatus, User $actor): void
    {
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

        $this->transitionStatus($task, $targetStatus);
    }

    /**
     * KONTRAK (H7/F-138a): "Mulai" — HANYA assignee (F-95, TIDAK ada bypass
     * task.manage, lihat RISIKO header). Task WAJIB masih di status entri (belum
     * is_work_state/is_review/is_completed — "todo"). Pindah ke status is_work_state
     * PERTAMA project ini (by position) LALU buka segmen atas nama $actor sendiri
     * (F-112 — pelaku=pekerja, tidak perlu resolveSegmentWorker() lagi karena
     * aksi ini SELALU personal).
     */
    public function start(Task $task, User $actor): void
    {
        $this->assertAssignee($task, $actor);

        $currentStatus = $task->taskStatus;

        if ($currentStatus->is_work_state || $currentStatus->is_review || $currentStatus->is_completed) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Task sudah pernah dimulai — pakai Lanjut kalau sedang jeda.',
            ]);
        }

        $targetStatus = TaskStatus::where('project_id', $task->project_id)
            ->where('is_work_state', true)
            ->orderBy('position')
            ->first();

        if (! $targetStatus) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Project ini tidak punya status "sedang dikerjakan" (F-19) — hubungi admin.',
            ]);
        }

        DB::transaction(function () use ($task, $actor, $targetStatus) {
            $this->transitionStatus($task, $targetStatus);
            $this->openSegment($task, $actor);
        });
    }

    /**
     * KONTRAK (H7/F-138): "Hold" — tutup segmen MILIK $actor sendiri yang sedang
     * terbuka (jeda). Status TETAP is_work_state (F-138b: jeda = TURUNAN, nol
     * field baru) — hanya segmen yang berubah.
     */
    public function hold(Task $task, User $actor): void
    {
        $this->assertAssignee($task, $actor);

        if (! $task->taskStatus->is_work_state) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Task tidak sedang dikerjakan.',
            ]);
        }

        $openSegment = TaskTimeSegment::where('task_id', $task->id)
            ->where('user_id', $actor->id)
            ->whereNull('ended_at')
            ->first();

        if (! $openSegment) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Tidak ada sesi kerja yang sedang berjalan untuk kamu di task ini.',
            ]);
        }

        $openSegment->update(['ended_at' => now()]);
    }

    /**
     * KONTRAK (H7/F-138): "Lanjut" — buka segmen BARU atas nama $actor. Task WAJIB
     * is_work_state DAN $actor WAJIB sedang jeda (nol segmen terbuka miliknya) —
     * mencegah dobel segmen kalau tombol diklik dua kali / race.
     */
    public function resume(Task $task, User $actor): void
    {
        $this->assertAssignee($task, $actor);

        if (! $task->taskStatus->is_work_state) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Task tidak sedang dikerjakan.',
            ]);
        }

        $hasOpenSegment = TaskTimeSegment::where('task_id', $task->id)
            ->where('user_id', $actor->id)
            ->whereNull('ended_at')
            ->exists();

        if ($hasOpenSegment) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Sesi kerja kamu sudah berjalan, tidak sedang jeda.',
            ]);
        }

        $this->openSegment($task, $actor);
    }

    /**
     * KONTRAK (H7/F-132/F-138): "Submit" — CEK GATE F-127 dulu (checklist belum
     * tuntas -> GAGAL, task_status_id & segmen TIDAK disentuh sama sekali, sebelum
     * transitionStatus() dipanggil). Lolos -> pindah ke status is_review project
     * ini; TaskObserver yang menutup SEMUA segmen terbuka task ini (siapa pun
     * pemiliknya, pola sama sejak F-41 lama — "keluar work_state = tutup segmen"
     * TIDAK diubah H7, lihat TaskObserver header) dan realisasi (F-38) otomatis
     * ikut ter-Σ dari sana, TIDAK dihitung/ditulis manual di sini.
     *
     * BUSINESS RULE (2026-08-07, keputusan Boss): submitted_at dicatat SEKALI
     * SAJA — submit PERTAMA yang jadi patokan telat/tidak di LeaderboardService,
     * BUKAN submit terakhir. Resubmit setelah ditolak (reject() sekarang mundur
     * ke status ENTRY, assignee klik Mulai lagi -> submit() lagi) TIDAK menimpa
     * nilai ini. Di-set SEBELUM transitionStatus() supaya ikut SATU query UPDATE
     * yang sama dengan task_status_id — kalau gate F-127 gagal di bawah, exception
     * dilempar sebelum update() dipanggil sama sekali, jadi attribute yang baru
     * di-set di memori ini juga tidak pernah tersimpan (konsisten "TIDAK disentuh
     * sama sekali" di atas).
     */
    public function submit(Task $task, User $actor): void
    {
        $this->assertAssignee($task, $actor);

        if (! $task->taskStatus->is_work_state) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Task tidak sedang dikerjakan.',
            ]);
        }

        $targetStatus = TaskStatus::where('project_id', $task->project_id)
            ->where('is_review', true)
            ->first();

        if (! $targetStatus) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Project ini tidak punya status "review" (F-19) — hubungi admin.',
            ]);
        }

        if (is_null($task->submitted_at)) {
            $task->submitted_at = now();
        }

        // transitionStatus() sendiri yang menegakkan gate F-127 (target is_review)
        // -- kalau gagal, exception dilempar SEBELUM Task::update() dipanggil sama
        // sekali, jadi status & segmen TIDAK tersentuh (D4).
        $this->transitionStatus($task, $targetStatus);
    }

    /**
     * KONTRAK: buka 1 segmen baru atas nama $user. DIPAKAI start()/resume() —
     * TIDAK ADA guard "tutup dulu segmen dangling" seperti observer lama, karena
     * start()/resume() sendiri sudah menjamin TIDAK ADA segmen terbuka milik
     * $user sebelum dipanggil (dicek di pemanggil masing-masing).
     */
    private function openSegment(Task $task, User $user): void
    {
        TaskTimeSegment::create([
            'organization_id' => $task->organization_id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'started_at' => now(),
        ]);
    }

    /**
     * KONTRAK (F-95): start/hold/resume/submit HANYA assignee task ini, TITIK —
     * lihat RISIKO header kenapa ini TIDAK sama dengan pengecekan changeStatus().
     */
    private function assertAssignee(Task $task, User $actor): void
    {
        abort_unless($task->assignees()->whereKey($actor->id)->exists(), 403, 'Kamu bukan assignee task ini.');
    }

    /**
     * KONTRAK: inti validasi+eksekusi transisi status (F-45/F-28/F-127), DIPISAH
     * dari changeStatus() supaya start()/submit() bisa reuse validasi yang SAMA
     * PERSIS tanpa ikut memaksakan aturan otorisasi admin-atau-assignee milik
     * changeStatus() (lihat RISIKO header — F-95, 4 aksi baru assignee-only murni).
     */
    private function transitionStatus(Task $task, TaskStatus $targetStatus): void
    {
        if ($targetStatus->project_id !== $task->project_id) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Status tujuan bukan milik project ini.',
            ]);
        }

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

        // BUSINESS RULE (revisi 2026-08-06 item 3, semangat F-39): task yang sudah
        // is_completed TERKUNCI PERMANEN — approve() satu-satunya jalan MASUK (F-28),
        // dan TIDAK ADA jalan KELUAR lagi lewat jalur mana pun. F-45 "mundur bebas"
        // sengaja TIDAK berlaku di sini: kalau status boleh mundur dari Selesai,
        // task bisa dibuka lagi lalu di-approve ULANG dengan actual_minutes baru —
        // menulis ulang retroaktif angka KPI yang seharusnya beku selamanya.
        if ($currentStatus->is_completed) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Task sudah Selesai — status terkunci permanen, tidak bisa diubah lagi.',
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
        // supaya SEMUA jalur (dropdown TaskStatusCell, drag board, Submit tombol H7)
        // otomatis ikut — nol jalur kedua untuk dilewati.
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
     * approved_at/approved_by/quality_rating/kpi_score DALAM SATU update() supaya
     * TaskObserver (yang cek $task->approved_at setelah save untuk log event
     * 'approved') melihat nilai yang benar, dan actual_minutes ikut FREEZE (F-39)
     * di query yang sama.
     *
     * BUSINESS RULE (v1.4 KPI-1, F-166/F-167): kpi_score dihitung DI SINI (bukan
     * observer) via strategy aktif organisasi ($organization->kpi_strategy), pakai
     * config poin SAAT INI (kpi_points_ontime/late) — ganti config SETELAH approve
     * TIDAK menulis ulang skor task lama (tak retroaktif, pola F-39). Master toggle
     * kpi_enabled=false -> kpi_score tetap null (fitur "tinggal disable").
     * $task->approved_at DAN $task->actual_minutes di-assign KE ATTRIBUTE dulu
     * (bukan cuma array update()) SEBELUM strategy dipanggil — Task::isOnTime()
     * (F-109, revisi KEDUA 2026-08-10: GABUNGAN due_date DAN actual_minutes vs
     * estimated_minutes) BUTUH KEDUANYA sudah terisi saat strategy membacanya
     * (approved_at utk basis fallback due_date, actual_minutes utk basis
     * estimasi) — TaskObserver::saving() baru menghitung actual_minutes
     * belakangan di update() ini, jadi kalau tidak di-assign duluan, kpi_score
     * bisa beku salah.
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

        $approvedAt = now();
        $task->approved_at = $approvedAt;

        // BUSINESS RULE (2026-08-10, revisi isOnTime()): actual_minutes WAJIB
        // di-assign KE ATTRIBUTE di sini, SEBELUM strategy KPI dipanggil --
        // pola SAMA approved_at di atas. TaskObserver::saving() (F-39) baru
        // menghitung actual_minutes belakangan, DI DALAM update() yang sama di
        // bawah -- kalau tidak di-assign duluan, SimpleTimelinessStrategy akan
        // baca actual_minutes MASIH NULL saat isOnTime() dipanggil, membekukan
        // kpi_score yang salah (F-167 pelanggaran: skor beku, tak bisa dihitung
        // ulang). Guard is_null() cegah recompute kalau (secara teori) sudah
        // pernah terisi -- lihat TaskObserver::saving() untuk guard yang sama.
        if (is_null($task->actual_minutes)) {
            $task->actual_minutes = $task->calculateActualMinutes();
        }

        // F-85: preload eksplisit -- Model::preventLazyLoading() aktif di non-produksi,
        // SimpleTimelinessStrategy baca $task->organization->kpi_* langsung.
        $task->loadMissing('organization');

        $kpiScore = $task->organization->kpi_enabled
            ? $this->kpiRegistry->resolve($task->organization->kpi_strategy)->score($task)
            : null;

        $task->update([
            'task_status_id' => $completedStatus->id,
            'approved_at' => $approvedAt,
            'approved_by' => $admin->id,
            'quality_rating' => $qualityRating,
            'actual_minutes' => $task->actual_minutes,
            'kpi_score' => $kpiScore,
        ]);
    }

    /**
     * KONTRAK: reject — admin only (F-28). Task WAJIB sedang di status is_review.
     * BUSINESS RULE (2026-08-07, keputusan Boss — GANTI perilaku lama): mundur ke
     * status ENTRY project ini (is_work_state=false, is_review=false,
     * is_completed=false, posisi terendah) — BUKAN status is_work_state terdekat
     * seperti sebelumnya. Assignee WAJIB klik "Mulai" lagi (bukan "Lanjut") untuk
     * lanjut kerja — computeWorkState() otomatis balik 'todo' begitu flag semua
     * false, tombol yang tampil ikut berubah sendiri (task-work-actions.tsx),
     * NOL logic baru di frontend. Flag-based (F-44), bukan hardcode nama "TODO".
     * rejection_count++ ditangani TaskObserver (kondisinya ikut disesuaikan —
     * lihat TaskObserver header). Segmen TIDAK dibuka otomatis di sini maupun
     * observer (H7/F-138 — tidak berubah oleh revisi ini) — assignee buka segmen
     * baru sendiri lewat start().
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

        $entryStatus = TaskStatus::where('project_id', $task->project_id)
            ->where('is_work_state', false)
            ->where('is_review', false)
            ->where('is_completed', false)
            ->orderBy('position')
            ->first();

        if (! $entryStatus) {
            throw ValidationException::withMessages([
                'task_status_id' => 'Project ini tidak punya status entri (F-19) — tidak bisa menolak task ini.',
            ]);
        }

        $task->rejectionReasonTransient = $reason;
        $task->update(['task_status_id' => $entryStatus->id]);
    }
}
