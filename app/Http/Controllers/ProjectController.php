<?php

/**
 * ==========================================================
 * MODUL       : ProjectController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : CRUD Project (F-8 level ke-2 hierarki). Admin lihat semua, member
 *               hanya yang di-assign (03-BUSINESS-FLOW §6). Create/edit/archive admin only.
 * DIPANGGIL   : routes/admin.php (create/store/edit/update/archive), routes/web.php (index)
 * MEMANGGIL   : Project, Task, TaskStatus::seedDefaults(), User (dropdown owner/members)
 * DATA MASUK  : Form Project CRUD
 * DATA KELUAR : Inertia pages 'projects/*'
 * RISIKO      : SUMBER : Hari-3 §C3 — Project::create() + TaskStatus::seedDefaults()
 *               WAJIB dalam satu DB::transaction(). Project tanpa status = project
 *               rusak (task tidak akan pernah bisa dibuat Hari-4, tidak ada TODO default).
 *               F-87 (HARDEN) — update() menolak drop member yang masih punya task
 *               is_work_state di project ini (guardAgainstRemovingMembersWithActiveTasks).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * BUSINESS RULE: 03-BUSINESS-FLOW §6 — admin lihat SEMUA project (organisasi,
     * via OrganizationScope), member HANYA yang di-assign ke dia.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // F-90: project.viewAll (bukan isAdmin()).
        $projects = $user->can('project.viewAll')
            ? Project::with('owner')->orderBy('name')->get()
            : $user->projects()->with('owner')->orderBy('name')->get();

        return Inertia::render('projects/index', [
            'projects' => $projects,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('projects/create', [
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $project = Project::create($request->safe()->only(['name', 'description', 'owner_id']));

            // F-71: sync() (bukan query manual ke pivot) supaya ProjectUserObserver
            // menangkap event assigned untuk tiap member. Owner digabung ke daftar
            // member supaya pemilik project otomatis punya akses, walau admin lupa
            // mencentangnya di multi-select.
            $memberIds = array_unique([...($request->validated('members') ?? []), $project->owner_id]);
            $project->members()->sync($memberIds);

            TaskStatus::seedDefaults($project);
        });

        return to_route('projects.index');
    }

    public function edit(Project $project): Response
    {
        $project->load('members');

        return Inertia::render('projects/edit', [
            'project' => $project,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'memberIds' => $project->members->pluck('id'),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $memberIds = array_unique([...($request->validated('members') ?? []), $project->owner_id]);

        $this->guardAgainstRemovingMembersWithActiveTasks($project, $memberIds);

        $project->update($request->safe()->only(['name', 'description', 'owner_id']));

        $project->members()->sync($memberIds);

        return to_route('projects.index');
    }

    /**
     * BUSINESS RULE: F-87 — member yang di-drop dari project TAPI masih punya task
     * berstatus is_work_state (segmen terbuka, counter jalan — F-38/F-41) di project
     * ini DITOLAK. Kalau lolos: segmen tidak pernah ditutup, actual_minutes tak pernah
     * beku, dan member kehilangan akses (404) ke task miliknya sendiri yang menggantung
     * selamanya. Pola sama dengan F-19 (tolak hapus status berisi task).
     *
     * SUMBER data: task_status_id + task_user pivot — SATU query (bukan per-member,
     * bukan di service terpisah) supaya jalur HTTP (di sini) DAN pemanggil lain di
     * masa depan sama-sama lewat guard ini, bukan cuma dicek di UI (C3).
     */
    private function guardAgainstRemovingMembersWithActiveTasks(Project $project, array $newMemberIds): void
    {
        $removedMemberIds = $project->members()->pluck('users.id')->diff($newMemberIds);

        if ($removedMemberIds->isEmpty()) {
            return;
        }

        $blockedMembers = Task::where('project_id', $project->id)
            ->whereHas('taskStatus', fn ($query) => $query->where('is_work_state', true))
            ->with(['assignees' => fn ($query) => $query->whereIn('users.id', $removedMemberIds)])
            ->get()
            ->pluck('assignees')
            ->flatten()
            ->unique('id')
            ->whereIn('id', $removedMemberIds);

        if ($blockedMembers->isEmpty()) {
            return;
        }

        $names = $blockedMembers->pluck('name')->implode(', ');

        throw ValidationException::withMessages([
            'members' => "Member punya task sedang dikerjakan, tidak bisa dihapus dari project: {$names}. Selesaikan atau pindahkan assignee dulu.",
        ]);
    }

    /**
     * BUSINESS RULE: F-16 — archive, BUKAN delete. Project dengan riwayat task/KPI
     * tidak boleh dihapus permanen.
     */
    public function archive(Project $project): RedirectResponse
    {
        $project->update(['is_archived' => true]);

        return to_route('projects.index');
    }
}
