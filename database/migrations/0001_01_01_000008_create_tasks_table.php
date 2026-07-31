<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000008_create_tasks_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Tabel INTI sistem. Wadah seluruh kolom RAW yang jadi dasar KPI (F-37) —
 *               kalau tidak direkam di sini sejak Hari-1, datanya hilang selamanya.
 * DIPANGGIL   : TaskObserver, seluruh service Task (Hari-3, belum dibuat)
 * MEMANGGIL   : organizations, projects, task_templates, task_statuses, users, self (parent_task_id)
 * DATA MASUK  : Form Task CRUD admin-only (F-29), engine recurring (v0.8)
 * DATA KELUAR : task_time_segments (realisasi), activity_logs, dashboard v0.8, scoring v1.5
 * RISIKO      : due_date NOT NULL (F-31) & original_due_date (F-47) adalah pagar metrik
 *               on-time — kalau kolom ini kosong/salah isi, SEMUA task tampak "tepat waktu".
 *               actual_minutes/rejection_count di-FREEZE saat approve (F-39), jangan pernah
 *               di-recompute on-the-fly dari config aktif.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('project_id')->constrained();
            $table->foreignId('task_template_id')->nullable()->constrained(); // asal recurring (F-46)
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks'); // subtask, maks 1 level (F-20)
            $table->foreignId('task_status_id')->constrained();

            $table->string('title', 255);
            $table->longText('description')->nullable(); // rich text HTML (F-30)
            $table->enum('task_type', ['daily', 'weekly', 'monthly', 'tentative', 'project']);
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // RAW — F-37: hilang selamanya kalau tidak direkam saat ini juga.
            $table->smallInteger('points')->default(0);
            $table->smallInteger('estimated_minutes');
            $table->smallInteger('actual_minutes')->nullable(); // FROZEN saat approve (F-39)
            $table->tinyInteger('quality_rating')->nullable(); // 1-5, diisi admin saat approve
            $table->smallInteger('rejection_count')->default(0); // FROZEN saat approve (F-39)

            // F-31: WAJIB, tidak nullable — jantung metrik on-time.
            $table->dateTime('due_date');
            // F-47: jejak due_date SEBELUM extension. Tanpa ini metrik on-time bohong total.
            $table->dateTime('original_due_date')->nullable();

            $table->dateTime('started_at')->nullable(); // pertama kali masuk work-state
            $table->dateTime('completed_at')->nullable(); // F-21
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users'); // F-28: admin

            $table->integer('position')->default(0); // urutan board (v1.0)
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
            $table->softDeletes(); // F-16

            $table->index(['organization_id', 'project_id']);
            $table->index('task_status_id');
            $table->index('due_date');
            $table->index('parent_task_id');
            // DIPAKAI: dashboard beban harian v0.8 (02-DATA-MODEL §5 rumus BEBAN/BACKLOG)
            $table->index(['organization_id', 'due_date', 'task_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
