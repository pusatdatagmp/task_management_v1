<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_07_102325_widen_tasks_task_type_to_string
 * KLASIFIKASI : DATA
 * TUJUAN      : Permintaan Boss (2026-08-07) — hapus kategori statis
 *               daily/weekly/monthly dari halaman Tugas Berulang, ganti label
 *               ringkasan jadwal custom Automation Engine (mis. "Tiap 3 hari").
 *               `tasks.task_type` sebelumnya ENUM 5 nilai tetap -- tidak bisa
 *               nampung teks bebas itu, jadi dilebarkan jadi VARCHAR.
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : tasks
 * DATA MASUK  : -
 * DATA KELUAR : tasks.task_type jadi VARCHAR(50) NOT NULL (bukan ENUM lagi)
 * RISIKO      : NON-LOSSY -- nilai existing (tentative/project/daily lama) TIDAK
 *               berubah, cuma constraint-nya dilebarkan. Tidak pakai
 *               doctrine/dbal (tidak terpasang di repo ini, F-4: jangan tambah
 *               dependency tanpa approval) -- raw SQL MODIFY COLUMN dipakai
 *               sebagai gantinya, satu-satunya cara Laravel ubah tipe kolom
 *               existing tanpa paket itu. `task_templates.task_type` SENGAJA
 *               TIDAK ikut disentuh migrasi ini -- tetap ENUM lama, dead-tapi-
 *               aman untuk jalur rollback F-162 (GenerateRecurringTasksCommand).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tasks MODIFY task_type VARCHAR(50) NOT NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tasks MODIFY task_type ENUM('daily','weekly','monthly','tentative','project') NOT NULL");
    }
};
