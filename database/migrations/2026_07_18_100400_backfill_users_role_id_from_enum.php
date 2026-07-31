<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_18_100400_backfill_users_role_id_from_enum
 * KLASIFIKASI : DATA
 * TUJUAN      : RBAC (F-88) — jembatan enum lama -> role_id baru (A5). Pakai
 *               DB::table() MENTAH (bukan model Eloquent Role/User) SENGAJA —
 *               model Role belum ada saat migration ini ditulis (baru lahir di
 *               Fase B), dan migration TIDAK BOLEH bergantung pada kode app yang
 *               bisa berubah nanti (konvensi migration Laravel).
 * DIPANGGIL   : php artisan migrate (berjalan otomatis, urutan timestamp)
 * MEMANGGIL   : users (baca `role`/`organization_id`, tulis `role_id`), roles (tulis)
 * DATA MASUK  : Kolom `users.role` enum (admin/member) — SUMBER data lama
 * DATA KELUAR : `users.role_id` terisi, 2 baris `roles` (is_system=true) per organisasi
 * RISIKO      : SUMBER : self-contained SENGAJA — bikin role sistem kalau BELUM
 *               ada per organisasi (bukan asumsi seeder sudah jalan duluan).
 *               Di `migrate:fresh` (jalur yang dipakai hari ini, tabel users KOSONG
 *               saat migration ini jalan), loop di bawah nol iterasi — no-op aman.
 *               Baru berguna nyata kalau migration ini dijalankan (BUKAN fresh) di
 *               database yang SUDAH punya user dengan enum terisi.
 *               `is_default=true` diberi ke role 'member' (bukan 'admin') — SUMBER:
 *               default paling aman (least-privilege) untuk user baru tanpa role
 *               eksplisit, konsisten dengan `enum('role',...)->default('member')`
 *               di migration users asli (0001_01_01_000000).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $organizationIds = DB::table('users')->select('organization_id')->distinct()->pluck('organization_id');

        foreach ($organizationIds as $organizationId) {
            foreach (['admin', 'member'] as $roleName) {
                $roleId = DB::table('roles')
                    ->where('organization_id', $organizationId)
                    ->where('role_name', $roleName)
                    ->value('id');

                if (! $roleId) {
                    $roleId = DB::table('roles')->insertGetId([
                        'organization_id' => $organizationId,
                        'role_name' => $roleName,
                        'is_system' => true,
                        'is_default' => $roleName === 'member',
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('users')
                    ->where('organization_id', $organizationId)
                    ->where('role', $roleName)
                    ->update(['role_id' => $roleId]);
            }
        }
    }

    /**
     * KONTRAK: best-effort — kosongkan lagi role_id yang migration ini isi.
     * Baris `roles` (is_system) SENGAJA TIDAK dihapus di sini — migration
     * berikutnya (role_permission, Fase F seeder) mungkin sudah bergantung
     * padanya; hapus paksa di down() berisiko FK constraint error / data hilang
     * yang tidak perlu untuk sekadar rollback kolom.
     */
    public function down(): void
    {
        DB::table('users')
            ->whereIn('role_id', DB::table('roles')->where('is_system', true)->pluck('id'))
            ->update(['role_id' => null]);
    }
};
