<?php

/**
 * ==========================================================
 * MODUL       : TaskChecklistItemController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : CRUD item checklist dalam-tugas (F-123) — dipakai gate transisi
 *               ->review (F-127, ditegakkan di TaskTransitionService, BUKAN di sini).
 *               Kepemilikan (revisi Boss 2026-08-06 — MENGGANTI keputusan LANGKAH 0
 *               v1.2 H5 lama): task.manage SATU-SATUNYA yang boleh menambah/ubah
 *               teks/hapus item ("syarat kerja"). Assignee TIDAK BISA lagi menambah
 *               item baru — cuma boleh mencentang (toggle) item yang sudah ada,
 *               dan HANYA saat task sedang is_work_state=true (F-44 — flag, bukan
 *               nama status "TODO"; task belum "Mulai" = belum ada progres nyata
 *               untuk ditandai, H7/F-132/F-138).
 * DIPANGGIL   : routes/web.php (mixed access, gating DI CONTROLLER — F-95 pola
 *               sama CommentController/AttachmentController)
 * MEMANGGIL   : TaskChecklistItem, Task (assignees() untuk cek membership)
 * DATA MASUK  : Form checklist di tasks/show.tsx, route model binding
 *               project/task/checklistItem (F-76 scopeBindings)
 * DATA KELUAR : Baris task_checklist_items
 * RISIKO      : SUMBER F-95 — member = NOL permission RBAC untuk aksi ini, gating
 *               MURNI assignee/task.manage, bukan permission baru (konsisten
 *               Comment/Attachment). TIDAK ADA activity log di sini (pola sama
 *               TaskChecklistItem model header — gap sementara, dicatat H2/H5).
 *               toggleDone() menolak dengan 422 (ValidationException) kalau
 *               task belum is_work_state — BEDA dari 403 (bukan soal izin,
 *               soal state task, pola sama gate F-127 di TaskTransitionService).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\ChecklistItem\StoreChecklistItemRequest;
use App\Http\Requests\ChecklistItem\UpdateChecklistItemRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class TaskChecklistItemController extends Controller
{
    /**
     * BUSINESS RULE (revisi Boss 2026-08-06): task.manage SATU-SATUNYA yang boleh
     * menambah item — assignee TIDAK BISA lagi tambah item baru (dulu boleh,
     * dicabut). position = max+1 supaya item baru selalu di akhir daftar
     * (F-123, reorder manual opsional).
     */
    public function store(StoreChecklistItemRequest $request, Project $project, Task $task): RedirectResponse
    {
        abort_unless($request->user()->can('task.manage'), 403); // F-90

        $nextPosition = ((int) $task->checklistItems()->max('position')) + 1;

        $task->checklistItems()->create([
            'organization_id' => $task->organization_id,
            'text' => $request->validated('text'),
            'position' => $nextPosition,
        ]);

        return back();
    }

    /**
     * BUSINESS RULE: F-123 — ubah TEKS item, task.manage ONLY (item = "syarat
     * kerja" yang didefinisikan task.manage). BUKAN endpoint untuk centang —
     * itu toggleDone() di bawah, mixed access.
     */
    public function update(UpdateChecklistItemRequest $request, Project $project, Task $task, TaskChecklistItem $checklistItem): RedirectResponse
    {
        abort_unless($request->user()->can('task.manage'), 403); // F-90

        $checklistItem->update(['text' => $request->validated('text')]);

        return back();
    }

    /**
     * BUSINESS RULE: F-123/F-127 — centang/uncentang, task.manage ATAU assignee
     * task ini (siapa pun yang boleh kerjakan task boleh menandai progres-nya).
     * Toggle murni flip is_done saat ini, tidak butuh input (pola sama
     * TaskTemplateController::toggleActive()).
     *
     * BUSINESS RULE (revisi Boss 2026-08-06): CUMA bisa dicentang saat task
     * sedang is_work_state=true (F-44 — flag, bukan cek nama "TODO"). Task
     * yang belum "Mulai" (H7/F-132/F-138) belum ada progres nyata untuk
     * ditandai; ini juga otomatis mengunci toggle saat task sudah masuk
     * Review/Selesai (is_work_state ikut false di kedua status itu).
     */
    public function toggleDone(Project $project, Task $task, TaskChecklistItem $checklistItem): RedirectResponse
    {
        $user = request()->user();

        abort_unless($user->can('task.manage') || $task->assignees()->whereKey($user->id)->exists(), 403, 'Kamu tidak di-assign ke task ini.');

        if (! $task->taskStatus->is_work_state) {
            throw ValidationException::withMessages([
                'checklist' => 'Task belum dimulai — centang checklist hanya bisa dilakukan saat task sedang dikerjakan.',
            ]);
        }

        $checklistItem->update(['is_done' => ! $checklistItem->is_done]);

        return back();
    }

    /**
     * BUSINESS RULE: F-123 — hapus item, task.manage ONLY (pola sama update()
     * teks — item adalah "syarat kerja", assignee tidak bisa membuang syarat
     * yang ditetapkan, hanya bisa menambah punya sendiri).
     */
    public function destroy(Project $project, Task $task, TaskChecklistItem $checklistItem): RedirectResponse
    {
        abort_unless(request()->user()->can('task.manage'), 403); // F-90

        $checklistItem->delete();

        return back();
    }
}
