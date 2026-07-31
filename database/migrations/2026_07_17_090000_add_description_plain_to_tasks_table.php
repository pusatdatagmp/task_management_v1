<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_17_090000_add_description_plain_to_tasks_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Kolom hasil strip-HTML dari description rich text (F-79) — sumber
 *               yang benar untuk FULLTEXT search, karena description asli berisi
 *               tag HTML (F-30) yang ikut terindeks kalau dipakai langsung.
 * DIPANGGIL   : TaskObserver::saving() (isi kolom ini), TaskController::search() (F-7)
 * MEMANGGIL   : tasks (harus sudah ada)
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : Kolom NULL sampai TaskObserver mengisi ulang lewat save() berikutnya.
 *               v0.5 masih data seeder -> migrate:fresh --seed cukup untuk backfill,
 *               TIDAK butuh script backfill terpisah (B5).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->longText('description_plain')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('description_plain');
        });
    }
};
