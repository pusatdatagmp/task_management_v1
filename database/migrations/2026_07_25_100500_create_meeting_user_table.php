<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_25_100500_create_meeting_user_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Pivot peserta meeting (F-124). Kolom `id` SURROGATE ditambah
 *               walau tidak diminta eksplisit di prompt H2 — pola SAMA persis
 *               dengan `task_user`/`project_user` (lihat migration masing-masing):
 *               pivot custom butuh id sendiri supaya event created/deleted Eloquent
 *               benar-benar terpicu, dipakai observer notifikasi "diundang meeting"
 *               (H6, kategori kolaborasi F-114, BUKAN lifecycle F-35). DEVIASI dari
 *               spec literal, dilaporkan ke Jarvis — bukan keputusan sepihak diam-diam.
 * DIPANGGIL   : (belum — Meeting::participants() model H6)
 * MEMANGGIL   : meetings, users
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : Tanpa `id` sendiri, kalau H6 butuh pivot model custom (pola
 *               TaskUser/ProjectUser) untuk memicu observer, migration ini harus
 *               diubah lagi — mumpung masih skema kosong, lebih murah sekarang.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained();
            $table->foreignId('user_id')->constrained();

            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_user');
    }
};
