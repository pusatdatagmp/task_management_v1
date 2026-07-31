<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_18_100500_drop_role_enum_from_users_table
 * KLASIFIKASI : DATA
 * TUJUAN      : RBAC (F-88) — migration KETIGA (terpisah dari add role_id) yang
 *               menghapus enum `role` lama. Aturan induk: TIDAK BOLEH enum & RBAC
 *               hidup berdampingan — begitu role_id terbukti terisi (migration
 *               sebelumnya), satu-satunya sumber kebenaran izin HARUS role_id.
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : users
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : SUMBER : migration destruktif (DROP kolom) — CLAUDE.md §4 melarang
 *               ini tanpa konfirmasi+backup, TAPI di sini eksplisit di-approve
 *               Boss lewat urutan Fase A yang diminta (A4: "migration ketiga
 *               men-drop enum") dan alasan tertulis di registry F-88: "enum→role_id
 *               butuh migrate:fresh, gratis mumpung data kosong". Kalau migration
 *               ini pernah dijalankan di database dengan data user NYATA (bukan
 *               migrate:fresh), WAJIB pastikan migration backfill
 *               (2026_07_18_100400) sudah jalan & `role_id` terisi 100% dulu —
 *               kolom `role` yang di-drop di sini TIDAK BISA dikembalikan
 *               (down() cuma restore struktur kolom, BUKAN nilai lamanya).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * KONTRAK: rollback struktur SAJA — kolom `role` kembali ada (default 'member'),
     * tapi nilai lama yang sudah di-drop TIDAK bisa direkonstruksi dari role_id
     * (role_name bisa "Supervisor" custom, bukan cuma admin/member). down() ini
     * cuma untuk memulihkan skema, BUKAN jalur pemulihan data.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'member'])->default('member')->after('password');
        });
    }
};
