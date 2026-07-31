<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000006_create_task_statuses_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Status task custom per project dengan TIGA FLAG (F-44) — logika sistem
 *               bergantung pada flag, BUKAN nama status, karena admin bebas rename.
 * DIPANGGIL   : TaskObserver (cek is_work_state/is_review/is_completed), validasi transisi F-45
 * MEMANGGIL   : organizations, projects
 * DATA MASUK  : Seeder 4 status default per project (TODO/IN PROGRESS/REVIEW/DONE)
 * DATA KELUAR : task_status_id di tasks, position dipakai validasi transisi berurutan (F-45)
 * RISIKO      : SUMBER : 02-DATA-MODEL §3.7 — JANGAN PERNAH hardcode
 *               `if (status.name == 'IN PROGRESS')` di mana pun kode ini dipakai.
 *               Admin rename status = logika yang hardcode nama langsung rusak diam-diam.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('project_id')->constrained();

            $table->string('name', 50);
            $table->string('color', 7); // hex, mis. #3b82f6
            $table->smallInteger('position'); // urutan — dipakai validasi transisi F-45

            // F-44: TIGA FLAG — jangan pernah diganti jadi nama status di kode.
            $table->boolean('is_work_state')->default(false); // counter jalan (F-41)
            $table->boolean('is_review')->default(false); // antrian approval admin (F-28)
            $table->boolean('is_completed')->default(false); // final, freeze angka (F-39)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_statuses');
    }
};
