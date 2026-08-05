<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_05_141832_remap_legacy_weekly_monthly_to_calendar_anchored_strategy
 * KLASIFIKASI : DATA
 * TUJUAN      : Migrasi DATA korektif (F-163) — migrasi AE-1 (2026_08_04_100300)
 *               menurunkan SEMUA template legacy (termasuk weekly/monthly) ke
 *               `anchor_strategy='time_based'`. Itu SALAH untuk weekly/monthly:
 *               hari-tetap (mis. "tiap Senin"/"tgl 1") ada di `recurrence_config`
 *               (day_of_week/day_of_month), tapi TimeDeltaGuard+time_based murni
 *               aditif (last_generated + interval) — TIDAK membaca hari-tetap
 *               sama sekali. Sekali miss-run terjadi, catch-up mendarat di hari
 *               SEMBARANG dan anchor bergeser PERMANEN dari situ (F-163).
 *               FIX: weekly/monthly -> `calendar_anchored` (baca hari-tetap dari
 *               anchor_config, re-anchor OTOMATIS tiap evaluasi -- self-heal dari
 *               drift apa pun). daily TETAP `time_based` (F-163 eksplisit — tidak
 *               ada hari-tetap untuk daily, nol drift, mengubahnya jadi
 *               calendar_anchored justru SALAH/tak bermakna).
 * DIPANGGIL   : php artisan migrate
 * MEMANGGIL   : task_templates.recurrence_config (SUMBER day_of_week/day_of_month,
 *               kolom lama F-121, TIDAK disentuh/dihapus)
 * DATA MASUK  : task_templates WHERE task_type IN (weekly,monthly) AND
 *               anchor_strategy='time_based' (SENGAJA filter ini -- lihat RISIKO)
 * DATA KELUAR : task_templates.anchor_strategy='calendar_anchored',
 *               anchor_config={day_of_week:N} / {day_of_month:N}.
 *               interval_value/interval_unit TIDAK diubah (TETAP terisi dari
 *               migrasi AE-1) -- TimeDeltaGuard tetap jalan duluan di guard chain
 *               (Active->TimeDelta->DateWindow->Quota->Anchor) SEBELUM
 *               CalendarAnchoredStrategy dievaluasi; interval_unit NULL di sini
 *               akan meledak UnhandledMatchError di TimeDeltaGuard begitu
 *               last_generated_date terisi (template lama SUDAH pernah generate).
 * RISIKO      : Filter `anchor_strategy='time_based'` MEMBUAT MIGRASI INI
 *               IDEMPOTENT SEKALIGUS AMAN dijalankan ulang KAPAN PUN setelah
 *               Boss mengkonfigurasi ulang template lewat form AE-2b (FASE C) --
 *               run kedua tidak menemukan baris weekly/monthly yang masih
 *               time_based (baik karena sudah dikoreksi migrasi ini, atau sudah
 *               diubah manual Boss lewat form) -> no-op, TIDAK menimpa pilihan
 *               sadar Boss. DB dev saat ini KOSONG dari template weekly/monthly
 *               nyata -> migrasi ini no-op di dev (dibuktikan lewat test dengan
 *               data buatan, pola sama AutomationEngineSchemaTest::E2), TAPI
 *               logikanya benar untuk data produksi nanti.
 *               down() TIDAK bisa membedakan "calendar_anchored karena migrasi
 *               ini" vs "calendar_anchored karena Boss pilih sendiri lewat form"
 *               -- sama seperti migrasi F-159 poin 4 (down()-nya juga blanket-
 *               reset), down() di sini HANYA aman dipakai SEGERA setelah up()
 *               (sebelum Boss sempat mengubah apa pun lewat form).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['weekly' => 'day_of_week', 'monthly' => 'day_of_month'] as $taskType => $configKey) {
            DB::table('task_templates')
                ->where('task_type', $taskType)
                ->where('anchor_strategy', 'time_based')
                ->get(['id', 'recurrence_config'])
                ->each(function ($row) use ($configKey) {
                    $recurrenceConfig = json_decode((string) $row->recurrence_config, true) ?? [];

                    DB::table('task_templates')->where('id', $row->id)->update([
                        'anchor_strategy' => 'calendar_anchored',
                        'anchor_config' => json_encode([$configKey => (int) ($recurrenceConfig[$configKey] ?? 1)]),
                    ]);
                });
        }
    }

    public function down(): void
    {
        // Lihat RISIKO di header -- reversible HANYA kalau dijalankan segera
        // setelah up(), sebelum Boss sempat mengubah konfigurasi manual.
        DB::table('task_templates')
            ->whereIn('task_type', ['weekly', 'monthly'])
            ->where('anchor_strategy', 'calendar_anchored')
            ->update([
                'anchor_strategy' => 'time_based',
                'anchor_config' => null,
            ]);
    }
};
