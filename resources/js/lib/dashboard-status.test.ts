// ==========================================================
// MODUL       : dashboard-status.test
// KLASIFIKASI : UTIL
// TUJUAN      : Verifikasi classifyWorkload() (A3/B2) — overload, idle-tinggi,
//               normal, dan guard kapasitas 0. Node.js built-in test runner
//               (`node --test`, pola use-live-counter H1) — NOL dependency baru.
// DIPANGGIL   : node --test resources/js/lib/dashboard-status.test.ts
// MEMANGGIL   : ./dashboard-status (classifyWorkload)
// DATA MASUK  : -
// DATA KELUAR : Assertion pass/fail (node:assert/strict)
// RISIKO      : Kalau ambang di sini salah, admin dapat sinyal visual keliru
//               (overload tidak ketandai / orang normal ditandai idle-tinggi) —
//               bukan data KPI yang rusak (F-52 tetap dari service), tapi keputusan
//               assign admin bisa terarahkan salah.
// ==========================================================

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { classifyWorkload } from './dashboard-status.ts';

test('beban melebihi kapasitas -> overload', () => {
    assert.equal(classifyWorkload(500, 480), 'overload');
});

test('beban persis sama dengan kapasitas -> normal, bukan overload', () => {
    assert.equal(classifyWorkload(480, 480), 'normal');
});

test('idle_plan >= 50% kapasitas -> idle-tinggi', () => {
    assert.equal(classifyWorkload(200, 480), 'idle-tinggi'); // idle 280/480 = 58%
});

test('idle_plan tepat di bawah 50% kapasitas -> normal', () => {
    assert.equal(classifyWorkload(250, 480), 'normal'); // idle 230/480 = 48%
});

test('kapasitas 0 (belum ada work_schedule aktif) -> normal, bukan idle-tinggi', () => {
    assert.equal(classifyWorkload(0, 0), 'normal');
});

test('beban 0 dengan kapasitas normal -> idle-tinggi (100% nganggur)', () => {
    assert.equal(classifyWorkload(0, 480), 'idle-tinggi');
});
