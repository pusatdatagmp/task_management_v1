<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_04_100300_migrate_legacy_recurrence_to_automation_columns
 * KLASIFIKASI : DATA
 * TUJUAN      : Migrasi DATA (bukan skema) template existing ke kolom Automation
 *               Engine baru (F-159 poin 4) — supaya AE-2 nanti punya interval_value/
 *               interval_unit terisi tanpa Boss input ulang tiap template lama.
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : task_templates.task_type (kolom lama, F-121 — TETAP ADA, sumber
 *               migrasi ini, BUKAN target hapus)
 * DATA MASUK  : task_templates.task_type existing: 'daily'|'weekly'|'monthly'
 * DATA KELUAR : task_templates.anchor_strategy='time_based', interval_value=1,
 *               interval_unit diturunkan 1:1 dari task_type (daily->day, weekly->week,
 *               monthly->month)
 * RISIKO      : SUMBER F-159 poin 4 — engine lama HANYA punya notion time_based
 *               (tidak ada completion-based di skema lama), jadi SEMUA template lama
 *               anchor_strategy='time_based' adalah pemetaan yang benar, bukan tebakan.
 *               `last_generated_date` (kolom lama, F-61) SENGAJA TIDAK disentuh migrasi
 *               ini — kolom itu sudah representasi akurat "kapan terakhir digenerate"
 *               dari engine lama, rekonstruksi ulang dari tabel `tasks` cuma menambah
 *               risiko salah tanpa menambah akurasi.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['daily' => 'day', 'weekly' => 'week', 'monthly' => 'month'] as $taskType => $intervalUnit) {
            DB::table('task_templates')
                ->where('task_type', $taskType)
                ->update([
                    'anchor_strategy' => 'time_based',
                    'interval_value' => 1,
                    'interval_unit' => $intervalUnit,
                ]);
        }
    }

    public function down(): void
    {
        // Reversible: kembalikan interval_value/interval_unit ke null (state sebelum
        // migrasi ini). anchor_strategy dibiarkan 'time_based' — itu DEFAULT kolom
        // sejak migration skema (FASE A), bukan data yang ditambahkan migration ini.
        DB::table('task_templates')->update([
            'interval_value' => null,
            'interval_unit' => null,
        ]);
    }
};
