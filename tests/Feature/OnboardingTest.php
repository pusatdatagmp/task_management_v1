<?php

/**
 * ==========================================================
 * MODUL       : OnboardingTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi UserService::onboardNewUser() (RBAC §C) lewat HTTP —
 *               3 mode payload role, transaction rollback (C3/F-89), dan F-92
 *               (password server-generated, client TIDAK BISA mengirimnya).
 *               Termasuk regression test F-93: payload PERSIS seperti yang dikirim
 *               frontend asli (users/create.tsx transform() — SELALU menyertakan
 *               custom_permissions[]/permissions[] kosong di SEMUA mode, bukan
 *               di-null-kan) — bug ini LOLOS di Pest lama karena test lama cuma
 *               kirim field minimal, ketemu lewat browser nyata (F-75).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : UserController::store(), OnboardUserRequest, UserService
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau transaction rollback (C3) gagal, user bisa tersimpan TANPA
 *               role (F-89) — user itu akan error di setiap tempat yang akses
 *               $user->role (Gate::before, sidebar permissions, dst).
 * ==========================================================
 */

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

// SUMBER: bentuk PERSIS payload yang dikirim resources/js/pages/users/create.tsx
// (submit() -> transform()) untuk mode "existing" — role_id terisi, TAPI
// base_role_id/new_role_name di-null-kan sedangkan custom_permissions/permissions
// TETAP dikirim sebagai array kosong (bukan dihapus dari payload). F-93: ini
// PERSIS payload yang dulu bikin OnboardUserRequest salah hitung modeCount=2.
function existingModePayload(int $roleId, string $email): array
{
    return [
        'name' => 'Onboard Existing',
        'email' => $email,
        'employment_type' => 'internal',
        'daily_capacity_minutes' => '',
        'role_mode' => 'existing',
        'role_id' => $roleId,
        'base_role_id' => '',
        'new_role_name' => '',
        'custom_permissions' => [],
        'permissions' => [],
    ];
}

test('F-93 regression: mode "existing" succeeds even with empty custom_permissions[]/permissions[] present (real frontend payload shape)', function () {
    $admin = User::factory()->admin()->create();
    $memberRole = RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization)['member'];

    $response = $this->actingAs($admin)->post(
        route('users.store'),
        existingModePayload($memberRole->id, 'onboard-existing@example.com'),
    );

    $response->assertRedirect(route('users.index'));
    $response->assertSessionHasNoErrors();

    $newUser = User::where('email', 'onboard-existing@example.com')->firstOrFail();
    expect($newUser->role_id)->toBe($memberRole->id);
});

test('mode "clone": creates a NEW role with EXACTLY custom_permissions, not merged with the base role', function () {
    $admin = User::factory()->admin()->create();
    $baseRole = RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization)['admin']; // punya SEMUA permission
    $taskApprove = Permission::where('permission_name', 'task.approve')->firstOrFail();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Onboard Clone',
        'email' => 'onboard-clone@example.com',
        'employment_type' => 'internal',
        'role_mode' => 'clone',
        'role_id' => '',
        'base_role_id' => $baseRole->id,
        'new_role_name' => 'Supervisor Clone',
        'custom_permissions' => [$taskApprove->id],
        'permissions' => [],
    ]);

    $response->assertRedirect(route('users.index'));
    $response->assertSessionHasNoErrors();

    $newRole = Role::where('organization_id', $admin->organization_id)->where('role_name', 'Supervisor Clone')->firstOrFail();
    // GUARD: base_role_id HANYA titik referensi UI (lihat UserService::resolveRole()
    // kontrak) — permission akhir HARUS persis custom_permissions, TIDAK
    // "nempel diam-diam" seluruh permission base (admin, di test ini).
    expect($newRole->permissions()->pluck('permission_name')->all())->toBe(['task.approve']);

    $newUser = User::where('email', 'onboard-clone@example.com')->firstOrFail();
    expect($newUser->role_id)->toBe($newRole->id);
});

test('mode "new": creates a role from scratch with exactly the given permissions', function () {
    $admin = User::factory()->admin()->create();
    $taskManage = Permission::where('permission_name', 'task.manage')->firstOrFail();
    $statusManage = Permission::where('permission_name', 'status.manage')->firstOrFail();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Onboard New',
        'email' => 'onboard-new@example.com',
        'employment_type' => 'internal',
        'role_mode' => 'new',
        'new_role_name' => 'Operasional',
        'permissions' => [$taskManage->id, $statusManage->id],
        'custom_permissions' => [],
    ]);

    $response->assertRedirect(route('users.index'));

    $newRole = Role::where('organization_id', $admin->organization_id)->where('role_name', 'Operasional')->firstOrFail();
    expect($newRole->permissions()->pluck('permission_name')->sort()->values()->all())
        ->toBe(['status.manage', 'task.manage']);
});

test('transaction rollback (C3/F-89): role creation failure leaves NO orphaned user', function () {
    $admin = User::factory()->admin()->create();
    $userCountBefore = User::count();

    // BUSINESS RULE: C6 — nama role tidak boleh bentrok nama sistem, sengaja
    // dilanggar di sini untuk memicu ValidationException DI TENGAH transaction
    // (resolveRole() sudah jalan, User::create() belum), memverifikasi tidak
    // ada user yang tersimpan tanpa role (F-89).
    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Should Not Exist',
        'email' => 'should-not-exist@example.com',
        'employment_type' => 'internal',
        'role_mode' => 'new',
        'new_role_name' => 'admin',
        'permissions' => [],
    ]);

    $response->assertSessionHasErrors('new_role_name');
    expect(User::count())->toBe($userCountBefore)
        ->and(User::where('email', 'should-not-exist@example.com')->exists())->toBeFalse();
});

test('role_mode is required — omitting it is rejected, not silently guessed', function () {
    $admin = User::factory()->admin()->create();
    $memberRole = RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization)['member'];

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'No Mode',
        'email' => 'no-mode@example.com',
        'employment_type' => 'internal',
        'role_id' => $memberRole->id,
    ]);

    $response->assertSessionHasErrors('role_mode');
    expect(User::where('email', 'no-mode@example.com')->exists())->toBeFalse();
});

test('F-92: client-supplied password is ignored — server always generates a random one', function () {
    $admin = User::factory()->admin()->create();
    $memberRole = RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization)['member'];

    $this->actingAs($admin)->post(route('users.store'), [
        ...existingModePayload($memberRole->id, 'onboard-nopassword@example.com'),
        'password' => 'ForcedPassword123!',
    ]);

    $newUser = User::where('email', 'onboard-nopassword@example.com')->firstOrFail();
    expect(Hash::check('ForcedPassword123!', $newUser->password))->toBeFalse();
});

test('member (no user.manage) cannot onboard a new user (F-90/F-91)', function () {
    $member = User::factory()->create();
    $memberRole = RolePermissionSeeder::seedSystemRolesForOrganization($member->organization)['member'];

    $response = $this->actingAs($member)->post(
        route('users.store'),
        existingModePayload($memberRole->id, 'blocked@example.com'),
    );

    $response->assertForbidden();
    expect(User::where('email', 'blocked@example.com')->exists())->toBeFalse();
});
