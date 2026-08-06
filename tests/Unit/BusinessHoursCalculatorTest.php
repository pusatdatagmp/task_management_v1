<?php

/**
 * ==========================================================
 * MODUL       : BusinessHoursCalculatorTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi F-57 (cap jendela kerja) SEBELUM aplikasi menyentuh data
 *               nyata — kalau rumus ini salah, actual_minutes yang dibekukan (F-39)
 *               saat task pertama di-approve akan salah PERMANEN. F-66 matang: config
 *               di-resolve PER HARI dari koleksi WorkSchedule (bukan 1 versi untuk
 *               seluruh segmen). F-43 matang: holiday di-skip seperti akhir pekan.
 * DIPANGGIL   : php artisan test (Pest, suite Unit — murni PHP, tanpa DB)
 * MEMANGGIL   : BusinessHoursCalculator, WorkSchedule & Holiday (dipakai sebagai value
 *               object in-memory di sini, TIDAK pernah di-save ke DB)
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kasus "Jumat 16:00 -> Senin 09:00 = 120 menit" adalah GERBANG UTAMA
 *               (01-PRD §6 contoh F-57) — kalau ini gagal, JANGAN lanjut ke integrasi
 *               Task::calculateActualMinutes()/seeder.
 * ==========================================================
 */

use App\Models\Holiday;
use App\Models\WorkSchedule;
use App\Services\BusinessHoursCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

// SUMBER: seluruh kasus di file ini pakai jendela Sen-Jum 08:00-17:00, kapasitas
// 480 menit — persis contoh F-57 di 01-PRD §6 dan 03-BUSINESS-FLOW §2.
// Tanggal acuan: 2024-01-05 = Jumat, 2024-01-08 = Senin, 2024-01-12 = Jumat berikutnya.
// F-66: effective_from ditanam jauh di masa lalu supaya berlaku di SELURUH tanggal
// test di grup lama tanpa jadi fokus kasus itu sendiri — resolusi per-hari punya
// grup test terpisah di bawah ("F-66 — resolusi config per-hari").
function businessWorkSchedule(): WorkSchedule
{
    return new WorkSchedule([
        'effective_from' => '2020-01-01',
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'daily_capacity_minutes' => 480,
    ]);
}

// F-43: koleksi holiday kosong = kondisi v0.5 (tabel belum diisi). Dipakai di
// seluruh test kecuali grup "F-43 — holidays" di bawah.
function noHolidays(): Collection
{
    return collect();
}

beforeEach(function () {
    $this->calculator = new BusinessHoursCalculator;
    $this->schedule = businessWorkSchedule();
    $this->schedules = collect([$this->schedule]);
});

test('kasus utama: Jumat 16:00 -> Senin 09:00 = 120 menit (bukan 3900)', function () {
    $start = Carbon::create(2024, 1, 5, 16, 0, 0);
    $end = Carbon::create(2024, 1, 8, 9, 0, 0);

    expect($this->calculator->overlapMinutes($start, $end, $this->schedules, noHolidays()))->toBe(120);
});

test('Senin 09:00 -> Senin 11:00 = 120 menit', function () {
    $start = Carbon::create(2024, 1, 8, 9, 0, 0);
    $end = Carbon::create(2024, 1, 8, 11, 0, 0);

    expect($this->calculator->overlapMinutes($start, $end, $this->schedules, noHolidays()))->toBe(120);
});

test('Senin 16:00 -> Selasa 09:00 = 120 menit', function () {
    $start = Carbon::create(2024, 1, 8, 16, 0, 0);
    $end = Carbon::create(2024, 1, 9, 9, 0, 0);

    expect($this->calculator->overlapMinutes($start, $end, $this->schedules, noHolidays()))->toBe(120);
});

test('Sabtu 10:00 -> Sabtu 12:00 = 0 menit (bukan hari kerja)', function () {
    $start = Carbon::create(2024, 1, 6, 10, 0, 0);
    $end = Carbon::create(2024, 1, 6, 12, 0, 0);

    expect($this->calculator->overlapMinutes($start, $end, $this->schedules, noHolidays()))->toBe(0);
});

