// ==========================================================
// MODUL       : use-live-counter
// KLASIFIKASI : STATE
// TUJUAN      : Tick counter live di klien TANPA menghitung business-hours sendiri
//               (F-94/F-72/F-76) — server (LiveTaskCounter, F-57/F-66/F-43) sudah
//               kirim snapshot akurat + kapan berhenti (window_ends_at). Hook ini
//               cuma menambah selisih wall-clock sejak snapshot dimuat, dan BERHENTI
//               di window_ends_at — aritmetika tanggal biasa, bukan aturan bisnis.
// DIPANGGIL   : components/task-live-counter.tsx
// MEMANGGIL   : -
// DATA MASUK  : LiveCounterData (props dari TaskController::show()/myTasks()/index())
// DATA KELUAR : total menit (number) yang menick tiap detik, atau null kalau task
//               tidak sedang punya segmen terbuka milik user login
// RISIKO      : F-38 — NOL polling/refetch di sini. setInterval HANYA mengubah state
//               LOKAL (jam berapa sekarang menurut klien), tidak pernah memanggil
//               server. Kalau server mati, tick tetap jalan dari snapshot terakhir.
// ==========================================================

import { useEffect, useRef, useState } from 'react';

export interface LiveCounterData {
    accumulated_minutes: number;
    is_in_work_window: boolean;
    /** ISO datetime WIB (format Y-m-d\TH:i:sP dari Carbon::serializeUsing, F-69), atau null. */
    window_ends_at: string | null;
    /** ISO datetime WIB — informasi tampilan ("sejak jam X"), TIDAK dipakai di tick math. */
    segment_started_at: string;
}

/**
 * KONTRAK: fungsi PURE (gampang diuji, C2) — total menit yang ditampilkan pada
 * momen `now`, berdasar snapshot `data` yang dimuat pada `loadedAt`.
 *
 * - `is_in_work_window` false -> statis, kembalikan `accumulated_minutes` apa adanya
 *   (paused, A2).
 * - `is_in_work_window` true  -> tambah selisih waktu sejak `loadedAt`, DICAP ke
 *   `window_ends_at` (kalau `now` sudah lewat itu, berhenti tepat di situ — bukan
 *   terus lanjut ke wall-clock mentah).
 */
export function computeDisplayMinutes(data: LiveCounterData, loadedAt: Date, now: Date): number {
    if (!data.is_in_work_window) {
        return data.accumulated_minutes;
    }

    const windowEndsAt = data.window_ends_at ? new Date(data.window_ends_at) : null;
    const effectiveNow = windowEndsAt && now.getTime() > windowEndsAt.getTime() ? windowEndsAt : now;

    const elapsedMinutes = Math.max(0, effectiveNow.getTime() - loadedAt.getTime()) / 60_000;

    return data.accumulated_minutes + elapsedMinutes;
}

/** SUMBER: "1j 23m" kalau >=1 jam, "23m" kalau di bawah 1 jam — konsisten B1. */
export function formatLiveMinutes(totalMinutes: number): string {
    const whole = Math.floor(totalMinutes);
    const hours = Math.floor(whole / 60);
    const minutes = whole % 60;

    return hours > 0 ? `${hours}j ${minutes}m` : `${minutes}m`;
}

/**
 * KONTRAK: hook React tipis di atas computeDisplayMinutes() — menick tiap detik
 * SELAMA is_in_work_window true, berhenti sendiri (clearInterval) begitu tidak lagi
 * relevan. `loadedAt` di-reset ke waktu klien SAAT `data` berubah (page baru
 * dimuat/props di-refresh Inertia setelah aksi status, F-38 A3), bukan disimpan
 * lintas render.
 */
export function useLiveCounter(data: LiveCounterData | null): number | null {
    const [now, setNow] = useState(() => new Date());
    const loadedAtRef = useRef(new Date());

    useEffect(() => {
        loadedAtRef.current = new Date();
        setNow(new Date());
    }, [data]);

    useEffect(() => {
        if (!data || !data.is_in_work_window) {
            return;
        }

        const id = setInterval(() => setNow(new Date()), 1000);

        return () => clearInterval(id);
    }, [data]);

    if (!data) {
        return null;
    }

    return computeDisplayMinutes(data, loadedAtRef.current, now);
}
