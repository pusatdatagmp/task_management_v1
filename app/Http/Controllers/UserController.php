<?php

/**
 * ==========================================================
 * MODUL       : UserController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : CRUD user/member + onboarding RBAC (F-90/F-91). Satu-satunya
 *               jalur menambah akun tim — self-signup DIMATIKAN
 *               (03-BUSINESS-FLOW §7). Gerbang permission `user.manage`
 *               (routes/admin.php), bukan lagi middleware 'admin' blanket.
 * DIPANGGIL   : routes/admin.php (index/create/store/edit/update/toggleActive)
 * MEMANGGIL   : User, UserService (onboarding — RBAC §C)
 * DATA MASUK  : Form buat/edit user, form onboarding 3-mode (Fase E2)
 * DATA KELUAR : Inertia pages 'users/*', flash session `generatedPassword` (SEKALI)
 * RISIKO      : SUMBER : F-16 — TIDAK ADA destroy(). Nonaktifkan HANYA lewat
 *               toggleActive() (is_active=false), riwayat task/KPI milik user tetap
 *               utuh. Hard delete user akan menghapus jejak assignee/approver di
 *               riwayat KPI — dilarang keras.
 *               F-92 — password TIDAK PERNAH diketik admin lagi (store()); dibuat
 *               acak oleh UserService, ditampilkan SEKALI via flash session, tidak
 *               disimpan plaintext di mana pun setelah response ini.
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\User\OnboardUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('users/index', [
            'users' => User::with('role:id,role_name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role_id', 'employment_type', 'daily_capacity_minutes', 'is_active']),
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
            $request->safe()->only(['name', 'email', 'employment_type', 'daily_capacity_minutes']),
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
            'user' => $user->only(['id', 'name', 'email', 'role_id', 'employment_type', 'daily_capacity_minutes']),
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
}
