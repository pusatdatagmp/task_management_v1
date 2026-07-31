<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_25_100200_create_task_template_checklist_items_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Blueprint checklist di `task_templates` (F-127) — daftar item yang
 *               NANTI disalin ke `task_checklist_items` tiap kali instance recurring
 *               lahir. Tabel CHILD terpisah dari task_checklist_items (bukan reuse)
 *               karena baris di sini adalah TEMPLATE (tanpa is_done — belum ada
 *               instance untuk "selesai"), bukan progres task nyata.
 * DIPANGGIL   : (belum — logika copy-on-generate di GenerateRecurringTasksCommand,
 *               dibangun H5, bersinggungan dengan F-100/F-101/F-102/F-61)
 * MEMANGGIL   : organizations, task_templates
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : Kalau H5 lupa salin baris di sini ke task_checklist_items saat
 *               generate, instance recurring lahir tanpa checklist walau template-nya
 *               sudah diisi admin — silent gap, tidak akan ketahuan dari error.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_template_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('task_template_id')->constrained();

            $table->string('text', 500);
            $table->integer('position')->default(0);

            $table->timestamps();

            $table->index('task_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_template_checklist_items');
    }
};
