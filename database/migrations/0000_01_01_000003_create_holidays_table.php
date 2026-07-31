<?php

/**
 * ==========================================================
 * MODUL       : 0000_01_01_000003_create_holidays_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Kalender libur nasional/perusahaan. Dibuat Hari-1, DIBIARKAN KOSONG
 *               sampai v0.8 (F-43) — supaya tidak perlu migrasi ulang saat fitur ini digarap.
 * DIPANGGIL   : (belum dipakai — v0.8: perhitungan realisasi F-57)
 * MEMANGGIL   : organizations (FK)
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : Tabel ini TIDAK BOLEH diisi seeder Hari-1 (F-43). Kalau di-skip
 *               pembuatannya sekarang, v0.8 harus migration lagi — dampak kecil tapi
 *               dihindari karena "skema lengkap sejak Hari-1" adalah janji utama (01-PRD F-56).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->date('date');
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['organization_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
