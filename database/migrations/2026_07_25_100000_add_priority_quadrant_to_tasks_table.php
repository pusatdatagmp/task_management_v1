<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_25_100000_add_priority_quadrant_to_tasks_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Integrasi mockup v1.7 — Eisenhower quadrant (Penting x Mendesak)
 *               dipakai UI donut/sort dashboard (F-122/F-126). Kolom TERPISAH dari
 *               `priority` enum lama (low/normal/high/urgent) — enum lama TETAP ADA
 *               di DB, cuma disembunyikan dari UI (legacy internal), bukan dihapus.
 * DIPANGGIL   : (belum — form task & dashboard sort disambungkan H4/H5)
 * MEMANGGIL   : tasks (harus sudah ada)
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : Default NULL, bukan 'p4' — task lama/baru yang belum diklasifikasi
 *               Eisenhower harus tampak "belum diisi", bukan seolah sudah dinilai
 *               rendah. Kalau default 'p4', 259+ task existing akan terlihat sudah
 *               diklasifikasi padahal belum — data palsu untuk dashboard donut.
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
            $table->enum('priority_quadrant', ['p1', 'p2', 'p3', 'p4'])
                ->nullable()
                ->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('priority_quadrant');
        });
    }
};
