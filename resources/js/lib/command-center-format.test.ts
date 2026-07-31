// ==========================================================
// MODUL       : command-center-format.test
// KLASIFIKASI : UTIL
// TUJUAN      : Verifikasi formatJamPair() dan shiftMonth() (B3) — node:test
//               bawaan (pola dashboard-status.test.ts/use-live-counter H1),
//               NOL dependency baru.
// DIPANGGIL   : node --test resources/js/lib/command-center-format.test.ts
// MEMANGGIL   : ./command-center-format
// DATA MASUK  : -
// DATA KELUAR : Assertion pass/fail (node:assert/strict)
// RISIKO      : shiftMonth() salah rollover tahun -> heatmap prev/next lompat
//               ke bulan tak valid (fokus test rollover Des/Jan di bawah).
// ==========================================================

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatJamPair, shiftMonth } from './command-center-format.ts';

test('formatJamPair: menit genap 60 -> integer tanpa desimal', () => {
    assert.equal(formatJamPair(480, 480), '8/8 jam');
});

test('formatJamPair: menit tidak genap 60 -> satu desimal', () => {
    assert.equal(formatJamPair(90, 480), '1.5/8 jam');
});

test('formatJamPair: X=0 (belum ada beban tercatat)', () => {
    assert.equal(formatJamPair(0, 480), '0/8 jam');
});

test('shiftMonth: next dalam tahun sama', () => {
    assert.equal(shiftMonth('2026-08', 1), '2026-09');
});

test('shiftMonth: prev dalam tahun sama', () => {
    assert.equal(shiftMonth('2026-08', -1), '2026-07');
});

test('shiftMonth: rollover Desember -> Januari tahun berikutnya', () => {
    assert.equal(shiftMonth('2026-12', 1), '2027-01');
});

test('shiftMonth: rollover Januari -> Desember tahun sebelumnya', () => {
    assert.equal(shiftMonth('2026-01', -1), '2025-12');
});
