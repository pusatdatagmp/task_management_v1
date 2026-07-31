// ==========================================================
// MODUL       : board-column-validity.test
// KLASIFIKASI : UTIL
// TUJUAN      : Verifikasi isValidDropTarget() (aturan C, F-110) — dari posisi P:
//               sah = {<P, P, P+1}, tak-sah = {>P+1}. Node.js built-in test runner
//               (`node --test`, pola dashboard-status.test.ts) — NOL dependency baru.
// DIPANGGIL   : node --test resources/js/lib/board-column-validity.test.ts
// MEMANGGIL   : ./board-column-validity (isValidDropTarget)
// DATA MASUK  : -
// DATA KELUAR : Assertion pass/fail (node:assert/strict)
// RISIKO      : -
// ==========================================================

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isValidDropTarget } from './board-column-validity.ts';

test('posisi sama dengan asal (batal drag) -> sah', () => {
    assert.equal(isValidDropTarget(1, 1), true);
});

test('posisi P+1 (maju satu, F-45) -> sah', () => {
    assert.equal(isValidDropTarget(1, 2), true);
});

test('posisi di bawah asal (mundur bebas) -> sah', () => {
    assert.equal(isValidDropTarget(2, 0), true);
    assert.equal(isValidDropTarget(2, 1), true);
});

test('posisi lompat > P+1 (maju 2 langkah atau lebih) -> TAK sah', () => {
    assert.equal(isValidDropTarget(0, 2), false);
    assert.equal(isValidDropTarget(0, 3), false);
});

test('dari posisi 0, hanya 0 dan 1 yang sah', () => {
    assert.equal(isValidDropTarget(0, 0), true);
    assert.equal(isValidDropTarget(0, 1), true);
    assert.equal(isValidDropTarget(0, 2), false);
});