test('Senin 07:00 -> Senin 09:00 = 60 menit (jendela baru buka 08:00)', function () {
    $start = Carbon::create(2024, 1, 8, 7, 0, 0);
    $end = Carbon::create(2024, 1, 8, 9, 0, 0);

    expect($this->calculator->overlapMinutes($start, $end, $this->schedules, noHolidays()))->toBe(60);
});

test('Senin 16:00 -> Senin 18:00 = 60 menit (jendela tutup 17:00)', function () {
    $start = Carbon::create(2024, 1, 8, 16, 0, 0);
    $end = Carbon::create(2024, 1, 8, 18, 0, 0);

    expect($this->calculator->overlapMinutes($start, $end, $this->schedules, noHolidays()))->toBe(60);
});

test('Senin 18:00 -> Senin 20:00 = 0 menit (sudah di luar jendela)', function () {
    $start = Carbon::create(2024, 1, 8, 18, 0, 0);
    $end = Carbon::create(2024, 1, 8, 20, 0, 0);

    expect($this->calculator->overlapMinutes($start, $end, $this->schedules, noHolidays()))->toBe(0);
});

test('Senin 08:00 -> Jumat 17:00 = 2700 menit (5 hari x 9 jam)', function () {
    $start = Carbon::create(2024, 1, 8, 8, 0, 0);
    $end = Carbon::create(2024, 1, 12, 17, 0, 0);

    expect($this->calculator->overlapMinutes($start, $end, $this->schedules, noHolidays()))->toBe(2700);
});

test('ended_at null dihitung sampai now() kalau now() masih di dalam jendela', function () {
    // GUARD waktu: freeze ke Senin 10:00 supaya deterministik — segmen mulai 09:00
    // masih berjalan, harus dicap ke "sekarang" (10:00), BUKAN ke penutupan 17:00.
    Carbon::setTestNow(Carbon::create(2024, 1, 8, 10, 0, 0));

    $start = Carbon::create(2024, 1, 8, 9, 0, 0);

    expect($this->calculator->overlapMinutes($start, null, $this->schedules, noHolidays()))->toBe(60);

    Carbon::setTestNow();
});

test('ended_at null dicap ke penutupan jendela kalau now() sudah lewat jam pulang', function () {
    Carbon::setTestNow(Carbon::create(2024, 1, 8, 20, 0, 0)); // Senin malam, sudah lewat 17:00

    $start = Carbon::create(2024, 1, 8, 9, 0, 0);

    // min(now, penutupan hari ini) = 17:00 -> overlap 09:00-17:00 = 480 menit,
    // BUKAN 09:00-20:00 mentah.
    expect($this->calculator->overlapMinutes($start, null, $this->schedules, noHolidays()))->toBe(480);

    Carbon::setTestNow();
});

test('ended_at null = 0 menit kalau tidak ada WorkSchedule berlaku hari ini', function () {
    // F-66: koleksi schedule KOSONG -> tidak ada versi yang bisa dipakai untuk
    // menentukan penutupan jendela hari ini. 0, bukan ditebak (F-42/F-40).
    Carbon::setTestNow(Carbon::create(2024, 1, 8, 10, 0, 0));

    $start = Carbon::create(2024, 1, 8, 9, 0, 0);

    expect($this->calculator->overlapMinutes($start, null, collect(), noHolidays()))->toBe(0);

    Carbon::setTestNow();
});

test('end <= start = 0 menit', function () {
    $start = Carbon::create(2024, 1, 8, 12, 0, 0);
    $end = Carbon::create(2024, 1, 8, 10, 0, 0);

    expect($this->calculator->overlapMinutes($start, $end, $this->schedules, noHolidays()))->toBe(0);
});

test('segmen lebih dari 365 hari melempar exception (data korup)', function () {
    $start = Carbon::create(2024, 1, 1, 8, 0, 0);
    $end = Carbon::create(2025, 1, 10, 8, 0, 0); // 2024 kabisat, span > 365 hari

    $this->calculator->overlapMinutes($start, $end, $this->schedules, noHolidays());
})->throws(RuntimeException::class);

