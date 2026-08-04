<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_04_100100_add_period_key_and_unique_index_to_tasks_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Idempotency key Automation Engine (F-61/F-159 poin 1) — kunci
 *               (task_template_id, period_key) supaya cron dobel tak generate ganda.
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : tasks.task_template_id — REUSE kolom FK yang SUDAH ADA sejak Hari-1
 *               (0001_01_01_000008_create_tasks_table, dipakai GenerateRecurringTasksCommand
 *               lama). Boss putuskan REUSE ini, BUKAN bikin kolom `template_id` baru
 *               (draf SPEK v1.3 §2 pakai nama generik "template_id" — di skema nyata
 *               kolomnya sudah bernama task_template_id, 2 FK ke tabel sama = ambigu).
 * DATA MASUK  : -
 * DATA KELUAR : tasks.period_key (string, tanggal periode terjadwal — F-159 poin 1),
 *               unique index (task_template_id, period_key) siap dibaca AE-2 saat generate.
 * RISIKO      : Unique index NULLABLE-SAFE by design — MySQL menganggap tiap NULL
 *               unik satu sama lain, jadi task manual (task_template_id & period_key
 *               keduanya NULL) tidak pernah tabrakan index ini. Index ini HANYA
 *               menjaga instance hasil generate (kedua kolom terisi).
 *               🔴 down() WAJIB pasang index tunggal `task_template_id` DULU sebelum
 *               drop index composite: InnoDB diam-diam MEMBUANG index auto-generated
 *               lama yang menopang FK (dari migration 0001_01_01_000008) begitu index
 *               composite ini dibuat (composite jadi satu-satunya penopang FK). Tanpa
 *               index pengganti, drop composite gagal "1553 needed in a foreign key
 *               constraint" — dibuktikan lewat migrate:rollback nyata, bukan tebakan.
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
            // F-159 poin 1: string, bukan date — konsisten dengan format readable
            // "2026-07-28" yang disepakati, dan bebas dipakai non-tanggal kelak
            // (mis. nomor periode) tanpa migrasi tipe kolom lagi.
            $table->string('period_key', 20)->nullable()->after('task_template_id');

            $table->unique(['task_template_id', 'period_key'], 'tasks_template_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Lihat RISIKO di header — index pengganti WAJIB ada sebelum composite
            // di-drop, supaya FK task_template_id (dari migration Hari-1) tetap
            // punya index penopang.
            $table->index('task_template_id', 'tasks_task_template_id_index');
            $table->dropUnique('tasks_template_period_unique');
            $table->dropColumn('period_key');
        });
    }
};
