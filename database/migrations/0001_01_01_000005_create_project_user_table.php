<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000005_create_project_user_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Pivot keanggotaan project (ERD §2: USERS }o--o{ PROJECTS).
 *               Menentukan project mana yang boleh dilihat member (F-34/matriks §6).
 * DIPANGGIL   : Project::members(), User::projects()
 * MEMANGGIL   : projects, users
 * DATA MASUK  : Admin assign member ke project (Hari-2)
 * DATA KELUAR : Filter akses "Lihat semua project (admin) vs hanya yang di-assign (member)"
 * RISIKO      : Tanpa UNIQUE, member bisa terdaftar dobel di project yang sama —
 *               query membership jadi tidak bisa diandalkan untuk permission check.
 *
 * CATATAN F-5 : Tabel ini SENGAJA tidak punya organization_id. Pivot murni relasi
 *               project<->user — tenant-nya ikut project_id (yang sudah scoped
 *               organization_id sendiri). Menambah organization_id di sini cuma
 *               data duplikat, bukan pelanggaran F-5. Kalau ragu: JOIN ke projects
 *               untuk dapat organization_id, jangan tambah kolom baru di sini.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user');
    }
};
