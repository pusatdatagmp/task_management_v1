<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_09_204747_add_kpi_config_to_organizations_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Config org-level sistem KPI (F-166, v1.4 KPI-1) — pola SAMA
 *               branding/tema (F-142/F-143, add_branding_to_organizations_table):
 *               kolom langsung di `organizations`, bukan tabel settings terpisah
 *               (proyek ini tidak punya tabel settings generik).
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : -
 * DATA MASUK  : -
 * DATA KELUAR : organizations.{kpi_enabled,kpi_strategy,kpi_points_ontime,
 *               kpi_points_late,kpi_points_notdone}
 * RISIKO      : Default poin (5/3/0) SESUAI blueprint §14.2 -- admin BOLEH
 *               override lewat Setelan (KPI-2, belum dibangun di migrasi ini).
 *               `kpi_strategy` default 'simple_timeliness' HARUS match key yang
 *               didaftar KpiStrategyRegistry -- kalau tidak match, resolve()
 *               melempar UnhandledMatchError saat approve (pola F-160, sengaja
 *               tidak ditelan diam-diam).
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
            $table->boolean('kpi_enabled')->default(true)->after('theme_config');
            $table->string('kpi_strategy', 50)->default('simple_timeliness')->after('kpi_enabled');
            $table->smallInteger('kpi_points_ontime')->default(5)->after('kpi_strategy');
            $table->smallInteger('kpi_points_late')->default(3)->after('kpi_points_ontime');
            $table->smallInteger('kpi_points_notdone')->default(0)->after('kpi_points_late');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['kpi_enabled', 'kpi_strategy', 'kpi_points_ontime', 'kpi_points_late', 'kpi_points_notdone']);
        });
    }
};
