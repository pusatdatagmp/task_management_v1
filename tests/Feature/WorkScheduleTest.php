<?php

/**
 * ==========================================================
 * MODUL       : WorkScheduleTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi Pengaturan Jam Kerja Hari-3 — SIMPAN = INSERT baru (F-40),
 *               effective_from anti-backdate (F-70), kapasitas vs jendela (F-42).
 *               REVISI 2026-08-10 (audit Boss, F-40 tetap dihormati): edit/arsip
 *               manual sekarang ada TAPI cuma utk versi FUTURE (belum pernah
 *               aktif) — versi yang sudah pernah/sedang aktif TETAP terkunci
 *               permanen, ditest eksplisit (guard tidak boleh bolong).
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

// =============================================================================
// EDIT/ARSIP versi FUTURE (audit Boss 2026-08-10, F-40 tetap dihormati)
// =============================================================================

test('admin can edit a FUTURE version -- UPDATE in place, no new row created', function () {
    $admin = User::factory()->admin()->create();
    $future = WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => now()->addWeek()->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
    $countBefore = WorkSchedule::count();

    $response = $this->actingAs($admin)->put(route('work-schedules.update', $future), [
        'days_of_week' => [1, 2, 3, 4, 5, 6],
        'start_time' => '09:00',
        'end_time' => '18:00',
        'daily_capacity_minutes' => 500,
        'effective_from' => now()->addWeek()->toDateString(),
    ]);

    $response->assertRedirect(route('work-schedules.index'));
    expect(WorkSchedule::count())->toBe($countBefore); // TETAP UPDATE, bukan INSERT.
    $future->refresh();
    expect($future->days_of_week)->toBe([1, 2, 3, 4, 5, 6])
        ->and($future->daily_capacity_minutes)->toBe(500);
});

test('admin CANNOT edit a version that is already active (effective_from today) -- F-40 guard', function () {
    $admin = User::factory()->admin()->create();
    $today = WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => now()->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->put(route('work-schedules.update', $today), [
        'days_of_week' => [1, 2, 3, 4, 5, 6],
        'start_time' => '09:00',
        'end_time' => '18:00',
        'daily_capacity_minutes' => 500,
        'effective_from' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('effective_from');
    $today->refresh();
    expect($today->daily_capacity_minutes)->toBe(480); // TAK BERUBAH.
});

test('admin CANNOT edit a version from the past -- F-40 guard', function () {
    $admin = User::factory()->admin()->create();
    $past = WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => now()->subMonth()->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->put(route('work-schedules.update', $past), [
        'days_of_week' => [1],
        'start_time' => '09:00',
        'end_time' => '18:00',
        'daily_capacity_minutes' => 100,
        'effective_from' => now()->addWeek()->toDateString(),
    ]);

    $response->assertSessionHasErrors('effective_from');
    expect($past->fresh()->daily_capacity_minutes)->toBe(480);
});

test('admin can archive a FUTURE version -- is_archived flips true, row NOT deleted', function () {
    $admin = User::factory()->admin()->create();
    $future = WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => now()->addWeek()->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->patch(route('work-schedules.archive', $future));

    $response->assertRedirect(route('work-schedules.index'));
    expect($future->fresh()->is_archived)->toBeTrue();
    expect(WorkSchedule::whereKey($future->id)->exists())->toBeTrue(); // BUKAN hard delete (F-16 semangat).
});

test('admin CANNOT archive a version that is already active or past -- F-40 guard', function () {
    $admin = User::factory()->admin()->create();
    $today = WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => now()->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->patch(route('work-schedules.archive', $today));

    $response->assertSessionHasErrors('effective_from');
    expect($today->fresh()->is_archived)->toBeFalse();
});

test('an archived FUTURE version never becomes active even after its effective_from date arrives (WorkSchedule::active() guard)', function () {
    $admin = User::factory()->admin()->create();
    $original = WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => now()->subMonth()->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
    $cancelled = WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => now()->addDay()->toDateString(),
        'days_of_week' => [1],
        'start_time' => '10:00',
        'end_time' => '11:00',
        'daily_capacity_minutes' => 60,
        'created_by' => $admin->id,
        'is_archived' => true,
    ]);

    // Lompat ke SETELAH effective_from milik $cancelled -- kalau guard is_archived
    // bolong, $cancelled akan "hidup lagi" jadi versi aktif di sini.
    $this->travelTo(now()->addDays(2));

    $active = WorkSchedule::active($admin->organization_id);
    expect($active->id)->toBe($original->id)
        ->and($active->id)->not->toBe($cancelled->id);
});

test('member cannot edit or archive work schedule versions', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $future = WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => now()->addWeek()->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($member)->put(route('work-schedules.update', $future), [
        'days_of_week' => [1], 'start_time' => '09:00', 'end_time' => '18:00',
        'daily_capacity_minutes' => 100, 'effective_from' => now()->addWeek()->toDateString(),
    ])->assertForbidden();

    $this->actingAs($member)->patch(route('work-schedules.archive', $future))->assertForbidden();
});
