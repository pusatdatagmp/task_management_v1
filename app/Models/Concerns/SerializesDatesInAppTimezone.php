<?php

/**
 * ==========================================================
 * MODUL       : SerializesDatesInAppTimezone
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-72 — Eloquent HasAttributes::serializeDate() memanggil
 *               Carbon::toJSON() yang HARDCODED konversi ke UTC, mengabaikan
 *               APP_TIMEZONE maupun Carbon::serializeUsing() global. Trait ini
 *               mem-force serialize tanggal Eloquent (Inertia props, JSON
 *               response) pakai config('app.timezone') = Asia/Jakarta (F-69),
 *               supaya frontend menerima jam yang SAMA dengan yang ada di DB.
 * DIPANGGIL   : SEMUA model bisnis (lihat daftar di CLAUDE.md/PROMPT Hari-4 A2)
 * MEMANGGIL   : Carbon
 * DATA MASUK  : DateTimeInterface dari kolom bertipe datetime/date/timestamp
 * DATA KELUAR : String ISO-8601 dengan offset WIB (+07:00), dikonsumsi frontend
 *               React lewat Inertia props
 * RISIKO      : Model baru yang lupa pasang trait ini akan mengirim tanggal
 *               UTC ke frontend -> tanggal bisa mundur 1 hari (lihat kasus
 *               effective_from Hari-3). Trait, bukan base class, karena User
 *               extends Illuminate\Foundation\Auth\User (Authenticatable),
 *               tidak bisa extend base Model kustom.
 * ==========================================================
 */

namespace App\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;

trait SerializesDatesInAppTimezone
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)
            ->setTimezone(config('app.timezone'))
            ->format('Y-m-d\TH:i:sP');
    }
}
