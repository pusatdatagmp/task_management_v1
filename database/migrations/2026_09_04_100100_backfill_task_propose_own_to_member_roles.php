<?php

/**
 * ==========================================================
 * MODUL       : 2026_09_04_100100_backfill_task_propose_own_to_member_roles
 * KLASIFIKASI : DATA
 * TUJUAN      : F-186 — insert permission task.proposeOwn DAN assign ke SEMUA role
 *               member (is_system=true) yang sudah ada di tiap organisasi, supaya
 *               org yang sudah di-seed sebelum fitur ini tidak kehilangan akses.
 *               Pola IDENTIK 2026_08_26_120000_split_menu_permissions_backfill.
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : RolePermissionSeeder::seedPermissionCatalog(), tabel roles/role_permission
 * DATA MASUK  : -
 * DATA KELUAR : Baris baru permissions (task.proposeOwn) + role_permission (role
 *               member SEMUA organisasi)
 * RISIKO      : Additive murni — insertOrIgnore aman dipanggil ulang (idempotent,
 *               unique constraint role_permission cegah duplikat). down() menghapus
 *               permission ini total (role_permission + permissions) — aman karena
 *               baris ini sepenuhnya lahir dari migration ini.
 * ==========================================================
 */

use App\Models\Permission;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        RolePermissionSeeder::seedPermissionCatalog();

        $permissionId = Permission::where('permission_name', 'task.proposeOwn')->value('id');

        $memberRoleIds = DB::table('roles')
            ->where('role_name', 'member')
            ->where('is_system', true)
            ->pluck('id');

        foreach ($memberRoleIds as $roleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_permission')
            ->where('permission_id', Permission::where('permission_name', 'task.proposeOwn')->value('id'))
            ->delete();

        Permission::where('permission_name', 'task.proposeOwn')->delete();
    }
};
