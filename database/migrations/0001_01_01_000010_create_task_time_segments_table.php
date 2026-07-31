<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000010_create_task_time_segments_table
 * KLASIFIKASI : DATA
 * TUJUAN      : JANTUNG REALISASI (F-41) — satu-satunya sumber jam kerja aktual.
 *               BUKAN activity_logs (rapuh, harus parsing json).
 * DIPANGGIL   : TaskObserver (insert saat masuk work-state, update ended_at saat keluar)
 * MEMANGGIL   : organizations, tasks, users
 * DATA MASUK  : Transisi status task (otomatis via observer, F-22 — bukan input manual)
 * DATA KELUAR : Realisasi = Σ overlap(started_at, ended_at, work_schedule) → freeze ke
 *               tasks.actual_minutes saat approve (F-39)
 * RISIKO      : SUMBER : F-38 — ended_at NULL berarti SEDANG BERJALAN, dihitung sampai
 *               min(now, end_time hari ini) saat ditanya. JANGAN simpan "durasi berjalan"
 *               sebagai state — scheduler mati/cron telat bikin counter korup permanen,
 *               timestamp tidak bisa korup. F-48: maks 1 segmen terbuka per task, dijaga
 *               di level aplikasi (observer), bukan constraint DB.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_time_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('task_id')->constrained();
            $table->foreignId('user_id')->constrained(); // siapa yang kerja

            $table->dateTime('started_at'); // masuk work-state
            $table->dateTime('ended_at')->nullable(); // NULL = sedang berjalan (F-38)
            $table->timestamp('created_at')->useCurrent();

            $table->index('task_id');
            $table->index(['organization_id', 'user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_time_segments');
    }
};
