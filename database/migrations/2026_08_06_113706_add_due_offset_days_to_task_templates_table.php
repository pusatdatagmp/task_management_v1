<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_06_113706_add_due_offset_days_to_task_templates_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Revisi Boss 2026-08-06 item 7 — tugas berulang SEBELUM ini lahir
 *               SUDAH jatuh tempo di hari yang sama (GenerateTaskAction: due_date =
 *               target_date, nol jarak/buffer). Kolom ini kasih admin kontrol
 *               "berapa HARI KERJA setelah muncul, tugas ini jatuh tempo".
 * DIPANGGIL   : GenerateTaskAction::execute() (baca), TaskTemplateController::store()/
 *               update() (tulis, form Template)
 * MEMANGGIL   : -
 * DATA MASUK  : Form Tugas Berulang (task-templates/create.tsx & edit.tsx)
 * DATA KELUAR : Task.due_date instance baru (dihitung BusinessHoursCalculator::addBusinessDays())
 * RISIKO      : nullable, default NULL = perilaku LAMA TIDAK BERUBAH (due_date =
 *               target_date, sama hari) — template existing sebelum migrasi ini
 *               TIDAK terpengaruh sama sekali (F-78, aditif murni, nol backfill).
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
            $table->unsignedSmallInteger('due_offset_days')->nullable()->after('recurrence_config');
        });
    }

    public function down(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropColumn('due_offset_days');
        });
    }
};
