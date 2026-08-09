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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * BUSINESS RULE: 03-BUSINESS-FLOW §6 — admin lihat SEMUA project (organisasi,
     * via OrganizationScope), member HANYA yang di-assign ke dia.
     *
     * Revisi 2026-08-07 (permintaan Boss): HANYA project aktif (is_archived=false)
     * -- project diarsipkan pindah TOTAL ke halaman terpisah projects/archive()
     * di bawah, bukan lagi digabung+difilter di halaman ini.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // F-90: project.viewAll (bukan isAdmin()).
        $projects = $user->can('project.viewAll')
            ? Project::with('owner')->where('is_archived', false)->orderBy('name')->get()
            : $user->projects()->with('owner')->where('is_archived', false)->orderBy('name')->get();

        return Inertia::render('projects/index', [
            'projects' => $projects,
        ]);
    }

    /**
     * KONTRAK: halaman "Arsip Project" (permintaan Boss 2026-08-07) -- daftar
     * project is_archived=true SAJA, cerminan index() tapi terbalik. Gating
     * SAMA seperti edit()/archive() (route group can:project.manage) -- arsip
     * dianggap area manajemen, bukan tontonan member biasa.
     */
    public function archived(Request $request): Response
    {
        $user = $request->user();

        $projects = $user->can('project.viewAll')
            ? Project::with('owner')->where('is_archived', true)->orderBy('name')->get()
            : $user->projects()->with('owner')->where('is_archived', true)->orderBy('name')->get();

        return Inertia::render('projects/archive', [
            'projects' => $projects,
        ]);
    }

    /**
     * Revisi 2026-08-07 (permintaan Boss): checklist Member di form Project
     * Baru HANYA user is_active=true -- user nonaktif (toggleActive(), F-16)
     * tidak boleh ditawarkan sebagai member baru.
     */
    public function create(): Response
    {
        return Inertia::render('projects/create', [
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'owners' => $this->eligibleOwners(),
        ]);
    }

    /**
     * KONTRAK (2026-08-08, keputusan Boss): owner_ids[] (bisa >1, urutan pilih =
     * urutan posisi) menggantikan owner_id tunggal. Elemen PERTAMA jadi
     * projects.owner_id (owner "utama" -- lihat Project::owner() RISIKO) supaya
     * automation/created_by tidak perlu tahu konsep multi-owner sama sekali.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $ownerIds = $request->validated('owner_ids');

            $project = Project::create([
                ...$request->safe()->only(['name', 'description']),
                'owner_id' => $ownerIds[0],
            ]);

            $project->owners()->sync($this->ownerSyncPayload($ownerIds));

            // F-71: sync() (bukan query manual ke pivot) supaya ProjectUserObserver
            // menangkap event assigned untuk tiap member. SEMUA owner digabung ke
            // daftar member supaya mereka otomatis punya akses, walau admin lupa
            // mencentangnya di multi-select.
            $memberIds = array_unique([...($request->validated('members') ?? []), ...$ownerIds]);
            $project->members()->sync($memberIds);

            TaskStatus::seedDefaults($project);
        });

        return to_route('projects.index');
    }

    /**
     * KONTRAK: bentuk payload sync() pivot berposisi -- {user_id: ['position' => i]},
     * urutan array $ownerIds MENENTUKAN posisi (0 = utama). DIPAKAI store()/update()
     * supaya logic-nya SATU tempat, tidak diketik ulang.
     *
     * @param  array<int, int>  $ownerIds
     * @return array<int, array{position: int}>
     */
    private function ownerSyncPayload(array $ownerIds): array
    {
        return collect($ownerIds)->mapWithKeys(fn (int $id, int $position) => [$id => ['position' => $position]])->all();
    }

    /**
     * BUG FIX (2026-08-08, permintaan Boss): checklist Member SEKARANG juga HANYA
     * is_active=true, sama seperti create() -- member nonaktif tidak lagi muncul
     * sebagai checkbox di sini.
     *
     * AMAN dari silent-removal (sempat saya khawatirkan, TERNYATA TIDAK): frontend
     * (projects/edit.tsx) seed `data.members` dari `memberIds` DI BAWAH ini --
     * daftar member LENGKAP project (termasuk yang nonaktif), BUKAN dibangun ulang
     * dari checkbox `users` yang tampil. toggleMember() cuma MENGUBAH array lewat
     * klik eksplisit; id member nonaktif yang tidak punya checkbox tidak pernah
     * tersentuh, jadi tetap ikut terkirim utuh ke sync() saat submit -- keanggotaan
     * mereka TIDAK hilang walau tidak terlihat di daftar.
     */
    public function edit(Project $project): Response
    {
        $project->load('members', 'owners');

        return Inertia::render('projects/edit', [
            'project' => $project,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'owners' => $this->eligibleOwners($this->currentOwnerIds($project)),
            'memberIds' => $project->members->pluck('id'),
            'ownerIds' => $this->currentOwnerIds($project),
        ]);
    }

    /**
     * KONTRAK: daftar owner project SAAT INI, ROBUST terhadap project_owners
     * yang kosong (project dibuat LANGSUNG lewat Project::create(['owner_id'=>..])
     * -- pola yang dipakai di banyak test/seeder lain, BUKAN lewat store() yang
     * mengisi pivot -- tanpa fallback ini, checklist Owner form Edit tampil KOSONG
     * dan validasi "min:1" gagal begitu admin simpan tanpa menyentuh apa pun).
     * owner_id (kolom lama) SELALU dimasukkan kalau belum ada di pivot.
     *
     * @return array<int, int>
     */
    private function currentOwnerIds(Project $project): array
    {
        $ids = $project->owners->pluck('id')->all();

        if ($project->owner_id && ! in_array($project->owner_id, $ids, true)) {
            $ids[] = $project->owner_id;
        }

        return $ids;
    }

    /**
     * KONTRAK: permintaan Boss (2026-08-07) — checklist "Owner (reviewer, F-28)"
     * HANYA user dengan permission `project.manage` (F-90: per-permission,
     * BUKAN hardcode nama role "Admin" — role dinamis dari tabel roles).
     * $currentOwnerIds (saat edit project existing, BISA LEBIH DARI SATU sejak
     * 2026-08-08) SELALU ikut ditambahkan walau permission-nya sudah dicabut
     * belakangan -- kalau tidak, checklist kehilangan opsi utk value yang
     * sedang tersimpan & owner existing tampil hilang padahal masih sah.
     *
     * BUG FIX (2026-08-08, permintaan Boss): SEBELUM ini nol filter is_active --
     * member yang sudah dinonaktifkan (toggleActive(), F-16) masih bisa dipilih
     * jadi owner BARU. `is_active` digabung DI DALAM cabang permission (bukan
     * AND di luar seluruh where()) supaya $currentOwnerIds TETAP muncul walau
     * sudah nonaktif -- pola sama persis dengan guard permission-dicabut di atas,
     * cuma AND baru ini menambah syarat "aktif" untuk kandidat yang BISA dipilih
     * baru, tanpa menyembunyikan owner yang SUDAH tersimpan.
     *
     * @param  array<int, int>  $currentOwnerIds
     * @return Collection<int, array{id: int, name: string}>
     */
    private function eligibleOwners(array $currentOwnerIds = [])
    {
        return User::where(function ($query) use ($currentOwnerIds) {
            $query->where('is_active', true)
                ->whereHas('role.permissions', fn ($q) => $q->where('permission_name', 'project.manage'));
            if (! empty($currentOwnerIds)) {
                $query->orWhereIn('id', $currentOwnerIds);
            }
        })->orderBy('name')->get(['id', 'name']);
    }

    /**
     * KONTRAK (2026-08-08, keputusan Boss): owner_ids[] menggantikan owner_id
     * tunggal, pola sama persis store() -- elemen PERTAMA jadi projects.owner_id.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $ownerIds = $request->validated('owner_ids');
        $memberIds = array_unique([...($request->validated('members') ?? []), ...$ownerIds]);

        $this->guardAgainstRemovingMembersWithActiveTasks($project, $memberIds);

        $project->update([
            ...$request->safe()->only(['name', 'description']),
            'owner_id' => $ownerIds[0],
        ]);

        $project->owners()->sync($this->ownerSyncPayload($ownerIds));
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

    /**
     * KONTRAK: kebalikan archive() (permintaan Boss 2026-08-07) -- SEBELUM ini
     * TIDAK ADA jalur untuk membatalkan arsip sama sekali. is_archived=false lagi,
     * project kembali muncul di index() aktif. Redirect ke halaman arsip (bukan
     * index) -- Boss sedang di situ, project yang dipulihkan otomatis hilang dari
     * daftar arsip (is_archived sudah false), konsisten tanpa nyasar ke halaman lain.
     */
    public function restore(Project $project): RedirectResponse
    {
        $project->update(['is_archived' => false]);

        return to_route('projects.archived');
    }
}
