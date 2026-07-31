<?php

/**
 * ==========================================================
 * MODUL       : 0000_01_01_000001_create_organizations_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Tabel akar multi-tenant. Jangkar F-5 — semua tabel bisnis
 *               menunjuk ke sini sejak baris pertama (persiapan v3.0 freelance marketplace).
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : -
 * DATA MASUK  : -
 * DATA KELUAR : organizations.id dipakai sebagai FK organization_id di seluruh tabel bisnis
 * RISIKO      : Kalau tabel ini salah/telat dibuat, retrofit F-5 di v3.0 = bongkar total DB (02-DATA-MODEL §1)
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
