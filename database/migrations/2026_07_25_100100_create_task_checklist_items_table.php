<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_25_100100_create_task_checklist_items_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Checklist dalam-tugas (F-123) — BEDA dari subtask (F-20, tabel
 *               `tasks` self-relation). Ini daftar item ringan (text+is_done) di
 *               dalam satu task, dipakai gate transisi ->REVIEW (F-127, DIBANGUN
 *               H5 — hari ini CUMA skema, gate/logika wajib BELUM ditegakkan).
 * DIPANGGIL   : (belum — TaskTransitionService gate & UI checklist disambungkan H5)
 * MEMANGGIL   : organizations, tasks
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : Makna "checklist wajib" masih F-127 TERBUKA (gate-only vs setiap
 *               task wajib >=1 item) — skema ini SAMA untuk kedua tafsir, resolusi
 *               tidak mengubah tabel, cuma validasi di H5.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('task_id')->constrained();

            $table->string('text', 500);
            $table->boolean('is_done')->default(false);
            $table->integer('position')->default(0);

            $table->timestamps();

            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_checklist_items');
    }
};
