<?php

/**
 * ==========================================================
 * MODUL       : UserService
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : SATU-SATUNYA jalur membuat user baru + role-nya (RBAC §C, F-91).
 *               Admin-only, BUKAN self-signup (register tetap mati, F-91). Semua
 *               onboarding — assign role eksisting, clone role jadi baru, atau
 *               role baru dari kosong — lewat sini, supaya user TIDAK PERNAH
 *               tersimpan tanpa role (F-89) walau salah satu langkah gagal.
 * DIPANGGIL   : UserController::store()
 * MEMANGGIL   : User, Role, Permission
 * DATA MASUK  : $userData (name/email/employment_type/daily_capacity_minutes dari
 *               form), $roleConfig (3 bentuk, lihat resolveRole()), $actor (admin
 *               yang melakukan onboarding — sumber organization_id & created_by)
 * DATA KELUAR : User baru + password acak PLAINTEXT (SEKALI, dipakai controller
 *               untuk ditampilkan sekali ke admin — TIDAK disimpan di mana pun)
 * RISIKO      : SUMBER : F-92 — hash password SINKRON lewat cast bawaan
 *               ('password' => 'hashed' di User::casts()), BUKAN job/queue.
 *               Blueprint asli menyebut "asinkron" — itu KELIRU teknis, sengaja
 *               TIDAK diikuti (dicatat di registry F-92).
 *               SELURUH proses WAJIB dalam DB::transaction() (C3) — kalau resolveRole()
 *               gagal di tengah (nama role bentrok, dst), User::create() TIDAK
 *               PERNAH tereksekusi. Tidak ada skenario "user tersimpan, role gagal".
 * ==========================================================
 */

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * KONTRAK: buat user baru + role-nya dalam SATU transaction (C3). Return
     * User (relasi `role` sudah di-load) dan password plaintext SEKALI —
     * pemanggil (controller) bertanggung jawab menampilkannya SEKALI lalu
     * membuangnya (flash session, bukan kolom DB manapun).
     *
     * @param  array{name: string, email: string, employment_type?: string, daily_capacity_minutes?: int|null}  $userData
     * @param  array{role_id?: int}|array{base_role_id: int, new_role_name: string, custom_permissions: int[]}|array{new_role_name: string, permissions: int[]}  $roleConfig
     * @return array{user: User, password: string}
     */
    public function onboardNewUser(array $userData, array $roleConfig, User $actor): array
    {
        return DB::transaction(function () use ($userData, $roleConfig, $actor) {
            $role = $this->resolveRole($roleConfig, $actor);

            // BUSINESS RULE: F-92 — password acak, TIDAK diminta admin ketik manual.
            // Str::password() (bukan Str::random()) supaya sudah memenuhi aturan
            // kompleksitas default Laravel — sama seperti ProductionSeeder (Hari-7).
            $plainPassword = Str::password(20);

            $user = User::create([
                'organization_id' => $actor->organization_id,
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($plainPassword),
                'role_id' => $role->id,
                'employment_type' => $userData['employment_type'] ?? 'internal',
                'daily_capacity_minutes' => $userData['daily_capacity_minutes'] ?? null,
                'is_active' => true,
            ]);

            return ['user' => $user->load('role'), 'password' => $plainPassword];
        });
    }

    /**
     * KONTRAK: C4 — tiga bentuk payload role, percabangan TERPUSAT di sini
     * (bukan tersebar di controller):
     *  1. `role_id` ada           -> assign role EKSISTING (harus di organisasi actor)
     *  2. `base_role_id` + `new_role_name` + `custom_permissions[]`
     *                              -> role BARU, permission = custom_permissions
     *                                 (base_role_id dipakai sebagai referensi titik
     *                                 mulai di UI Fase E, BUKAN otomatis di-merge —
     *                                 permission akhir PERSIS custom_permissions
     *                                 yang dikirim, supaya tidak ada permission
     *                                 "nempel diam-diam" dari base yang tidak
     *                                 sengaja dicentang admin)
     *  3. `new_role_name` + `permissions[]` -> role BARU dari kosong
     *
     * Mode 2 & 3 sama-sama membuat role baru; bedanya cuma titik referensi awal
     * di UI. Keduanya lewat createRole() yang sama supaya validasi C6 (nama
     * duplikat/bentrok role sistem/karakter aneh) satu tempat, tidak dua kali.
     */
    private function resolveRole(array $roleConfig, User $actor): Role
    {
        if (array_key_exists('role_id', $roleConfig)) {
            return Role::where('organization_id', $actor->organization_id)
                ->findOrFail($roleConfig['role_id']);
        }

        if (array_key_exists('base_role_id', $roleConfig)) {
            // GUARD: base_role_id WAJIB ada di organisasi actor — bukan validasi
            // kosmetik, mencegah admin "meminjam" ID role organisasi lain sebagai
            // referensi (F-15). Hasilnya TIDAK dipakai lebih lanjut (lihat kontrak
            // di atas) selain memastikan ID ini memang milik organisasi ini.
            Role::where('organization_id', $actor->organization_id)->findOrFail($roleConfig['base_role_id']);

            return $this->createRole($roleConfig['new_role_name'], $roleConfig['custom_permissions'], $actor);
        }

        if (array_key_exists('new_role_name', $roleConfig)) {
            return $this->createRole($roleConfig['new_role_name'], $roleConfig['permissions'], $actor);
        }

        throw ValidationException::withMessages([
            'role' => 'Konfigurasi role tidak valid — pilih role eksisting atau buat role baru.',
        ]);
    }

    /**
     * KONTRAK: C6 — buat role custom baru (is_system=false SELALU, admin tidak
     * bisa membuat role sistem baru lewat jalur ini). PUBLIC — dipakai juga oleh
     * RoleController::store() (Fase E1, UI Role Management berdiri sendiri,
     * bukan cuma dari alur onboarding user) supaya validasi C6 satu tempat.
     */
    public function createRole(string $roleName, array $permissionIds, User $actor): Role
    {
        $this->validateNewRoleName($roleName, $actor->organization_id);

        $role = Role::create([
            'organization_id' => $actor->organization_id,
            'role_name' => $roleName,
            'is_system' => false,
            'is_default' => false,
            'created_by' => $actor->id,
        ]);

        // GUARD: whereIn — permission_id yang dikirim tapi tidak ada di katalog
        // (mis. typo/manipulasi request) diam-diam DIABAIKAN, bukan bikin baris
        // role_permission ke ID yang tidak eksis (FK constraint akan menolak,
        // tapi lebih baik dicegah di sini dengan pesan yang jelas kalau nol valid).
        $validPermissionIds = Permission::whereIn('id', $permissionIds)->pluck('id');
        $role->permissions()->sync($validPermissionIds);

        return $role;
    }

    /**
     * KONTRAK: C6 — cegah 3 kelas nama role tidak valid:
     *  1. Duplikat DALAM organisasi yang sama (UNIQUE constraint jaga di DB,
     *     tapi validasi di sini supaya pesan error jelas, bukan SQL exception mentah)
     *  2. Bentrok nama role SISTEM ('admin'/'member') — role custom tidak boleh
     *     menyamar sebagai role sistem, membingungkan fresh entry yang baca log
     *  3. Karakter aneh — hanya huruf/angka/spasi/strip, cegah nama yang bisa
     *     rusak tampilan UI Role Management (Fase E) atau celah lain
     */
    private function validateNewRoleName(string $roleName, int $organizationId): void
    {
        $trimmed = trim($roleName);

        if ($trimmed === '' || ! preg_match('/^[\p{L}\p{N} \-]+$/u', $trimmed)) {
            throw ValidationException::withMessages([
                'new_role_name' => 'Nama role hanya boleh huruf, angka, spasi, dan strip.',
            ]);
        }

        if (in_array(mb_strtolower($trimmed), ['admin', 'member'], true)) {
            throw ValidationException::withMessages([
                'new_role_name' => 'Nama role tidak boleh sama dengan role sistem (admin/member).',
            ]);
        }

        $exists = Role::where('organization_id', $organizationId)
            ->whereRaw('LOWER(role_name) = ?', [mb_strtolower($trimmed)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'new_role_name' => "Role \"{$trimmed}\" sudah ada di organisasi ini.",
            ]);
        }
    }
}
