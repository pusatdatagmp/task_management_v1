<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_09_204747_add_kpi_score_to_tasks_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Kolom skor KPI per-task (F-166/F-167, v1.4 KPI-1) — indikator
 *               ketepatan-waktu TERPISAH dari `points` (F-168, throughput lama
 *               TIDAK diganti). Beku sejak diisi, konsumen (leaderboard KPI-2
 *               nanti) baca kolom ini apa adanya tanpa peduli strategy mana
 *               yang menghitungnya (F-166 — pluggable).
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : -
 * DATA MASUK  : -
 * DATA KELUAR : tasks.kpi_score
 * RISIKO      : NULLABLE, TANPA default paksa — null berarti "belum di-approve"
 *               ATAU "kpi_enabled=false saat approve" (F-166 master toggle),
 *               dua makna yang sama-sama valid, TIDAK dibedakan di kolom ini.
 *               Diisi SEKALI saat approve (TaskTransitionService::approve()) lalu
 *               BEKU permanen (F-167/F-39) — ubah config poin setelahnya TIDAK
 *               menulis ulang nilai lama.
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
            $table->smallInteger('kpi_score')->nullable()->after('quality_rating');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('kpi_score');
        });
    }
};
