<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_25_100300_add_goal_and_due_date_to_projects_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Integrasi mockup v1.7 — modal detail proyek ("Goal Proyek") dan
 *               kolom Dateline di tabel Status Project (F-125). `status`
 *               (todo/aktif/selesai) SENGAJA TIDAK ditambah di sini — DITURUNKAN
 *               dari agregasi task (F-125), bukan kolom tersimpan. `is_archived`
 *               lama TETAP terpisah (aksi manual admin, tidak disentuh).
 * DIPANGGIL   : (belum — form proyek & tabel Status Project disambungkan H3/H4)
 * MEMANGGIL   : projects (harus sudah ada)
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : `due_date` di sini bertipe DATE (bukan datetime) — beda dari
 *               `tasks.due_date` yang datetime. Proyek tidak butuh presisi jam
 *               (mockup cuma tampilkan tanggal di kolom Dateline), task butuh
 *               (F-31/F-47 metrik on-time). JANGAN disamakan tipenya.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('goal')->nullable()->after('description');
            $table->date('due_date')->nullable()->after('goal');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['goal', 'due_date']);
        });
    }
};
