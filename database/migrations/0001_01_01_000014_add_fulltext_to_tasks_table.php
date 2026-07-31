<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000014_add_fulltext_to_tasks_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Index FULLTEXT untuk search (F-7) — MySQL/MariaDB native, BUKAN
 *               Elasticsearch/Algolia (keputusan arsitektur, biaya & skala 10 user).
 * DIPANGGIL   : Search MATCH AGAINST title, description (03-BUSINESS-FLOW §10)
 * MEMANGGIL   : tasks (harus sudah ada — migration ini WAJIB jalan setelah create_tasks_table)
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : SUMBER : F-24 — Schema builder Laravel tidak native mendukung FULLTEXT,
 *               jadi pakai raw DB::statement. WORKAROUND ini satu-satunya cara di migration.
 *               F-67 — guard driver pakai EXCLUDE (bukan INCLUDE 'mysql'): Laravel 11+
 *               punya driver 'mariadb' terpisah dari 'mysql'. Kalau guard include hanya
 *               'mysql', ganti driver di .env ke MariaDB membuat FULLTEXT diam-diam TIDAK
 *               dibuat — search mati tanpa error. Daftar exclude harus eksplisit; daftar
 *               include akan selalu ketinggalan saat ada driver baru.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WORKAROUND: phpunit.xml memaksa DB_CONNECTION=sqlite (in-memory) untuk
        // testing bawaan Laravel — konvensi standar supaya test suite cepat. SQLite
        // tidak kenal sintaks FULLTEXT MySQL/MariaDB, jadi index ini di-skip HANYA
        // untuk sqlite (F-67 — exclude eksplisit, bukan include 'mysql' yang rapuh
        // terhadap driver 'mariadb' terpisah di Laravel 11+).
        if (! in_array(Schema::getConnection()->getDriverName(), ['sqlite'])) {
            DB::statement('ALTER TABLE tasks ADD FULLTEXT fulltext_index (title, description)');
        }
    }

    public function down(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['sqlite'])) {
            DB::statement('ALTER TABLE tasks DROP INDEX fulltext_index');
        }
    }
};
