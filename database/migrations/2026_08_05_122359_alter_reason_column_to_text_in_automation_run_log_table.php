<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_05_122359_alter_reason_column_to_text_in_automation_run_log_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Harden AE-3 (pelajaran AE-2) — `automation_run_log.reason` varchar(255)
 *               TERBUKTI kepotong saat Decision::error() menyimpan pesan
 *               QueryException PENUH (bisa menyertakan SQL utuh), sampai INSERT
 *               log itu SENDIRI gagal constraint (1406 Data too long) -- 1 template
 *               gagal jadi merusak SELURUH run, kebalikan dari F-160 isolasi.
 *               Str::limit(250) di RunAutomationEngineCommand tetap dipertahankan
 *               sebagai sabuk kedua (defense-in-depth), TEXT ini sabuk pertama.
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : automation_run_log.reason (raw SQL ALTER -- doctrine/dbal TIDAK
 *               terpasang di project ini, ->change() Blueprint butuh dependency
 *               itu; ALTER manual MySQL langsung menghindari nambah dependency
 *               baru tanpa approval, CLAUDE.md §4)
 * DATA MASUK  : -
 * DATA KELUAR : automation_run_log.reason: varchar(255) nullable -> text nullable
 * RISIKO      : Aditif murni (F-121 spirit) — memperlebar kapasitas kolom, TIDAK
 *               mengubah data existing atau kolom lain. down() mengembalikan ke
 *               varchar(255) -- HANYA aman kalau rollback dilakukan sebelum ada
 *               baris reason > 255 char tersimpan (kalau ada, MySQL akan menolak
 *               downgrade dengan data truncation error, bukan diam-diam memotong).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE automation_run_log MODIFY reason TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE automation_run_log MODIFY reason VARCHAR(255) NULL');
    }
};
