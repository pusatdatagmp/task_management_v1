<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_18_100300_add_role_id_to_users_table
 * KLASIFIKASI : DATA
 * TUJUAN      : RBAC (F-88/F-89) — jangkar 1 role per user. NULLABLE dulu (bukan
 *               langsung NOT NULL) supaya migrasi bertahap aman: kolom `role`
 *               enum lama TETAP ADA & TETAP DIBACA sampai migration backfill
 *               (2026_07_18_100400) mengisi role_id untuk SEMUA baris, baru
 *               migration drop-enum (2026_07_18_100500) yang menghapus kolom lama.
 * DIPANGGIL   : User::role() (Fase B2)
 * MEMANGGIL   : roles
 * DATA MASUK  : Migration backfill, UserService::onboardNewUser (Fase C)
 * DATA KELUAR : User::role_id dipakai seluruh cek permission (F-90)
 * RISIKO      : SUMBER : F-89 — SATU role per user (`role_id`), BUKAN pivot
 *               many-to-many. Kalau v3.0 nanti butuh multi-role, itu RETROFIT
 *               sadar (dicatat di F-89), bukan dibangun sekarang.
 *               nullable() SENGAJA — NOT NULL constraint baru masuk akal setelah
 *               migration backfill memastikan NOL baris tersisa dengan role_id
 *               kosong (grep DoD: `User::whereNull('role_id')->count() -> 0`).
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
            $table->foreignId('role_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
