<?php

/**
 * ==========================================================
 * MODUL       : WorkScheduleTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi Pengaturan Jam Kerja Hari-3 — SIMPAN = INSERT baru (F-40),
 *               effective_from anti-backdate (F-70), kapasitas vs jendela (F-42).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : WorkScheduleController, WorkSchedule
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test F-70 adalah gerbang paling kritis — kalau ini lolos padahal
 *               backdate diterima, jendela kerja masa lalu bisa ditulis ulang dan
 *               diam-diam mengubah realisasi task yang belum di-approve.
 * ==========================================================
 */

use App\Models\User;
use App\Models\WorkSchedule;

test('admin adding a schedule version inserts a new row, never updates existing', function () {
    $admin = User::factory()->admin()->create();

    $before = WorkSchedule::count();

    $response = $this->actingAs($admin)->post(route('work-schedules.store'), [
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '09:00',
        'end_time' => '18:00',
        'daily_capacity_minutes' => 480,
        'effective_from' => now()->addDay()->toDateString(),
    ]);

    $response->assertRedirect(route('work-schedules.index'));
    expect(WorkSchedule::count())->toBe($before + 1);
});

test('backdated effective_from is rejected (F-70)', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('work-schedules.store'), [
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'effective_from' => now()->subDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors('effective_from');
    expect(WorkSchedule::count())->toBe(0);
});

test('effective_from of today is accepted (not a backdate)', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('work-schedules.store'), [
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'effective_from' => now()->toDateString(),
    ]);

    $response->assertSessionDoesntHaveErrors('effective_from');
});

test('daily_capacity_minutes cannot exceed window length (F-42)', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('work-schedules.store'), [
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '09:00', // jendela cuma 60 menit
        'daily_capacity_minutes' => 120, // melebihi jendela
        'effective_from' => now()->addDays(2)->toDateString(),
    ]);

    $response->assertSessionHasErrors('daily_capacity_minutes');
});

test('member cannot access work schedule settings', function () {
    $member = User::factory()->create();

    $response = $this->actingAs($member)->get(route('work-schedules.index'));

    $response->assertForbidden();
});
