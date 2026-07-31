<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000003_add_created_by_foreign_to_work_schedules_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Menutup circular dependency F-54 — work_schedules.created_by baru
 *               dipasangi FK constraint di sini, setelah tabel users benar-benar ada.
 * DIPANGGIL   : -
 * MEMANGGIL   : work_schedules, users
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : Kalau migration ini dijalankan sebelum create_users_table, FK gagal
 *               (tabel referensi belum ada). Urutan file HARUS setelah users (F-54).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });
    }
};
