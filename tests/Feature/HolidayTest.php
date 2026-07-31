<?php

/**
 * ==========================================================
 * MODUL       : HolidayTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi Pengaturan Hari Libur (F-43 HARDEN) — CRUD, tanggal
 *               unik per organization (F-5), gating permission workschedule.manage.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : HolidayController, Holiday
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test unique constraint adalah gerbang F-5 — kalau bolong, holiday
 *               organisasi lain bisa dianggap konflik tanggal (kebocoran tenant).
 * ==========================================================
 */

use App\Models\Holiday;
use App\Models\User;

test('admin can add a holiday', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('holidays.store'), [
        'date' => '2026-08-17',
        'name' => 'Hari Kemerdekaan RI',
    ]);

    $response->assertRedirect(route('holidays.index'));
    expect(Holiday::where('date', '2026-08-17')->where('name', 'Hari Kemerdekaan RI')->exists())->toBeTrue();
});

test('duplicate date within the same organization is rejected (F-5)', function () {
    $admin = User::factory()->admin()->create();
    Holiday::create(['organization_id' => $admin->organization_id, 'date' => '2026-12-25', 'name' => 'Natal']);

    $response = $this->actingAs($admin)->post(route('holidays.store'), [
        'date' => '2026-12-25',
        'name' => 'Duplikat',
    ]);

    $response->assertSessionHasErrors('date');
    expect(Holiday::where('organization_id', $admin->organization_id)->where('date', '2026-12-25')->count())->toBe(1);
});

test('same date is allowed across different organizations (F-5)', function () {
    $adminOrgA = User::factory()->admin()->create();
    $adminOrgB = User::factory()->admin()->create(); // Organization::factory() beda per user

    Holiday::create(['organization_id' => $adminOrgA->organization_id, 'date' => '2026-05-01', 'name' => 'Hari Buruh']);

    $response = $this->actingAs($adminOrgB)->post(route('holidays.store'), [
        'date' => '2026-05-01',
        'name' => 'Hari Buruh',
    ]);

    $response->assertRedirect(route('holidays.index'));
    expect(Holiday::where('organization_id', $adminOrgB->organization_id)->where('date', '2026-05-01')->exists())->toBeTrue();
});

test('admin can update a holiday', function () {
    $admin = User::factory()->admin()->create();
    $holiday = Holiday::create(['organization_id' => $admin->organization_id, 'date' => '2026-06-01', 'name' => 'Typo']);

    $response = $this->actingAs($admin)->put(route('holidays.update', $holiday), [
        'date' => '2026-06-01',
        'name' => 'Nama Benar',
    ]);

    $response->assertRedirect(route('holidays.index'));
    expect($holiday->fresh()->name)->toBe('Nama Benar');
});

test('admin can delete a holiday', function () {
    $admin = User::factory()->admin()->create();
    $holiday = Holiday::create(['organization_id' => $admin->organization_id, 'date' => '2026-06-01', 'name' => 'Hapus Saya']);

    $response = $this->actingAs($admin)->delete(route('holidays.destroy', $holiday));

    $response->assertRedirect(route('holidays.index'));
    expect(Holiday::find($holiday->id))->toBeNull();
});

test('member cannot access holiday settings', function () {
    $member = User::factory()->create();

    $response = $this->actingAs($member)->get(route('holidays.index'));

    $response->assertForbidden();
});
