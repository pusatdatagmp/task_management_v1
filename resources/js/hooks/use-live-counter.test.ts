// ==========================================================
// MODUL       : use-live-counter.test
// KLASIFIKASI : UTIL
// TUJUAN      : Verifikasi fungsi PURE computeDisplayMinutes() (C2) — in-window
//               menick, out-window statis, berhenti di window_ends_at. Dijalankan
//               via Node.js built-in test runner (`node --test`, Node 24 type-
//               stripping bawaan) — NOL dependency baru (vitest/jest belum
//               terpasang di proyek ini, instalasi butuh approval Boss, CLAUDE.md §4).
// DIPANGGIL   : node --test resources/js/hooks/use-live-counter.test.ts
// MEMANGGIL   : ./use-live-counter (computeDisplayMinutes, formatLiveMinutes)
// DATA MASUK  : -
// DATA KELUAR : Assertion pass/fail (node:assert/strict)
// RISIKO      : Fungsi ini SATU-SATUNYA logika waktu di frontend (F-94) — kalau
//               capping window_ends_at salah, counter bisa terus menick lewat jam
//               pulang di layar (bukan di data, F-39 tetap aman, tapi UI menyesatkan).
// ==========================================================

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { computeDisplayMinutes, formatLiveMinutes, type LiveCounterData } from './use-live-counter.ts';

function data(overrides: Partial<LiveCounterData> = {}): LiveCounterData {
    return {
        accumulated_minutes: 60,
        is_in_work_window: true,
        window_ends_at: '2026-07-20T17:00:00+07:00',
        segment_started_at: '2026-07-20T09:00:00+07:00',
        ...overrides,
    };
}

test('out-window (paused): mengembalikan accumulated_minutes apa adanya, tidak menick', () => {
    const loadedAt = new Date('2026-07-20T18:00:00+07:00');
    const now = new Date('2026-07-20T18:30:00+07:00'); // 30 menit berlalu, TAPI di luar jendela

    const result = computeDisplayMinutes(data({ is_in_work_window: false, accumulated_minutes: 120 }), loadedAt, now);

    assert.equal(result, 120);
});

test('in-window: menambah selisih wall-clock sejak loadedAt', () => {
    const loadedAt = new Date('2026-07-20T10:00:00+07:00');
    const now = new Date('2026-07-20T10:05:00+07:00'); // 5 menit berlalu

    const result = computeDisplayMinutes(data({ accumulated_minutes: 60 }), loadedAt, now);

    assert.equal(result, 65);
});

test('in-window: BERHENTI tepat di window_ends_at, tidak lanjut ke wall-clock mentah', () => {
    const loadedAt = new Date('2026-07-20T16:55:00+07:00');
    const now = new Date('2026-07-20T18:00:00+07:00'); // 65 menit wall-clock, tapi jendela tutup 17:00

    const result = computeDisplayMinutes(
        data({ accumulated_minutes: 60, window_ends_at: '2026-07-20T17:00:00+07:00' }),
        loadedAt,
        now,
    );

    // Cap: loadedAt(16:55) -> window_ends_at(17:00) = 5 menit, BUKAN 65 menit wall-clock mentah.
    assert.equal(result, 65);
});

test('in-window tanpa window_ends_at (schedule tidak ditemukan): tidak capped, tetap tick wall-clock', () => {
    const loadedAt = new Date('2026-07-20T10:00:00+07:00');
    const now = new Date('2026-07-20T10:10:00+07:00');

    const result = computeDisplayMinutes(data({ accumulated_minutes: 0, window_ends_at: null }), loadedAt, now);

    assert.equal(result, 10);
});

test('formatLiveMinutes: di bawah 1 jam -> "Xm"', () => {
    assert.equal(formatLiveMinutes(45), '45m');
    assert.equal(formatLiveMinutes(0), '0m');
});

test('formatLiveMinutes: >=1 jam -> "Xj Ym"', () => {
    assert.equal(formatLiveMinutes(83), '1j 23m');
    assert.equal(formatLiveMinutes(120), '2j 0m');
});

test('formatLiveMinutes: membulatkan ke bawah pecahan menit (tick sub-menit)', () => {
    assert.equal(formatLiveMinutes(65.9), '1j 5m');
});
