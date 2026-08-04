<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_04_100000_add_automation_columns_to_task_templates_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Skema Automation Engine v1.3 (F-151/158/161) — kolom guard/strategy
 *               disiapkan sekarang, dibaca AE-2. HARI INI cuma skema, nol logika.
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : task_templates (kolom lama TIDAK disentuh — F-121, engine lama masih hidup)
 * DATA MASUK  : -
 * DATA KELUAR : task_templates dengan 8 kolom baru, semua nullable/default aman —
 *               baris existing otomatis valid tanpa backfill manual di migration ini
 *               (backfill nilai turunan dari task_type lama ada di migration data
 *               terpisah, F-159 poin 4).
 * RISIKO      : `last_generated_date` & `is_active` SUDAH ADA sejak Hari-1 (F-61) —
 *               SENGAJA TIDAK ditambah ulang di sini (akan crash "duplicate column").
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            // F-161: Strategy pattern key — TimeBased(A)/CompletionBased(B)/CalendarAnchored(C).
            // Default time_based supaya migrasi data (FASE D) template lama konsisten.
            $table->enum('anchor_strategy', ['time_based', 'completion_based', 'calendar_anchored'])
                ->default('time_based')
                ->after('is_active');

            // F-151: pasangan TimeDeltaGuard — dipakai strategy time_based/completion_based.
            $table->unsignedInteger('interval_value')->nullable()->after('anchor_strategy');
            $table->enum('interval_unit', ['day', 'week', 'month'])->nullable()->after('interval_value');

            // F-161: CalendarAnchored(C) — hari tetap, mis. {"day_of_month":1} atau {"day_of_week":1}.
            $table->json('anchor_config')->nullable()->after('interval_unit');

            // F-161: DateWindowGuard — batasi hari/tanggal boleh generate, mis.
            // {"weekdays":[1,2,3,4,5],"dom_min":1,"dom_max":25}.
            $table->json('date_window_config')->nullable()->after('anchor_config');

            // F-161: QuotaGuard — maks N instance belum-selesai. Null = tak terbatas.
            $table->unsignedInteger('max_active_instances')->nullable()->after('date_window_config');

            // F-154: Anchor Opsi B (completion-based) deadlock tracking — kapan mulai
            // ter-block nunggu instance sebelumnya selesai. Null = tidak sedang block.
            $table->date('blocked_since')->nullable()->after('max_active_instances');

            // F-154/F-159 poin 2: anti-spam — notif block SEKALI saat blocked_since
            // di-set, bukan tiap run cron.
            $table->dateTime('last_block_notified_at')->nullable()->after('blocked_since');
        });
    }

    public function down(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropColumn([
                'anchor_strategy',
                'interval_value',
                'interval_unit',
                'anchor_config',
                'date_window_config',
                'max_active_instances',
                'blocked_since',
                'last_block_notified_at',
            ]);
        });
    }
};
