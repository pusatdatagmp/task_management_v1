<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_01_110000_add_theme_config_to_organizations_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Kolom custom tema (F-143, v1.2 DS-3) — override token warna +
 *               gradasi per-org. ADITIF (F-121) — kolom branding DS-2 tak
 *               disentuh. JSON (BUKAN kolom per-token seperti branding, F-5) --
 *               tema SELALU dibaca/ditulis sebagai SATU config utuh (token+
 *               gradient), nol kebutuhan query per-field, pola sama
 *               task_templates.recurrence_config yang sudah ada di codebase.
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : -
 * DATA MASUK  : -
 * DATA KELUAR : organizations.theme_config (json, nullable)
 * RISIKO      : NULL = org belum pernah kustom tema -- FRONTEND yang render
 *               fallback default TEMPO (F-145), DB/backend TIDAK dipaksa isi
 *               default palsu ke kolom ini (pola sama branding DS-2).
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
            $table->json('theme_config')->nullable()->after('linkedin_url');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('theme_config');
        });
    }
};
