<?php

/**
 * ==========================================================
 * MODUL       : DeadlineExtensionController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Alur perpanjangan deadline (F-50) — ajukan (assignee/admin, F-95),
 *               approve/reject (admin, F-28-setara). Logika F-47 (original_due_date)
 *               & pemutakhiran task SUDAH ada di DeadlineExtensionObserver (Hari-1) —
 *               controller ini HANYA mengubah status, observer yang bereaksi.
 * DIPANGGIL   : routes/web.php (store, myExtensions — mixed access),
 *               routes/admin.php (index, approve, reject — can:task.approve)
 * MEMANGGIL   : DeadlineExtension, Task, Attachment::storeUploadedFile() (evidence, H5)
 * DATA MASUK  : Form ajukan (extensions/my-extensions.tsx), form approve/reject
 *               (extensions/index.tsx) — task_id di BODY (bukan URL, halaman flat
 *               lintas project seperti my-tasks), extension dari route model binding
 * DATA KELUAR : Baris deadline_extensions, attachments (evidence), activity_logs,
 *               notifications trigger #9/#10 (semua via observer, bukan di sini)
 * RISIKO      : SUMBER : approve()/reject() menolak kalau extension SUDAH diputuskan
 *               (status bukan pending) — mencegah approve/reject dobel yang bisa
 *               membuat DeadlineExtensionObserver menjalankan efek F-47 dua kali
 *               (tidak merusak data karena idempotent by design, tapi tetap
 *               keputusan tidak sah untuk mengubah keputusan yang sudah final).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\DeadlineExtension\ApproveExtensionRequest;
use App\Http\Requests\DeadlineExtension\RejectExtensionRequest;
use App\Http\Requests\DeadlineExtension\StoreDeadlineExtensionRequest;
use App\Models\Attachment;
use App\Models\DeadlineExtension;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeadlineExtensionController extends Controller
{
    /**
     * BUSINESS RULE: F-95 — assignee ATAU admin (task.manage, pola sama
     * AttachmentController::store()), BUKAN permission RBAC baru untuk member.
     * Task dipilih dari BODY (task_id), bukan URL — halaman "Perpanjangan Saya"
     * flat lintas project (pola sama my-tasks), jadi tidak ada {project}/{task}
     * di URL untuk di-scopeBindings.
     */
    public function store(StoreDeadlineExtensionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $task = Task::with('taskStatus')->findOrFail($request->validated('task_id'));

        abort_unless(
            $user->can('task.manage') || $task->assignees()->whereKey($user->id)->exists(),
            403,
            'Kamu tidak di-assign ke task ini.'
        );

        DB::transaction(function () use ($request, $task, $user) {
            $extension = DeadlineExtension::create([
                'task_id' => $task->id,
                'requested_by' => $user->id,
                'old_due_date' => $task->due_date,
                'requested_due_date' => $request->validated('requested_due_date'),
                'additional_minutes' => $request->validated('additional_minutes') ?? 0,
                'reason' => $request->validated('reason'),
            ]);

            // F-49: evidence OPSIONAL (skema tidak mewajibkannya, beda dari `reason`
            // yang eksplisit wajib di data model) — infra sama dengan attachment output.
            if ($request->hasFile('evidence')) {
                Attachment::storeUploadedFile($request->file('evidence'), [
                    'task_id' => $task->id,
                    'deadline_extension_id' => $extension->id,
                    'type' => 'evidence',
                    'uploaded_by' => $user->id,
                ]);
            }
        });

        return to_route('extensions.my');
    }

    /**
     * BUSINESS RULE: Hari-6 §B4 — halaman member: form ajukan (dropdown task
     * assigned & belum selesai, pola sama TaskController::myTasks()) + daftar
     * status pengajuan MILIK SENDIRI (requested_by, lintas project).
     */
    public function myExtensions(Request $request): Response
    {
        $user = $request->user();

        $tasks = Task::whereHas('assignees', fn ($q) => $q->whereKey($user->id))
            ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false))
            ->with('project:id,name')
            ->orderBy('due_date')
            ->get(['id', 'title', 'project_id', 'due_date']);

        $extensions = DeadlineExtension::where('requested_by', $user->id)
            ->with(['task:id,title,project_id', 'task.project:id,name', 'attachments' => fn ($q) => $q->where('type', 'evidence')])
            ->latest()
            ->get();

        return Inertia::render('extensions/my-extensions', [
            'tasks' => $tasks,
            'extensions' => $extensions,
        ]);
    }

    /**
     * BUSINESS RULE: BF §6 matriks — "Lihat dashboard tim" & aksi approve/reject
     * admin only. Antrean = PENDING saja (yang butuh keputusan); yang sudah
     * diputuskan tetap tercatat di riwayat "Perpanjangan Saya" pemohon, tidak
     * perlu ditampilkan lagi di antrean admin.
     */
    public function index(): Response
    {
        $extensions = DeadlineExtension::where('status', 'pending')
            ->with([
                'task:id,title,project_id,due_date',
                'task.project:id,name',
                'requestedBy:id,name',
                'attachments' => fn ($q) => $q->where('type', 'evidence'),
            ])
            ->oldest()
            ->get();

        return Inertia::render('extensions/index', [
            'extensions' => $extensions,
        ]);
    }

    /**
     * BUSINESS RULE: F-47 (original_due_date) & pemutakhiran due_date/estimated_minutes
     * SEPENUHNYA ditangani DeadlineExtensionObserver::updated() — di sini HANYA
     * mengubah status supaya observer itu satu-satunya tempat yang menegakkan aturan.
     */
    public function approve(ApproveExtensionRequest $request, DeadlineExtension $deadlineExtension): RedirectResponse
    {
        $this->guardStillPending($deadlineExtension);

        $deadlineExtension->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->validated('review_note'),
        ]);

        return to_route('extensions.index');
    }

    public function reject(RejectExtensionRequest $request, DeadlineExtension $deadlineExtension): RedirectResponse
    {
        $this->guardStillPending($deadlineExtension);

        $deadlineExtension->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->validated('review_note'),
        ]);

        return to_route('extensions.index');
    }

    private function guardStillPending(DeadlineExtension $extension): void
    {
        if ($extension->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan ini sudah diputuskan sebelumnya.',
            ]);
        }
    }
}
