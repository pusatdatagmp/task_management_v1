<?php

/**
 * ==========================================================
 * MODUL       : BoardController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Papan Kanban (v1.0 H1/H2, F-109) — TAMPILAN ALTERNATIF dari task
 *               yang SAMA dengan List View (TaskController::index()), dikelompokkan
 *               per kolom status. H2 (F-110/F-111): kirim `position` kolom + flag
 *               `can_drag` per kartu supaya FRONTEND bisa menghitung kolom sah/tak-sah
 *               SEBELUM user melepas drag (aturan C) — validasi ASLI tetap di server
 *               lewat endpoint tasks.status yang sudah ada (drop TIDAK bikin endpoint baru).
 * DIPANGGIL   : routes/web.php (bukan admin-only, gating sama TaskController::show())
 * MEMANGGIL   : Task, TaskStatus, LiveTaskCounter (F-94, REUSE — nol kalkulator baru)
 * DATA MASUK  : project dari route model binding, filter query string (assignee[]/priority[])
 * DATA KELUAR : Inertia page 'tasks/board'
 * RISIKO      : SUMBER F-109 — SATU-SATUNYA angka yang dihitung DI SINI adalah
 *               `due_status` (overdue/today), dan itu MURNI PERBANDINGAN TANGGAL
 *               (pola identik TaskController::index() filter 'due'), BUKAN kalkulator
 *               KPI (business-hours/realisasi/beban) — angka realisasi/counter
 *               SEPENUHNYA didelegasikan ke LiveTaskCounter (F-94), tidak dihitung
 *               ulang di sini maupun di frontend. A4 — subtask SENGAJA dikecualikan
 *               dari query (whereNull('parent_task_id')), cuma muncul sebagai
 *               children_count di kartu parent, bukan kartu sendiri. `can_drag` HANYA
 *               HINT UI (F-95, pola sama TaskStatusCell) — server (TaskTransitionService)
 *               tetap menegakkan assignee/task.manage ULANG saat drop benar-benar terjadi.
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\Task\FilterTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Services\LiveTaskCounter;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    /**
     * BUSINESS RULE: F-95 — gating sama persis TaskController::show() (project.viewAll
     * ATAU member project ini), 404 (bukan 403) untuk member project lain supaya
     * keberadaan project tidak bocor. B1/B2 — filter assignee/priority SERVER-SIDE,
     * pakai FilterTaskRequest yang SUDAH ADA (reuse, field status/due/sort di
     * dalamnya diabaikan di sini, tidak perlu Request baru untuk subset field).
     */
    public function index(Project $project, FilterTaskRequest $request): Response
    {
        $user = $request->user();

        abort_unless($user->can('project.viewAll') || $project->members()->whereKey($user->id)->exists(), 404);

        $filters = $request->validated();

        // F-44: kolom dari data (TaskStatus urut position), bukan nama hardcode.
        $statuses = $project->taskStatuses;

        // A4: HANYA task top-level yang jadi kartu — subtask ditandai children_count
        // di kartu induknya, bukan kartu terpisah.
        // BUG FIX (audit Boss 2026-08-14, F-172): query ini SEBELUMNYA nol orderBy
        // sama sekali -- urutan kartu per kolom murni ikut urutan fisik row MySQL,
        // tidak deterministic (bisa geser sendiri antar reload tanpa user ngapa-ngapain).
        // BUKAN orderBy('position') -- kolom `tasks.position` ("urutan board v1.0")
        // TERNYATA tidak pernah ditulis di mana pun (grep app/, nol hit) selalu 0,
        // drag-drop TIDAK PERNAH mem-persist urutan manual (cuma pindah kolom status).
        // orderBy('position') di sini jadi fix PALSU (semua baris seri di 0, urutan
        // tetap acak). created_at desc dipilih sebagai pengganti yang jujur & deterministic
        // (konsisten tema F-172 lainnya) sampai/kalau Boss minta fitur urutan manual
        // drag-drop sungguhan (persist position saat drop -- scope terpisah, lebih besar).
        $query = Task::where('project_id', $project->id)
            ->whereNull('parent_task_id')
            ->orderByDesc('created_at')
            ->with(['taskStatus', 'assignees:id,name'])
            ->withCount('children')
            // Revisi 2026-08-06 item 1 (F-85): alias SAMA PERSIS TaskController::
            // withChecklistCounts() -- beda class, definisi identik sengaja tidak
            // dishare (2 baris trivial, bukan kalkulator KPI, F-109 soal itu bukan soal ini).
            ->withCount([
                'checklistItems as checklist_items_count',
                'checklistItems as checklist_done_items_count' => fn ($q) => $q->where('is_done', true),
            ]);

        if (! empty($filters['assignee'])) {
            $query->whereHas('assignees', fn ($q) => $q->whereIn('users.id', $filters['assignee']));
        }

        if (! empty($filters['priority'])) {
            $query->whereIn('priority', $filters['priority']);
        }

        $tasks = $query->get();

        // F-94/F-109: counter live 100% dari LiveTaskCounter, SATU sumber sama
        // dengan List View/My Tasks/Detail — nol reimplementasi di board.
        $liveCounters = (new LiveTaskCounter)->forTasks($tasks, $user);

        $now = Carbon::now();
        $todayEnd = Carbon::today()->endOfDay();

        $canManageTask = $user->can('task.manage');

        $cards = $tasks->map(function (Task $task) use ($liveCounters, $now, $todayEnd, $user, $canManageTask) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'task_type' => $task->task_type,
                'priority' => $task->priority,
                'priority_quadrant' => $task->priority_quadrant, // F-122/F-126: badge Eisenhower di kartu kanban
                'points' => $task->points,
                'due_date' => $task->due_date,
                'task_status_id' => $task->task_status_id,
                'is_work_state' => $task->taskStatus->is_work_state,
                'assignees' => $task->assignees,
                'children_count' => $task->children_count,
                'progress_percent' => $task->progressPercent(), // revisi 2026-08-06 item 1
                'checklist_items_count' => $task->checklist_items_count,
                'live_counter' => $liveCounters[$task->id] ?? null,
                // SUMBER: perbandingan tanggal MENTAH (pola sama TaskController::index()
                // filter 'due') — BUKAN kalkulator KPI, F-109 tidak melarang ini.
                'due_status' => $task->taskStatus->is_completed
                    ? null
                    : ($task->due_date->lt($now) ? 'overdue' : ($task->due_date->lte($todayEnd) ? 'today' : null)),
                // F-95/C4 (H2): pola sama TaskTransitionService::changeStatus() — admin
                // (task.manage) ATAU assignee task ini. HINT UI saja, lihat RISIKO header.
                'can_drag' => $canManageTask || $task->assignees->contains('id', $user->id),
            ];
        });

        $columns = $statuses->map(fn (TaskStatus $status) => [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'position' => $status->position,
            'is_work_state' => $status->is_work_state,
            'is_review' => $status->is_review,
            'is_completed' => $status->is_completed,
            'cards' => $cards->where('task_status_id', $status->id)->values(),
        ]);

        return Inertia::render('tasks/board', [
            'project' => $project->only(['id', 'name']),
            'columns' => $columns,
            'members' => $project->members()->select('users.id', 'users.name')->orderBy('users.name')->get(),
            'filters' => [
                'assignee' => $filters['assignee'] ?? [],
                'priority' => $filters['priority'] ?? [],
            ],
        ]);
    }
}
