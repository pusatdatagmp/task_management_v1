<?php

/**
 * ==========================================================
 * MODUL       : HolidayShiftResolverTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : G3 (prompt AE-2) — target di hari libur/weekend digeser maju ke
 *               hari kerja berikutnya (F-43/F-153), REUSE
 *               BusinessHoursCalculator::isBusinessDay (F-72/F-76).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : HolidayShiftResolver
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : F-153 mengubah F-102 lama -- daily SEKARANG IKUT digeser, test ini
 *               pagar supaya regresi tidak diam-diam mengembalikan perilaku lama.
 * ==========================================================
 */

use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Automation\Resolvers\HolidayShiftResolver;
use Carbon\Carbon;

function seedResolverSchedule(User $admin, Carbon $effectiveFrom): WorkSchedule
{
    return WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $effectiveFrom->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

test('hari kerja biasa TIDAK digeser', function () {
    $admin = User::factory()->admin()->create();
    seedResolverSchedule($admin, Carbon::create(2026, 1, 1));
    $schedules = WorkSchedule::where('organization_id', $admin->organization_id)->get();
    $holidays = Holiday::where('organization_id', $admin->organization_id)->get();

    $monday = Carbon::create(2026, 8, 3); // Senin
    $result = (new HolidayShiftResolver)->resolve($monday, $schedules, $holidays);

    expect($result->toDateString())->toBe('2026-08-03');
});

test('weekend digeser maju ke hari kerja berikutnya', function () {
    $admin = User::factory()->admin()->create();
    seedResolverSchedule($admin, Carbon::create(2026, 1, 1));
    $schedules = WorkSchedule::where('organization_id', $admin->organization_id)->get();
    $holidays = Holiday::where('organization_id', $admin->organization_id)->get();

    $saturday = Carbon::create(2026, 8, 8); // Sabtu
    $result = (new HolidayShiftResolver)->resolve($saturday, $schedules, $holidays);

    expect($result->toDateString())->toBe('2026-08-10'); // Senin
});

test('hari libur digeser maju ke hari kerja berikutnya (F-43)', function () {
    $admin = User::factory()->admin()->create();
    seedResolverSchedule($admin, Carbon::create(2026, 1, 1));
    Holiday::create(['organization_id' => $admin->organization_id, 'date' => '2026-08-04', 'name' => 'Libur Uji']);

    $schedules = WorkSchedule::where('organization_id', $admin->organization_id)->get();
    $holidays = Holiday::where('organization_id', $admin->organization_id)->get();

    $tuesday = Carbon::create(2026, 8, 4); // Selasa, jadi libur
    $result = (new HolidayShiftResolver)->resolve($tuesday, $schedules, $holidays);

    expect($result->toDateString())->toBe('2026-08-05'); // Rabu
});

test('libur melompati batas minggu -> geser sampai Senin berikutnya', function () {
    $admin = User::factory()->admin()->create();
    seedResolverSchedule($admin, Carbon::create(2026, 1, 1));
    // Jumat 7 Agustus + Sabtu/Minggu libur alami -> geser ke Senin 10 Agustus.
    Holiday::create(['organization_id' => $admin->organization_id, 'date' => '2026-08-07', 'name' => 'Libur Uji']);

    $schedules = WorkSchedule::where('organization_id', $admin->organization_id)->get();
    $holidays = Holiday::where('organization_id', $admin->organization_id)->get();

    $friday = Carbon::create(2026, 8, 7); // Jumat, jadi libur
    $result = (new HolidayShiftResolver)->resolve($friday, $schedules, $holidays);

    expect($result->toDateString())->toBe('2026-08-10'); // Senin
});