test('akumulasi 2 segmen (skenario tolak-lalu-kerja-lagi) = jumlah keduanya', function () {
    // Segmen 1 (sebelum ditolak reviewer): Senin 09:00-11:00 = 120 menit.
    // Segmen 2 (setelah rejection_count++, dikerjakan lagi): Senin 13:00-14:30 = 90 menit.
    // Realisasi task = Σ overlapMinutes() semua segmen (lihat Task::calculateActualMinutes()).
    $segment1Minutes = $this->calculator->overlapMinutes(
        Carbon::create(2024, 1, 8, 9, 0, 0),
        Carbon::create(2024, 1, 8, 11, 0, 0),
        $this->schedules,
        noHolidays(),
    );

    $segment2Minutes = $this->calculator->overlapMinutes(
        Carbon::create(2024, 1, 8, 13, 0, 0),
        Carbon::create(2024, 1, 8, 14, 30, 0),
        $this->schedules,
        noHolidays(),
    );

    expect($segment1Minutes + $segment2Minutes)->toBe(210);
});

// ==========================================================
// F-66 — RESOLUSI CONFIG PER-HARI
// ==========================================================
// v0.5 memakai 1 WorkSchedule (aktif saat started_at) untuk SELURUH segmen. Matang:
// overlapMinutes() menghitung PER HARI dengan config yang berlaku PADA HARI ITU —
// segmen panjang yang menyeberang perubahan work_schedules (F-40, versioned) kini
// dihitung akurat per hari, bukan pakai 1 config lama/baru untuk semuanya.

test('F-66: segmen Senin-Jumat, config berubah Rabu -> split benar per hari', function () {
    // Config lama (berlaku sejak 2020): Sen-Jum 08:00-17:00 (9 jam/hari).
    // Config baru (berlaku MULAI Rabu 2024-01-10): Sen-Jum 09:00-16:00 (7 jam/hari).
    $oldSchedule = $this->schedule; // effective_from 2020-01-01
    $newSchedule = new WorkSchedule([
        'effective_from' => '2024-01-10',
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '09:00:00',
        'end_time' => '16:00:00',
        'daily_capacity_minutes' => 420,
    ]);
    $schedules = collect([$oldSchedule, $newSchedule]);

    // Senin 08:00 -> Jumat 17:00 (raw, tidak dicap tanggal tertentu).
    $start = Carbon::create(2024, 1, 8, 8, 0, 0);
    $end = Carbon::create(2024, 1, 12, 17, 0, 0);

    // Senin(540)+Selasa(540) pakai config lama, Rabu(420)+Kamis(420)+Jumat(420,
    // dicap ke 16:00 karena jendela baru tutup lebih awal) pakai config baru.
    expect($this->calculator->overlapMinutes($start, $end, $schedules, noHolidays()))->toBe(2340);
});

test('F-66: WorkSchedule masa depan yang belum berlaku TIDAK mengubah hasil hari lampau (regression guard v0.5)', function () {
    // Menambahkan versi yang effective_from-nya jauh di masa depan tidak boleh
    // mengubah realisasi tanggal yang sudah lewat -- resolusi per-hari harus tetap
    // memilih versi terbaru yang SUDAH berlaku pada hari itu, bukan versi manapun.
    $futureSchedule = new WorkSchedule([
        'effective_from' => '2030-01-01',
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '10:00:00',
        'end_time' => '14:00:00',
        'daily_capacity_minutes' => 240,
    ]);
    $schedules = collect([$this->schedule, $futureSchedule]);

    $start = Carbon::create(2024, 1, 8, 9, 0, 0);
    $end = Carbon::create(2024, 1, 8, 11, 0, 0);

    // Identik dengan test v0.5 "Senin 09:00 -> Senin 11:00 = 120 menit" di atas —
    // pembuktian bahwa pematangan F-66 tidak mengubah kasus tanpa perubahan config.
    expect($this->calculator->overlapMinutes($start, $end, $schedules, noHolidays()))->toBe(120);
});

// ==========================================================
// F-43 — HOLIDAYS
// ==========================================================

