// ==========================================================
// MODUL       : leaderboard-period.test
// KLASIFIKASI : UTIL
// TUJUAN      : Verifikasi todayRange()/thisWeekRange()/thisMonthRange() (B2) --
//               node:test bawaan (pola dashboard-status.test.ts), NOL dependency baru.
// DIPANGGIL   : node --test resources/js/lib/leaderboard-period.test.ts
// MEMANGGIL   : ./leaderboard-period
// DATA MASUK  : -
// DATA KELUAR : Assertion pass/fail (node:assert/strict)
// RISIKO      : thisWeekRange() salah offset -> "minggu ini" mulai hari yang salah.
// ==========================================================

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { thisMonthRange, thisWeekRange, todayRange } from './leaderboard-period.ts';

test('todayRange: from dan to sama-sama tanggal hari itu', () => {
    assert.deepEqual(todayRange(new Date(2026, 7, 19)), { from: '2026-08-19', to: '2026-08-19' });
});

test('thisWeekRange: Rabu -> Senin s/d Minggu minggu yang sama', () => {
    // 2026-08-19 = Rabu.
    assert.deepEqual(thisWeekRange(new Date(2026, 7, 19)), { from: '2026-08-17', to: '2026-08-23' });
});

test('thisWeekRange: Senin -> Senin itu sendiri s/d Minggu', () => {
    // 2026-08-17 = Senin.
    assert.deepEqual(thisWeekRange(new Date(2026, 7, 17)), { from: '2026-08-17', to: '2026-08-23' });
});

test('thisWeekRange: Minggu -> Senin sebelumnya s/d Minggu itu sendiri', () => {
    // 2026-08-23 = Minggu.
    assert.deepEqual(thisWeekRange(new Date(2026, 7, 23)), { from: '2026-08-17', to: '2026-08-23' });
});

test('thisMonthRange: tanggal 1 s/d hari terakhir bulan berjalan', () => {
    assert.deepEqual(thisMonthRange(new Date(2026, 7, 19)), { from: '2026-08-01', to: '2026-08-31' });
});

test('thisMonthRange: rollover Februari (28 hari, 2026 bukan kabisat)', () => {
    assert.deepEqual(thisMonthRange(new Date(2026, 1, 10)), { from: '2026-02-01', to: '2026-02-28' });
});
