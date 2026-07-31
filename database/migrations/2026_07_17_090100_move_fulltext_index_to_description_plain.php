<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_17_090100_move_fulltext_index_to_description_plain
 * KLASIFIKASI : DATA
 * TUJUAN      : Pindah FULLTEXT index dari (title, description) ke (title,
 *               description_plain) — F-79. Index lama mengindeks HTML mentah
 *               (tag & entity ikut jadi token pencarian), index baru mengindeks
 *               teks bersih hasil strip di TaskObserver.
 * DIPANGGIL   : Search MATCH AGAINST title, description_plain (03-BUSINESS-FLOW §10)
 * MEMANGGIL   : tasks (kolom description_plain harus sudah ada — migration terpisah sebelumnya)
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : SUMBER : F-24 — FULLTEXT butuh raw DB::statement, migration terpisah.
 *               F-67 — guard driver EXCLUDE sqlite (bukan INCLUDE 'mysql'), karena
 *               driver 'mariadb' terpisah dari 'mysql' di Laravel 11+ — INCLUDE yang
 *               salah membuat index diam-diam tidak dibuat, search mati tanpa error.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['sqlite'])) {
            DB::statement('ALTER TABLE tasks DROP INDEX fulltext_index');
            DB::statement('ALTER TABLE tasks ADD FULLTEXT fulltext_index (title, description_plain)');
        }
    }

    public function down(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['sqlite'])) {
            DB::statement('ALTER TABLE tasks DROP INDEX fulltext_index');
            DB::statement('ALTER TABLE tasks ADD FULLTEXT fulltext_index (title, description)');
        }
    }
};
