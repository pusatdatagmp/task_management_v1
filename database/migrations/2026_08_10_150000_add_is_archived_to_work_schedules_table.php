<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_10_150000_add_is_archived_to_work_schedules_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Permintaan Boss (2026-08-10, audit F-40) -- fitur "arsip manual"
 *               untuk versi Jam Kerja yang BELUM PERNAH aktif (effective_from
 *               masih di masa depan). BUKAN pelanggaran F-40 -- versi yang
 *               SUDAH PERNAH aktif (masa lalu/sekarang) TETAP terkunci permanen,
 *               kolom ini hanya dipakai WorkScheduleController::archive()/update()
 *               yang men-GUARD ketat effective_from>hari ini (lihat controller).
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : -
 * DATA MASUK  : -
 * DATA KELUAR : work_schedules.is_archived
 * RISIKO      : default false -- baris LAMA (Hari-1 seeder dst) semuanya
 *               otomatis TIDAK diarsipkan, nol dampak ke WorkSchedule::active()
 *               yang sudah berjalan.
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
            $table->boolean('is_archived')->default(false)->after('daily_capacity_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn('is_archived');
        });
    }
};
