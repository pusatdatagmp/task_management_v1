<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_26_130000_create_tags_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Katalog tag per-organisasi (permintaan Boss 2026-08-26) — dikelola
 *               terpusat di halaman Setelan, dipilih (multi) saat buat/edit Task.
 * DIPANGGIL   : Task::tags(), TagController
 * MEMANGGIL   : organizations (FK)
 * DATA MASUK  : Form tab "Tag" di halaman Setelan
 * DATA KELUAR : Dropdown/picker tag di form Task
 * RISIKO      : SUMBER : F-5 — organization_id sejak baris pertama (pola Holiday).
 *               unique(organization_id, name) mencegah tag duplikat NAMA per
 *               organisasi (tenant lain boleh punya nama tag sama, F-15).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->string('name', 50);
            $table->string('color', 7); // hex, pola sama task_statuses.color
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
