<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_01_100000_add_branding_to_organizations_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Kolom custom branding (F-142, v1.2 DS-2) — ganti "Laravel Starter
 *               Kit"/nama hardcode dengan identitas org sungguhan. ADITIF (F-121) —
 *               `name`/`slug` lama TIDAK disentuh, `company_name` SENGAJA terpisah
 *               dari `name` (name = identitas tenant internal, belum pernah dipakai
 *               di UI manapun; company_name = display branding, bisa beda kapan pun
 *               tanpa mengubah identitas tenant).
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : -
 * DATA MASUK  : -
 * DATA KELUAR : organizations.{logo_path,company_name,address,wa_number,
 *               facebook_url,instagram_url,linkedin_url} — semua nullable (org
 *               existing belum wajib isi branding, fallback default TEMPO di UI)
 * RISIKO      : Semua kolom NULLABLE tanpa default paksa — F-68-style, JANGAN
 *               diam-diam diisi default di model, biarkan NULL berarti "belum
 *               diisi Boss" dan fallback ditangani di FRONTEND (bukan di DB).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('slug');
            $table->string('company_name', 150)->nullable()->after('logo_path');
            $table->string('address', 500)->nullable()->after('company_name');
            $table->string('wa_number', 20)->nullable()->after('address');
            $table->string('facebook_url')->nullable()->after('wa_number');
            $table->string('instagram_url')->nullable()->after('facebook_url');
            $table->string('linkedin_url')->nullable()->after('instagram_url');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path', 'company_name', 'address', 'wa_number',
                'facebook_url', 'instagram_url', 'linkedin_url',
            ]);
        });
    }
};
