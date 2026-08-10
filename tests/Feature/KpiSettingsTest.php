<?php

/**
 * ==========================================================
 * MODUL       : KpiSettingsTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi tab KPI halaman Setelan (F-166, v1.4 KPI-2) — org-scoped
 *               (F-5), validasi poin int >= 0, gating settings.manage (reuse DS-2/
 *               DS-3, pola sama ThemeSettingsTest), tak buat permission baru.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : SettingsController::updateKpi(), UpdateKpiRequest
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau gate settings.manage bocor, member biasa bisa ubah poin KPI
 *               org (integritas data penilaian tim, F-166/F-167).
 * ==========================================================
 */

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

test('a user without settings.manage gets 403 on KPI config update', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($member)->post(route('settings.kpi.update'), [
        'kpi_enabled' => true,
        'kpi_points_ontime' => 5,
        'kpi_points_late' => 3,
        'kpi_points_notdone' => 0,
    ]);

    $response->assertForbidden();
});

test('a user with settings.manage can save KPI toggle+poin, org-scoped (F-5)', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('settings.kpi.update'), [
        'kpi_enabled' => false,
        'kpi_points_ontime' => 8,
        'kpi_points_late' => 2,
        'kpi_points_notdone' => 1,
    ]);

    $response->assertRedirect();

    $admin->organization->refresh();
    expect($admin->organization->kpi_enabled)->toBeFalse()
        ->and($admin->organization->kpi_points_ontime)->toBe(8)
        ->and($admin->organization->kpi_points_late)->toBe(2)
        ->and($admin->organization->kpi_points_notdone)->toBe(1);
});

test('KPI config never leaks across organizations (F-5/F-15)', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminA)->post(route('settings.kpi.update'), [
        'kpi_enabled' => true, 'kpi_points_ontime' => 11, 'kpi_points_late' => 1, 'kpi_points_notdone' => 0,
    ]);
    $this->actingAs($adminB)->post(route('settings.kpi.update'), [
        'kpi_enabled' => true, 'kpi_points_ontime' => 22, 'kpi_points_late' => 2, 'kpi_points_notdone' => 0,
    ]);

    expect($adminA->organization->fresh()->kpi_points_ontime)->toBe(11)
        ->and($adminB->organization->fresh()->kpi_points_ontime)->toBe(22);
});

test('negative poin ditolak validasi', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('settings.kpi.update'), [
        'kpi_enabled' => true,
        'kpi_points_ontime' => -1,
        'kpi_points_late' => 3,
        'kpi_points_notdone' => 0,
    ]);

    $response->assertSessionHasErrors('kpi_points_ontime');
    // Config LAMA tidak ikut berubah walau salah satu field gagal validasi.
    expect($admin->organization->fresh()->kpi_points_ontime)->toBe(5); // default migrasi KPI-1.
});

test('kpi_enabled non-boolean/poin non-integer ditolak validasi', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('settings.kpi.update'), [
        'kpi_enabled' => true,
        'kpi_points_ontime' => 'lima',
        'kpi_points_late' => 3,
        'kpi_points_notdone' => 0,
    ]);

    $response->assertSessionHasErrors('kpi_points_ontime');
});

test('halaman Setelan mengirim config KPI org saat ini (edit())', function () {
    $admin = User::factory()->admin()->create();
    $admin->organization->update(['kpi_enabled' => true, 'kpi_points_ontime' => 7]);

    $response = $this->actingAs($admin)->get(route('settings.index'));

    $response->assertInertia(fn ($p) => $p->where('kpi.kpi_enabled', true)->where('kpi.kpi_points_ontime', 7));
});

test('settings.manage (reuse Branding/Tema) dipakai lagi untuk KPI, tak ada permission baru', function () {
    $names = collect(RolePermissionSeeder::catalog())->pluck('permission_name');

    expect($names)->toHaveCount($names->unique()->count())
        ->and($names)->toContain('settings.manage');
});
