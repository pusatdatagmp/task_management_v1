<?php

/**
 * ==========================================================
 * MODUL       : RolePermissionTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi model-level RBAC (RBAC §B/G) — Role::hasPermission(),
 *               User::hasPermission()/can() lewat Gate::before, seed katalog
 *               admin=semua/member=nol (D2), dan Role::wouldLeaveNoHolderOfPermission()
 *               (guard RoleController::update(), E1) TIDAK dites lewat HTTP di sini
 *               (itu tugas PermissionEnforcementTest) — murni benar/salah logika.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : Role, User, Permission, RolePermissionSeeder
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau wouldLeaveNoHolderOfPermission() salah hitung, organisasi bisa
 *               terkunci total dari halaman kelola user/role (tidak ada jalan masuk
 *               lain, F-91 tidak ada self-signup) — test ini pagar terakhir sebelum itu.
 * ==========================================================
 */

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

// F-78: diperbarui dari "admin dapat SEMUA katalog" -- v1.2 (F-134) sengaja
// mengubah perilaku itu, SATU pengecualian: leaderboard.view (management-only,
// Boss assign manual). Cakupan setara: admin dapat SEMUA KECUALI pengecualian
// itu, member tetap nol, DITAMBAH assert eksplisit leaderboard.view TIDAK ikut.
test('seeding system roles gives admin ALL default-admin catalog permissions and member NONE (D2/F-134)', function () {
    $admin = User::factory()->admin()->create();
    $roles = RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization);

    $expectedAdminCount = collect(RolePermissionSeeder::catalog())
        ->reject(fn (array $p) => ($p['default_admin'] ?? true) === false)
        ->count();

    expect($roles['admin']->permissions()->count())->toBe($expectedAdminCount)
        ->and($roles['member']->permissions()->count())->toBe(0)
        ->and($roles['admin']->hasPermission('leaderboard.view'))->toBeFalse();
});

test('seeding system roles is idempotent — calling twice does not duplicate roles or permissions', function () {
    $admin = User::factory()->admin()->create();

    RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization);
    RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization);

    $expectedAdminCount = collect(RolePermissionSeeder::catalog())
        ->reject(fn (array $p) => ($p['default_admin'] ?? true) === false)
        ->count();

    expect(Role::where('organization_id', $admin->organization_id)->where('is_system', true)->count())->toBe(2)
        ->and(Role::where('organization_id', $admin->organization_id)->where('role_name', 'admin')->first()->permissions()->count())
        ->toBe($expectedAdminCount);
});

test('Role::hasPermission reflects the pivot, not a guess', function () {
    $admin = User::factory()->admin()->create();
    $role = Role::create(['organization_id' => $admin->organization_id, 'role_name' => 'Custom', 'is_system' => false, 'is_default' => false]);
    $taskManage = Permission::where('permission_name', 'task.manage')->firstOrFail();

    expect($role->hasPermission('task.manage'))->toBeFalse();

    $role->permissions()->attach($taskManage->id);
    $role->refresh()->load('permissions');

    expect($role->hasPermission('task.manage'))->toBeTrue()
        ->and($role->hasPermission('task.approve'))->toBeFalse();
});

// F-78: diperbarui -- v1.2 (F-134) leaderboard.view SATU-SATUNYA baris katalog
// yang admin TIDAK otomatis punya (default_admin=false). Loop tetap cek SEMUA
// baris katalog (cakupan setara), ekspektasi admin per baris ikut flag itu.
test('User::can() (Gate::before -> hasPermission) is true for admin (kecuali default_admin=false) and false for member, per permission (F-134)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    foreach (RolePermissionSeeder::catalog() as $permission) {
        $adminExpected = ($permission['default_admin'] ?? true) !== false;

        expect($admin->can($permission['permission_name']))->toBe($adminExpected, 'admin diharapkan '.($adminExpected ? 'punya' : 'TIDAK punya')." {$permission['permission_name']}")
            ->and($member->can($permission['permission_name']))->toBeFalse("member diharapkan TIDAK punya {$permission['permission_name']}");
    }
});

test('a custom role only grants the permissions explicitly synced to it', function () {
    $admin = User::factory()->admin()->create();
    $taskApprove = Permission::where('permission_name', 'task.approve')->firstOrFail();

    $role = Role::create(['organization_id' => $admin->organization_id, 'role_name' => 'Supervisor', 'is_system' => false, 'is_default' => false]);
    $role->permissions()->sync([$taskApprove->id]);

    $supervisor = User::factory()->create(['organization_id' => $admin->organization_id, 'role_id' => $role->id]);

    expect($supervisor->can('task.approve'))->toBeTrue()
        ->and($supervisor->can('task.manage'))->toBeFalse()
        ->and($supervisor->can('user.manage'))->toBeFalse();
});

test('wouldLeaveNoHolderOfPermission is true when the role being checked is the ONLY holder', function () {
    $admin = User::factory()->admin()->create();
    $roles = RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization);

    // Hanya role admin sistem yang pegang user.manage di organisasi baru ini.
    expect(Role::wouldLeaveNoHolderOfPermission($admin->organization_id, 'user.manage', $roles['admin']->id))->toBeTrue();
});

test('wouldLeaveNoHolderOfPermission is false when another role also holds the permission', function () {
    $admin = User::factory()->admin()->create();
    $roles = RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization);
    $userManage = Permission::where('permission_name', 'user.manage')->firstOrFail();

    $secondHolder = Role::create(['organization_id' => $admin->organization_id, 'role_name' => 'Co-Admin', 'is_system' => false, 'is_default' => false]);
    $secondHolder->permissions()->attach($userManage->id);

    expect(Role::wouldLeaveNoHolderOfPermission($admin->organization_id, 'user.manage', $roles['admin']->id))->toBeFalse();
});

test('wouldLeaveNoHolderOfPermission only counts roles within the SAME organization (F-15)', function () {
    $adminOrgA = User::factory()->admin()->create();
    $rolesOrgA = RolePermissionSeeder::seedSystemRolesForOrganization($adminOrgA->organization);

    // Organisasi lain juga punya role admin dengan user.manage — TIDAK BOLEH
    // dihitung sebagai "masih ada pemegang lain" untuk organisasi A.
    $adminOrgB = User::factory()->admin()->create();
    RolePermissionSeeder::seedSystemRolesForOrganization($adminOrgB->organization);

    expect(Role::wouldLeaveNoHolderOfPermission($adminOrgA->organization_id, 'user.manage', $rolesOrgA['admin']->id))->toBeTrue();
});
