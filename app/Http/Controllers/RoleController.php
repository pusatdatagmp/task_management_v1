<?php

/**
 * ==========================================================
 * MODUL       : RoleController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : UI Role Management (RBAC §E1) — daftar/buat/edit/hapus role +
 *               tandai role default. Permission user.manage (routes/admin.php).
 * DIPANGGIL   : routes/admin.php
 * MEMANGGIL   : Role, Permission, UserService::createRole() (C6, dipakai ulang)
 * DATA MASUK  : Form Role Baru/Edit Role
 * DATA KELUAR : Inertia pages 'roles/*'
 * RISIKO      : SUMBER : destroy() TOLAK role sistem (tidak bisa dihapus SELAMANYA,
 *               F-88) DAN role yang masih dipakai user (pola F-19 — "masih ada N
 *               user, pindahkan dulu"). update() TOLAK melucuti user.manage dari
 *               role sistem kalau dia SATU-SATUNYA pemegang — tanpa guard ini,
 *               organisasi bisa terkunci total dari halaman kelola user/role
 *               sendiri (tidak ada jalan masuk lain, F-91 tidak ada self-signup).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $organizationId = Auth::user()->organization_id;

        return Inertia::render('roles/index', [
            'roles' => Role::where('organization_id', $organizationId)
                ->withCount('users')
                ->orderByDesc('is_system')
                ->orderBy('role_name')
                ->get(['id', 'role_name', 'is_system', 'is_default']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('roles/create', [
            'permissions' => Permission::orderBy('module')->orderBy('permission_name')->get(['id', 'permission_name', 'module']),
        ]);
    }

    /**
     * BUSINESS RULE: RBAC §E1 — role baru SELALU custom (is_system=false),
     * lewat UserService::createRole() yang sama dipakai onboarding (C6, satu
     * tempat validasi nama).
     */
    public function store(StoreRoleRequest $request, UserService $service): RedirectResponse
    {
        $service->createRole(
            $request->validated('role_name'),
            $request->validated('permissions'),
            $request->user(),
        );

        return to_route('roles.index');
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions:id');

        return Inertia::render('roles/edit', [
            'role' => $role->only(['id', 'role_name', 'is_system', 'is_default']),
            'permissionIds' => $role->permissions->pluck('id'),
            'permissions' => Permission::orderBy('module')->orderBy('permission_name')->get(['id', 'permission_name', 'module']),
            // SUMBER: E1 — kalau true, checkbox user.manage WAJIB disabled di form
            // (dijelaskan ke admin KENAPA, bukan cuma dikunci diam-diam). Dihitung
            // di server supaya tidak lomba dengan Role::wouldLeaveNoHolderOfPermission
            // yang sama persis dipakai update() untuk penegakan asli.
            'isLastUserManageHolder' => $role->hasPermission('user.manage')
                && Role::wouldLeaveNoHolderOfPermission($role->organization_id, 'user.manage', $role->id),
        ]);
    }

    /**
     * BUSINESS RULE: E1 — nama HANYA berubah untuk role custom (role sistem
     * read-only, "tidak bisa dihapus/rename"). Permission bisa diedit untuk
     * KEDUA jenis role, TAPI role sistem punya lantai minimum (lihat guard di
     * bawah) — mencegah organisasi terkunci dari kelola user/role selamanya.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role) {
            if (! $role->is_system && $request->validated('role_name')) {
                $role->update(['role_name' => $request->validated('role_name')]);
            }

            $permissionIds = collect($request->validated('permissions'));
            $userManageId = Permission::where('permission_name', 'user.manage')->value('id');
            $isDroppingUserManage = $userManageId && ! $permissionIds->contains($userManageId);

            if ($isDroppingUserManage && Role::wouldLeaveNoHolderOfPermission($role->organization_id, 'user.manage', $role->id)) {
                throw ValidationException::withMessages([
                    'permissions' => "Permission 'user.manage' tidak bisa dilepas dari role ini — ini satu-satunya role yang bisa kelola user/role di organisasi. Beri role lain user.manage dulu sebelum melepasnya dari sini.",
                ]);
            }

            $role->permissions()->sync($permissionIds);
        });

        return to_route('roles.index');
    }

    /**
     * BUSINESS RULE: E1 — TOLAK kalau role sistem (F-88, tidak bisa dihapus
     * SELAMANYA) atau masih dipakai user (pola F-19 — pindahkan dulu, bukan
     * cascade/null otomatis, supaya admin sadar konsekuensinya).
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => 'Role sistem (admin/member) tidak bisa dihapus.',
            ]);
        }

        $userCount = $role->users()->count();

        if ($userCount > 0) {
            throw ValidationException::withMessages([
                'role' => "Masih ada {$userCount} user dengan role ini. Pindahkan dulu.",
            ]);
        }

        $role->delete();

        return to_route('roles.index');
    }

    /**
     * BUSINESS RULE: E3 — pola RADIO (F-74): set SEMUA role di organisasi ini
     * is_default=false dulu, baru set 1 yang dipilih true, dalam SATU
     * transaction. Tidak pernah ada state "0 default" atau "2 default" di
     * antara dua langkah, karena tidak pernah ada langkah antara yang terlihat.
     */
    public function setDefault(Role $role): RedirectResponse
    {
        DB::transaction(function () use ($role) {
            Role::where('organization_id', $role->organization_id)->update(['is_default' => false]);
            $role->update(['is_default' => true]);
        });

        return back();
    }
}
