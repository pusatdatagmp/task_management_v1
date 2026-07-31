<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000004_create_projects_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Level ke-2 hierarki (Org -> Project -> Task -> Subtask, F-8).
 *               Wadah task_statuses custom per project (F-44) dan task_templates.
 * DIPANGGIL   : ProjectController (Hari-2, belum dibuat)
 * MEMANGGIL   : organizations, users (owner)
 * DATA MASUK  : Form CRUD project admin-only (F-29)
 * DATA KELUAR : project_id dipakai tasks, task_statuses, task_templates
 * RISIKO      : F-16 — soft delete wajib, project dengan riwayat task/KPI tidak boleh
 *               hilang permanen kalau admin salah hapus.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5

            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users'); // F-28: admin/owner project
            $table->boolean('is_archived')->default(false);

            $table->timestamps();
            $table->softDeletes(); // F-16
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
