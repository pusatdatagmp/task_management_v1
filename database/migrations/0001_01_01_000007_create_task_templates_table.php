<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000007_create_task_templates_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Blueprint recurring task (F-46). Skema disiapkan Hari-1, ENGINE generator
 *               baru digarap v0.8 — tabel ini di Hari-1 hanya diisi seeder, belum aktif jalan.
 * DIPANGGIL   : (belum dipakai — v0.8: scheduler harian generate instance tasks)
 * MEMANGGIL   : organizations, projects
 * DATA MASUK  : Seeder 3 template (daily/weekly/monthly), is_active=true, belum generate
 * DATA KELUAR : tasks.task_template_id menunjuk balik ke sini saat instance lahir (v0.8)
 * RISIKO      : SUMBER : 02-DATA-MODEL §3.8 — last_generated_date WAJIB ada sejak sekarang
 *               walau belum dipakai (idempotency guard v0.8). Tanpa ini scheduler jalan 2x
 *               = task duplikat = budget harian ganda = dashboard bohong (F-61).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('project_id')->constrained();

            $table->string('title', 255);
            $table->longText('description')->nullable();

            // BUSINESS RULE: hanya 3 tipe ini yang berulang. 'tentative' & 'project'
            // TIDAK punya template, dibuat manual admin (02-DATA-MODEL §3.8).
            $table->enum('task_type', ['daily', 'weekly', 'monthly']);

            $table->smallInteger('estimated_minutes');
            $table->smallInteger('points');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // SUMBER: bentuk json beda per task_type — {"day_of_week":1} atau {"day_of_month":25}.
            $table->json('recurrence_config');
            $table->json('default_assignees'); // array of user_id

            $table->boolean('is_active')->default(true);
            $table->date('last_generated_date')->nullable(); // F-61: idempotency guard

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};
