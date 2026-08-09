<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_07_120000_add_submitted_at_to_tasks_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Keputusan Boss 2026-08-07 — metrik "telat submit" TIDAK BOLEH
 *               tergantung kapan admin approve (F-39 blind spot lama: LeaderboardService
 *               memakai approved_at, jadi member yang submit tepat waktu bisa ikut
 *               tercatat telat kalau admin lambat approve). Kolom ini jadi patokan baru.
 * DIPANGGIL   : TaskTransitionService::submit() (tulis), LeaderboardService::forPeriod() (baca)
 * MEMANGGIL   : -
 * DATA MASUK  : -
 * DATA KELUAR : tasks.submitted_at
 * RISIKO      : Diisi SEKALI SAJA saat task PERTAMA masuk status is_review (keputusan
 *               Boss: submit pertama, BUKAN submit terakhir, jadi patokan telat/tidak —
 *               revisi berkali-kali tidak menggeser nilai ini). nullable, default NULL =
 *               task lama (sebelum migrasi ini) fallback ke approved_at (lihat
 *               LeaderboardService RISIKO) supaya histori tidak pecah.
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
            $table->dateTime('submitted_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