test('F-43: segmen melewati 1 hari libur -> hari libur 0 menit, hari kerja lain tetap dihitung', function () {
    // Libur nasional Selasa 2024-01-09. Senin tetap hari kerja normal.
    $holidays = collect([new Holiday(['date' => '2024-01-09', 'name' => 'Contoh Libur Nasional'])]);

    $start = Carbon::create(2024, 1, 8, 8, 0, 0); // Senin 08:00
    $end = Carbon::create(2024, 1, 9, 17, 0, 0); // Selasa 17:00

    // Senin penuh (08:00-17:00 = 540 menit) + Selasa 0 menit (libur) = 540.
    expect($this->calculator->overlapMinutes($start, $end, $this->schedules, $holidays))->toBe(540);
});

test('F-43: libur jatuh di akhir pekan -> tidak dihitung ganda (sudah 0 dari akhir pekan)', function () {
    // Sabtu 2024-01-06 SENGAJA ditandai libur juga -- hari itu memang sudah bukan
    // hari kerja (days_of_week Sen-Jum). Hasil harus identik dengan kasus gerbang
    // F-57 Jumat->Senin (120 menit) TANPA holiday sama sekali -- pembuktian bahwa
    // menandai hari yang sudah libur akhir pekan tidak mengubah/menggandakan apa pun.
    $holidays = collect([new Holiday(['date' => '2024-01-06', 'name' => 'Contoh Libur di Akhir Pekan'])]);

    $start = Carbon::create(2024, 1, 5, 16, 0, 0); // Jumat 16:00
    $end = Carbon::create(2024, 1, 8, 9, 0, 0); // Senin 09:00

    expect($this->calculator->overlapMinutes($start, $end, $this->schedules, $holidays))->toBe(120);
});

// Revisi 2026-08-06 item 7 -- addBusinessDays(), dipakai deadline tugas berulang
// (due_offset_days, GenerateTaskAction).
test('addBusinessDays: Senin +1 hari kerja = Selasa (revisi 2026-08-06 item 7)', function () {
    $from = Carbon::create(2024, 1, 8); // Senin
    $result = $this->calculator->addBusinessDays($from, 1, $this->schedules, noHolidays());

    expect($result->toDateString())->toBe('2024-01-09'); // Selasa
});

test('addBusinessDays: Jumat +1 hari kerja LOMPAT akhir pekan = Senin berikutnya (revisi 2026-08-06 item 7)', function () {
    $from = Carbon::create(2024, 1, 5); // Jumat
    $result = $this->calculator->addBusinessDays($from, 1, $this->schedules, noHolidays());

    expect($result->toDateString())->toBe('2024-01-08'); // Senin
});

test('addBusinessDays: +5 hari kerja dari Senin = Senin minggu berikutnya (lompat 1 akhir pekan penuh, revisi 2026-08-06 item 7)', function () {
    $from = Carbon::create(2024, 1, 8); // Senin
    $result = $this->calculator->addBusinessDays($from, 5, $this->schedules, noHolidays());

    expect($result->toDateString())->toBe('2024-01-15'); // Senin minggu berikutnya
});

test('addBusinessDays: hari libur di tengah rentang ikut dilompati, bukan cuma akhir pekan (revisi 2026-08-06 item 7)', function () {
    // Selasa 2024-01-09 libur nasional -- dari Senin +2 hari kerja seharusnya
    // Rabu (Senin sendiri tak dihitung, Selasa dilompati krn libur, Rabu=1, Kamis=2).
    $holidays = collect([new Holiday(['date' => '2024-01-09', 'name' => 'Contoh Libur'])]);
    $from = Carbon::create(2024, 1, 8); // Senin

    $result = $this->calculator->addBusinessDays($from, 2, $this->schedules, $holidays);

    expect($result->toDateString())->toBe('2024-01-11'); // Kamis
});

test('addBusinessDays: kembalikan null kalau organisasi nol WorkSchedule (guard config korup, revisi 2026-08-06 item 7)', function () {
    $from = Carbon::create(2024, 1, 8);

    $result = $this->calculator->addBusinessDays($from, 1, collect(), noHolidays());

    expect($result)->toBeNull();
});
