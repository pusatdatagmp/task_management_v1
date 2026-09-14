<?php

/**
 * ==========================================================
 * MODUL       : UserController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : CRUD user/member + onboarding RBAC (F-90/F-91). Satu-satunya
 *               jalur menambah akun tim — self-signup DIMATIKAN
 *               (03-BUSINESS-FLOW §7). Gerbang permission `user.manage`
 *               (routes/admin.php), bukan lagi middleware 'admin' blanket.
 * DIPANGGIL   : routes/admin.php (index/create/store/edit/update/toggleActive/
 *               destroy/restore/trashed)
 * MEMANGGIL   : User, Role, Task, UserService (onboarding — RBAC §C)
 * DATA MASUK  : Form buat/edit user, form onboarding 3-mode (Fase E2)
 * DATA KELUAR : Inertia pages 'users/*', flash session `generatedPassword` (SEKALI)
 * RISIKO      : index() mengirim `users` (gate user.manage) DAN `roles` (gate
 *               role.manage, F-170 — dulu SAMA can:user.manage) supaya halaman ini
 *               bisa menampilkan Pengguna & Peran 2-kolom sekaligus — tiap kolom
 *               null kalau permission-nya tidak dipegang (lihat index()).
 *               SUMBER : F-16 — hard delete DILARANG (jejak assignee/approver di
 *               riwayat KPI tidak boleh hilang). destroy() (2026-09-11, keputusan
 *               Boss: "fitur hapus akun, bukan hanya nonaktif") memakai kolom
 *               deleted_at + trait SoftDeletes yang SUDAH terpasang di User sejak
 *               Hari-1 tapi belum pernah dipakai — jadi TETAP patuh F-16 (baris DB
 *               tidak hilang). toggleActive() (is_active) DIPERTAHANKAN berdampingan
 *               sebagai suspend cepat/reversibel; destroy() adalah tindakan lebih
 *               kuat (hilang dari SEMUA listing, perlu restore() eksplisit dari
 *               halaman Sampah). Supaya soft-delete ini tidak diam-diam menghapus
 *               atribusi KPI dari TAMPILAN, seluruh relasi belongsTo/belongsToMany
 *               ke User di model lain (Task, Comment, ActivityLog, dst) sudah
 *               ditambah withTrashed() — lihat komentar masing-masing model.
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\User\OnboardUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * BUSINESS RULE: F-170 — rute ini SENGAJA cuma 'auth' (routes/admin.php),
     * bukan can:xxx tunggal, karena halaman gabungan ini kini melayani DUA
     * permission independen (user.manage utk kolom Pengguna, role.manage utk
     * kolom Peran). Otorisasi union (SALAH SATU cukup) dilakukan INLINE di
     * sini, lalu tiap kolom data HANYA dikirim kalau permission-nya dipegang —
     * supaya role yang cuma punya role.manage tidak diam-diam menerima data
     * users (dan sebaliknya) dari satu response yang sama.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $organizationId = $user->organization_id;

        abort_unless($user->can('user.manage') || $user->can('role.manage'), 403);

        return Inertia::render('users/index', [
            // F-172 (permintaan Boss): default 'paling atas = data terbaru' --
            // sebelumnya alfabetis nama.
            'users' => $user->can('user.manage')
                ? User::with('role:id,role_name')
                    ->latest()
                    ->get(['id', 'name', 'email', 'role_id', 'employment_type', 'daily_capacity_minutes', 'is_active'])
                : null,
            // SUMBER (permintaan Boss): query IDENTIK RoleController::index() --
            // SATU sumber bentuk data untuk kolom "Peran" di halaman gabungan ini.
            'roles' => $user->can('role.manage')
                ? Role::where('organization_id', $organizationId)
                    ->withCount('users')
                    ->orderByDesc('is_system')
                    ->orderBy('role_name')
                    ->get(['id', 'role_name', 'is_system', 'is_default'])
                : null,
            // SUMBER: F-92 — flash session diisi store() SEKALI, otomatis kosong
            // lagi di request BERIKUTNYA (perilaku bawaan Session::flash() Laravel)
            // -- itu sebabnya "tampilkan sekali" tidak butuh logic manual di sini.
            'generatedPassword' => session('generatedPassword'),
            'generatedPasswordFor' => session('generatedPasswordFor'),
        ]);
    }

    /**
     * BUSINESS RULE: RBAC §E2 — form onboarding 3-mode butuh daftar role
     * eksisting (mode 1/clone) + katalog permission per module (mode clone/baru).
     */
    public function create(): Response
    {
        $organizationId = Auth::user()->organization_id;

        return Inertia::render('users/create', [
            'roles' => Role::where('organization_id', $organizationId)->orderBy('role_name')->get(['id', 'role_name', 'is_system']),
            'permissions' => Permission::orderBy('module')->orderBy('permission_name')->get(['id', 'permission_name', 'module']),
        ]);
    }

    /**
     * BUSINESS RULE: RBAC §C — SELURUH logika buat-user+role lewat UserService
     * (transaction, C3). Password TIDAK datang dari form (F-92) — di-generate
     * acak di service, di-flash SEKALI ke session supaya halaman berikutnya
     * (redirect ke index) bisa menampilkannya sekali ke admin.
     */
    public function store(OnboardUserRequest $request, UserService $service): RedirectResponse
    {
        $roleConfig = array_filter([
            'role_id' => $request->validated('role_id'),
            'base_role_id' => $request->validated('base_role_id'),
            'new_role_name' => $request->validated('new_role_name'),
            'permissions' => $request->validated('permissions'),
            'custom_permissions' => $request->validated('custom_permissions'),
        ], fn ($value) => ! is_null($value));

        $result = $service->onboardNewUser(
            $request->safe()->only(['name', 'nickname', 'email', 'employment_type', 'daily_capacity_minutes']),
            $roleConfig,
            $request->user(),
        );

        return to_route('users.index')->with([
            'generatedPassword' => $result['password'],
            'generatedPasswordFor' => $result['user']->email,
        ]);
    }

    public function edit(User $user): Response
    {
        $organizationId = Auth::user()->organization_id;

        return Inertia::render('users/edit', [
            'user' => $user->only(['id', 'name', 'nickname', 'email', 'role_id', 'employment_type', 'daily_capacity_minutes']),
            'roles' => Role::where('organization_id', $organizationId)->orderBy('role_name')->get(['id', 'role_name', 'is_system']),
        ]);
    }

    /**
     * BUSINESS RULE: password cuma di-update kalau admin mengisi field-nya
     * (UpdateUserRequest::rules() -> nullable). Kosong = password lama tetap.
     * role_id di sini SELALU assign role EKSISTING (mode 1) — buat role baru
     * lewat UI Role Management (Fase E1), bukan lewat form edit user ini.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->except('password');

        if ($request->validated('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $user->update($data);

        return to_route('users.index');
    }

    /**
     * BUSINESS RULE: F-16 — pengganti "hapus user". SUMBER: 03-BUSINESS-FLOW §7,
     * is_active=false memblokir login TANPA menghapus baris — riwayat KPI (assignee,
     * approver, activity log) tetap utuh selamanya.
     *
     * GUARD: admin tidak boleh menonaktifkan akunnya sendiri — kalau itu satu-satunya
     * admin aktif, dia akan langsung terkunci dari aplikasinya sendiri tanpa jalan
     * masuk lain (tidak ada reset password di v1, 03-BUSINESS-FLOW §7).
     */
    public function toggleActive(User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 403, 'Tidak bisa menonaktifkan akun sendiri.');

        $user->update(['is_active' => ! $user->is_active]);

        return back();
    }

    /**
     * BUSINESS RULE (2026-09-11, keputusan Boss): "fitur hapus akun, bukan hanya
     * nonaktif" — soft delete ($user->delete() mengisi deleted_at, BUKAN hard
     * delete, F-16 tetap ditegakkan). User hilang dari SEMUA listing/dropdown
     * aktif (beda dari toggleActive() yang cuma memblokir login tapi user tetap
     * terlihat di daftar) sampai dipulihkan lewat restore().
     *
     * GUARD: sama pola ProjectController::guardAgainstRemovingMembersWithActiveTasks
     * (F-87) — tolak kalau user masih punya task is_work_state aktif (segmen
     * waktu terbuka, F-38/F-41). Kalau lolos, segmen tidak pernah ditutup dan
     * actual_minutes tak pernah beku (F-39).
     */
    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 403, 'Tidak bisa menghapus akun sendiri.');

        $this->guardAgainstDeletingUserWithActiveTasks($user);

        $user->delete();

        return to_route('users.index');
    }

    /**
     * KONTRAK: kebalikan destroy() — user kembali muncul di seluruh listing
     * aktif. Route dipasang ->withTrashed() (routes/admin.php) supaya route
     * model binding {user} bisa menemukan baris yang deleted_at-nya terisi.
     */
    public function restore(User $user): RedirectResponse
    {
        $user->restore();

        return to_route('users.trash');
    }

    /**
     * KONTRAK: halaman "Sampah User" (permintaan Boss 2026-09-11) — pola SAMA
     * ProjectController::archived(), daftar user deleted_at IS NOT NULL saja.
     */
    public function trashed(): Response
    {
        return Inertia::render('users/trash', [
            'users' => User::onlyTrashed()
                ->with('role:id,role_name')
                ->orderByDesc('deleted_at')
                ->get(['id', 'name', 'email', 'role_id', 'deleted_at']),
        ]);
    }

    /**
     * SUMBER data: task_status_id + task_user pivot — pola IDENTIK
     * ProjectController::guardAgainstRemovingMembersWithActiveTasks (F-87), cuma
     * lintas SEMUA project (bukan satu project) karena hapus akun bersifat global.
     */
    private function guardAgainstDeletingUserWithActiveTasks(User $user): void
    {
        $hasActiveWork = Task::whereHas('assignees', fn ($query) => $query->where('users.id', $user->id))
            ->whereHas('taskStatus', fn ($query) => $query->where('is_work_state', true))
            ->exists();

        if (! $hasActiveWork) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => "User \"{$user->name}\" masih punya task sedang dikerjakan (timer jalan). Selesaikan atau pindahkan assignee dulu sebelum menghapus.",
        ]);
    }
}
