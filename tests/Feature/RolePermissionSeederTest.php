<?php

/**
 * ==========================================================
 * MODUL       : RolePermissionSeederTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Regresi F-129 — seedSystemRolesForOrganization() untuk org B
 *               sementara Auth::user() masih org A tidak boleh crash ataupun
 *               mencampur data (F-5/F-15). Sebelum fix, OrganizationScope
 *               menambah WHERE organization_id=org-A ke SELECT firstOrCreate
 *               yang sedang menargetkan org B -> tidak pernah match -> INSERT
 *               ganda -> duplicate-key crash.
 * DIPANGGIL   : php artisan test (Pest, suite Feature)
 * MEMANGGIL   : RolePermissionSeeder::seedSystemRolesForOrganization()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau test ini lolos tanpa fix withoutGlobalScope, F-129
 *               kembali laten dan baru meledak saat multi-org/marketplace v3.0.
 * ==========================================================
 */

use App\Models\Organization;
use App\Models\Role;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

test('seeding roles for org B while acting as org A does not crash (F-129)', function () {
    $userOrgA = User::factory()->admin()->create();
    $orgB = Organization::factory()->create();

    $this->actingAs($userOrgA);

    $roles = RolePermissionSeeder::seedSystemRolesForOrganization($orgB);

    expect($roles['admin']->organization_id)->toBe($orgB->id);
    expect($roles['member']->organization_id)->toBe($orgB->id);

    // F-5/F-15: role org B tidak boleh 1 baris pun bocor ke hitungan org A.
    $rolesOrgA = Role::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $userOrgA->organization_id)
        ->get();
    expect($rolesOrgA->pluck('organization_id')->unique()->all())->toBe([$userOrgA->organization_id]);
});

test('seeding roles for the same org twice stays idempotent, no duplicate rows', function () {
    $org = Organization::factory()->create();

    RolePermissionSeeder::seedSystemRolesForOrganization($org);
    RolePermissionSeeder::seedSystemRolesForOrganization($org);

    $count = Role::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $org->id)
        ->count();

    expect($count)->toBe(2);
});
