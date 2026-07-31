<?php

/**
 * ==========================================================
 * MODUL       : TaskStatusController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : CRUD status per project (F-44, admin only). Reorder via swap posisi,
 *               BUKAN drag-drop (itu v1.0 bareng Board View — Hari-3 §D3 melarang).
 * DIPANGGIL   : routes/admin.php
 * MEMANGGIL   : TaskStatus::wouldLeaveNoWorkState(), Task (cek dipakai sebelum hapus)
 * DATA MASUK  : Form CRUD status per project (identitas) + form radio/checkbox
 *               index (flag, F-74)
 * DATA KELUAR : Inertia pages 'task-statuses/*'
 * RISIKO      : SUMBER : D5 — hapus status yang masih dipakai task WAJIB ditolak,
 *               JANGAN cascade delete/pindah otomatis. Reorder WAJIB atomic (swap
 *               dalam transaction) supaya constraint "position unik per project"
 *               (D4) tidak pernah bolong walau di tengah proses. F-74 (Hari-5) —
 *               updateFlags() SATU-SATUNYA tempat ketiga flag berubah sekarang;
 *               store()/update() cuma identitas (nama/warna).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\TaskStatus\StoreTaskStatusRequest;
use App\Http\Requests\TaskStatus\UpdateTaskStatusFlagsRequest;
use App\Http\Requests\TaskStatus\UpdateTaskStatusRequest;
use App\Models\Project;
use App\Models\TaskStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TaskStatusController extends Controller
{
    public function index(Project $project): Response
    {
        return Inertia::render('task-statuses/index', [
            'project' => $project->only(['id', 'name']),
            'statuses' => $project->taskStatuses,
        ]);
    }

    public function create(Project $project): Response
    {
        return Inertia::render('task-statuses/create', [
            'project' => $project->only(['id', 'name']),
        ]);
    }

    public function store(StoreTaskStatusRequest $request, Project $project): RedirectResponse
    {
        // BUSINESS RULE: position TIDAK diminta dari form (D2) — status baru selalu
        // ditambahkan di URUTAN PALING AKHIR. Urutan ditata belakangan via reorder (D3).
        $nextPosition = ((int) $project->taskStatuses()->max('position')) + 1;

        // BUSINESS RULE F-74 (Hari-5): status baru SELALU lahir netral (ketiga flag
        // false). Admin mengatur "siapa jadi selesai/review/sedang dikerjakan" lewat
        // form radio di updateFlags() — TIDAK bisa diisi saat create, karena constraint
        // "tepat 1 completed" cuma aman diedit kalau semua status project terlihat sekaligus.
        TaskStatus::create([
            'project_id' => $project->id,
            'position' => $nextPosition,
            'is_work_state' => false,
            'is_review' => false,
            'is_completed' => false,
            ...$request->validated(),
        ]);

        return to_route('task-statuses.index', $project);
    }

    public function edit(Project $project, TaskStatus $taskStatus): Response
    {
        return Inertia::render('task-statuses/edit', [
            'project' => $project->only(['id', 'name']),
            'status' => $taskStatus,
        ]);
    }

    public function update(UpdateTaskStatusRequest $request, Project $project, TaskStatus $taskStatus): RedirectResponse
    {
        $taskStatus->update($request->validated());

        return to_route('task-statuses.index', $project);
    }

    /**
     * BUSINESS RULE: D3 — swap ATOMIK dengan status yang menempati posisi tujuan.
     * Tombol naik/turun secara definisi menuju posisi yang sudah ditempati status
     * lain, jadi ini BUKAN reject-on-collision — swap menjaga constraint "position
     * unik per project" (D4) tetap benar di kedua baris sekaligus.
     */
    public function reorder(Request $request, Project $project, TaskStatus $taskStatus): RedirectResponse
    {
        $request->validate([
            'direction' => ['nullable', 'in:up,down'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $targetPosition = match (true) {
            $request->filled('direction') => $taskStatus->position + ($request->input('direction') === 'up' ? -1 : 1),
            $request->filled('position') => (int) $request->input('position'),
            default => null,
        };

        if ($targetPosition === null || $targetPosition === $taskStatus->position) {
            return back();
        }

        DB::transaction(function () use ($project, $taskStatus, $targetPosition) {
            $swapWith = TaskStatus::where('project_id', $project->id)
                ->where('position', $targetPosition)
                ->first();

            // GUARD: posisi tujuan tidak ada (mis. tombol "naik" ditekan saat sudah
            // paling atas) -> no-op, bukan error. Tidak ada yang perlu diswap.
            if (! $swapWith) {
                return;
            }

            $originalPosition = $taskStatus->position;
            $taskStatus->update(['position' => $targetPosition]);
            $swapWith->update(['position' => $originalPosition]);
        });

        return to_route('task-statuses.index', $project);
    }

    /**
     * BUSINESS RULE: D5 — TOLAK kalau masih ada task memakai status ini. JANGAN
     * cascade delete, JANGAN pindahkan task otomatis. F-74 (Hari-5) — TOLAK juga
     * kalau status ini pemegang is_completed (harus TEPAT 1 selalu ada, F-19) atau
     * satu-satunya pemegang is_work_state (F-41 butuh minimal 1). is_review TIDAK
     * perlu dicek — "boleh tidak ada" adalah state yang sah (B2).
     */
    public function destroy(Project $project, TaskStatus $taskStatus): RedirectResponse
    {
        $taskCount = $taskStatus->tasks()->count();

        if ($taskCount > 0) {
            throw ValidationException::withMessages([
                'status' => "Masih ada {$taskCount} task di status ini. Pindahkan dulu.",
            ]);
        }

        if ($taskStatus->is_completed) {
            throw ValidationException::withMessages([
                'status' => "Tidak bisa hapus status 'selesai' — pilih status lain jadi 'selesai' dulu di tabel flag (F-19).",
            ]);
        }

        if ($taskStatus->is_work_state && TaskStatus::wouldLeaveNoWorkState($project, $taskStatus->id)) {
            throw ValidationException::withMessages([
                'status' => "Tidak bisa hapus — ini satu-satunya status 'sedang dikerjakan'. Counter waktu (F-41) butuh minimal 1.",
            ]);
        }

        $taskStatus->delete();

        return to_route('task-statuses.index', $project);
    }

    /**
     * BUSINESS RULE F-74 (Hari-5 §B) — SATU-SATUNYA tempat is_completed/is_review/
     * is_work_state berubah. Submit dari tabel radio+checkbox di halaman index,
     * mencakup SEMUA status project sekaligus dalam SATU transaction: set semua
     * false dulu, baru set yang dipilih true. Tidak pernah ada state invalid
     * karena tidak pernah ada langkah antara yang bisa gagal di tengah (B3).
     */
    public function updateFlags(UpdateTaskStatusFlagsRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project) {
            TaskStatus::where('project_id', $project->id)->update([
                'is_completed' => false,
                'is_review' => false,
                'is_work_state' => false,
            ]);

            TaskStatus::whereKey($request->validated('is_completed_id'))->update(['is_completed' => true]);

            if ($reviewId = $request->validated('is_review_id')) {
                TaskStatus::whereKey($reviewId)->update(['is_review' => true]);
            }

            TaskStatus::whereIn('id', $request->validated('work_state_ids'))->update(['is_work_state' => true]);
        });

        return to_route('task-statuses.index', $project);
    }
}
