<?php

/**
 * ==========================================================
 * MODUL       : Decision
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Value object hasil SATU evaluasi template (F-158/F-159 poin 3) —
 *               backbone observability Automation Engine v1.3. Setiap Guard,
 *               Strategy, Resolver, dan Action berbicara lewat objek ini, BUKAN
 *               exception/return code campur aduk.
 * DIPANGGIL   : Pipeline, seluruh Guard/Strategy/Action, RunAutomationEngineCommand
 *               (baca Decision -> tulis 1 baris automation_run_log)
 * MEMANGGIL   : Carbon (target_date)
 * DATA MASUK  : action (guard/strategy/action mana pun yang menghentikan/meloloskan)
 * DATA KELUAR : automation_run_log.action/reason/target_date/delta_days/meta
 * RISIKO      : `action` HARUS salah satu dari enum kolom automation_run_log
 *               (generate|skip|shift|error) — kalau meleset, insert log gagal
 *               constraint enum dan run_log kehilangan barisnya (observability bolong).
 * ==========================================================
 */

namespace App\Services\Automation;

use Carbon\Carbon;

final class Decision
{
    public function __construct(
        public readonly string $action,
        public readonly ?string $reason = null,
        public readonly ?Carbon $targetDate = null,
        public readonly array $meta = [],
        public readonly ?int $deltaDays = null,
    ) {}

    public static function skip(string $reason, ?Carbon $targetDate = null, array $meta = []): self
    {
        return new self('skip', $reason, $targetDate, $meta);
    }

    public static function error(string $reason, array $meta = []): self
    {
        return new self('error', $reason, null, $meta);
    }

    public static function generated(Carbon $targetDate, array $meta = []): self
    {
        return new self('generate', null, $targetDate, $meta);
    }

    /**
     * BUSINESS RULE F-153/§8.5: dipakai HolidayShiftResolver menemukan target_date
     * BUKAN hari evaluasi (digeser lewat libur/weekend). Task tetap DIBUAT (sama
     * seperti 'generate') — 'shift' murni label observability supaya riwayat run
     * bisa membedakan "lahir hari ini" vs "digeser maju", bukan jalur eksekusi beda.
     */
    public static function shifted(Carbon $targetDate, array $meta = []): self
    {
        return new self('shift', 'digeser-libur-atau-weekend', $targetDate, $meta);
    }

    /**
     * KONTRAK: tempel delta_days (F-151 Time-Delta) ke Decision APA PUN hasil
     * akhirnya (skip/generate/shift/error) — dipanggil SEKALI oleh Pipeline
     * supaya kolom delta_days automation_run_log konsisten terisi tanpa tiap
     * Guard/Strategy/Action harus tahu cara menghitungnya sendiri-sendiri.
     */
    public function withDeltaDays(?int $deltaDays): self
    {
        return new self($this->action, $this->reason, $this->targetDate, $this->meta, $deltaDays);
    }
}
