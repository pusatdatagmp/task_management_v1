<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_14_100000_add_priority_quadrant_to_task_templates_table
 * KLASIFIKASI : DATA
 * TUJUAN      : F-175 — Template Recurring belum punya kolom Eisenhower quadrant
 *               (p1-p4) yang sudah dipakai `tasks` sejak F-122/F-126. Akibatnya
 *               task hasil GenerateTaskAction selalu priority_quadrant NULL,
 *               tampil "Belum diklasifikasi" di halaman Semua Tugas walau
 *               template-nya sudah diisi prioritas (permintaan Boss 2026-08-14).
 * DIPANGGIL   : (belum — form task-templates & GenerateTaskAction disambungkan
 *               di perubahan yang sama)
 * MEMANGGIL   : task_templates (harus sudah ada)
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : Default NULL (bukan 'p4') — sama alasan migration `tasks`:
 *               template lama yang belum diisi harus tampak "belum diklasifikasi",
 *               bukan seolah sudah dinilai rendah.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->enum('priority_quadrant', ['p1', 'p2', 'p3', 'p4'])
                ->nullable()
                ->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropColumn('priority_quadrant');
        });
    }
};
