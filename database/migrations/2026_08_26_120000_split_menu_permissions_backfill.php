<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_26_120000_split_menu_permissions_backfill
 * KLASIFIKASI : DATA
 * TUJUAN      : F-170 (audit permission per-menu, permintaan Boss 2026-08-26) —
 *               insert 4 permission baru (role.manage/holiday.manage/
 *               tasktemplate.manage/extension.approve, lihat RolePermissionSeeder)
 *               DAN backfill role EXISTING supaya tidak ada yang kehilangan akses
 *               saat deploy: role yang SUDAH pegang permission induk (user.manage/
 *               workschedule.manage/task.manage/task.approve) otomatis ikut dapat
 *               permission anak yang baru menggantikannya untuk menu tsb.
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : RolePermissionSeeder::seedPermissionCatalog(), tabel role_permission
 * DATA MASUK  : -
 * DATA KELUAR : Baris baru di permissions + role_permission (SEMUA organisasi)
 * RISIKO      : SUMBER : additive murni — TIDAK ADA UPDATE/DELETE terhadap baris
 *               role_permission lama (F-16 spirit). insertOrIgnore aman dipanggil
 *               ulang (idempotent, unique constraint role_permission mencegah
 *               duplikat). down() menghapus SELURUH baris utk 4 permission baru
 *               ini (role_permission + permissions) — aman karena baris ini
 *               sepenuhnya lahir dari migration ini, tidak pernah ada sebelumnya.
 * ==========================================================
 */

use App\Models\Permission;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * KONTRAK: pasangan permission_name (induk => anak) yang di-split F-170.
     * Menu ini SEBELUMNYA reuse permission induk dari resource lain — lihat
     * komentar RolePermissionSeeder::catalog() untuk rationale lengkap per baris.
     */
    private const SPLIT_PAIRS = [
        'user.manage' => 'role.manage',
        'workschedule.manage' => 'holiday.manage',
        'task.manage' => 'tasktemplate.manage',
        'task.approve' => 'extension.approve',
    ];

    public function up(): void
    {
        RolePermissionSeeder::seedPermissionCatalog();

        foreach (self::SPLIT_PAIRS as $parentName => $childName) {
            $parentId = Permission::where('permission_name', $parentName)->value('id');
            $childId = Permission::where('permission_name', $childName)->value('id');

            $roleIds = DB::table('role_permission')->where('permission_id', $parentId)->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $childId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $childNames = array_values(self::SPLIT_PAIRS);

        DB::table('role_permission')
            ->whereIn('permission_id', Permission::whereIn('permission_name', $childNames)->pluck('id'))
            ->delete();

        Permission::whereIn('permission_name', $childNames)->delete();
    }
};
