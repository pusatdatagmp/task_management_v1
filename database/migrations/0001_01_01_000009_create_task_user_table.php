<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000009_create_task_user_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Pivot multi-assignee (ERD §2: USERS }o--o{ TASKS).
 * DIPANGGIL   : Task::assignees(), User::tasks()
 * MEMANGGIL   : tasks, users
 * DATA MASUK  : Admin assign task ke 1+ user (F-29)
 * DATA KELUAR : Notifikasi assign/unassign (F-35 trigger #1,#2)
 * RISIKO      : F-63 (BELUM DIPUTUSKAN) — kalau task multi-assignee, realisasi/poin
 *               dibagi atau digandakan untuk tiap orang belum ada aturannya. Tidak
 *               berdampak ke skema pivot ini (tidak butuh kolom rasio tambahan Hari-1),
 *               tapi berdampak langsung ke rumus KPI v1.5.
 *
 * CATATAN F-5 : Tabel ini SENGAJA tidak punya organization_id. Pivot murni relasi
 *               task<->user — tenant-nya ikut task_id (yang sudah scoped
 *               organization_id sendiri). Menambah organization_id di sini cuma
 *               data duplikat, bukan pelanggaran F-5. Kalau ragu: JOIN ke tasks
 *               untuk dapat organization_id, jangan tambah kolom baru di sini.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_user');
    }
};
