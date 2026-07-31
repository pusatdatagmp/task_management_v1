<?php

/**
 * ==========================================================
 * MODUL       : 0000_01_01_000002_create_work_schedules_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Jendela kerja perusahaan (F-40) — sumber KAPASITAS harian untuk
 *               rumus IDLE_PLAN/IDLE_REAL (01-PRD §6, 02-DATA-MODEL §5).
 * DIPANGGIL   : WorkSchedule::active() (02-DATA-MODEL §3.2)
 * MEMANGGIL   : organizations (FK)
 * DATA MASUK  : Form pengaturan jam kerja admin (Hari-2, belum dibuat)
 * DATA KELUAR : daily_capacity_minutes dipakai dashboard v0.8, task_time_segments overlap calc
 * RISIKO      : SUMBER : F-40 — kolom ini VERSIONED. Baris lama TIDAK PERNAH di-update,
 *               ubah setting = INSERT baris baru dengan effective_from baru. Kalau nekat
 *               UPDATE baris lama, config task yang sudah selesai (frozen di F-39) ikut
 *               berubah retroaktif — merusak dasar penilaian yang sudah dibayar (F-3 legal).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5

            $table->date('effective_from');
            $table->json('days_of_week'); // SUMBER: keputusan Boss, isi ISO [1..7] 1=Senin. F-42.
            $table->time('start_time')->default('08:00:00');
            $table->time('end_time')->default('17:00:00');
            $table->smallInteger('daily_capacity_minutes')->default(480); // F-42: beda dari (end-start), bisa ada jam istirahat

            // F-54: FK ke users TIDAK dipasang di sini — tabel users belum ada saat
            // migration ini jalan (circular dependency). FK constraint ditambah di
            // migration terpisah setelah create_users_table (lihat add_created_by_foreign_to_work_schedules_table).
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
