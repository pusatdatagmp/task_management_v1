<?php

/**
 * ==========================================================
 * MODUL       : UserManagementTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi CRUD user (F-90 permission `user.manage`), organization_id
 *               auto-fill (F-5/F-15), password opsional saat edit, dan guard tidak
 *               bisa menonaktifkan akun sendiri.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : UserController, UserService
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test organization_id auto-fill adalah pagar F-15 — kalau gagal,
 *               user baru bisa lolos tanpa tenant isolation.
 * PERUBAHAN   : F-78 — diperbarui (bukan ditambal) mengikuti RBAC §C/F-92: 'role'
 *               enum + password manual di form onboarding PENSIUN, diganti
 *               role_id (mode 1 payload) + password acak dari UserService.
 *               Cakupan tes SETARA (create/forbidden/update/toggle), cuma bentuk
 *               payload yang menyesuaikan kontrak baru. Cakupan onboarding 3-mode
 *               lengkap + transaction rollback ada di tests/Feature/OnboardingTest.php.
 *               F-93 — role_mode kini WAJIB (OnboardUserRequest), payload store()
 *               ditambah 'role_mode' => 'existing' supaya tetap valid.
 * ==========================================================
 */

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

test('admin can create a new user, scoped to their own organization (F-5/F-15)', function () {
    $admin = User::factory()->admin()->create();
    $memberRole = RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization)['member'];

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Member Baru',
        'email' => 'member-baru@example.com',
        'employment_type' => 'internal',
        'role_mode' => 'existing',
        'role_id' => $memberRole->id,
    ]);

    $response->assertRedirect(route('users.index'));

    $newUser = User::where('email', 'member-baru@example.com')->firstOrFail();
    expect($newUser->organization_id)->toBe($admin->organization_id)
        ->and($newUser->role_id)->toBe($memberRole->id)
        ->and($newUser->is_active)->toBeTrue()
        // SUMBER F-92: password TIDAK dikirim dari form — dibuat acak oleh
        // UserService, flash sekali ke session (bukan dites di sini, redirect
        // sudah cukup membuktikan alur sukses; nilai plaintext-nya tidak
        // relevan diverifikasi lewat DB karena memang tidak disimpan plaintext).
        ->and($newUser->password)->not->toBeEmpty();
});

test('member cannot create a user (F-29/F-90)', function () {
    $member = User::factory()->create();
    $memberRole = RolePermissionSeeder::seedSystemRolesForOrganization($member->organization)['member'];

    $response = $this->actingAs($member)->post(route('users.store'), [
        'name' => 'Tidak Boleh',
        'email' => 'tidak-boleh@example.com',
        'employment_type' => 'internal',
        'role_mode' => 'existing',
        'role_id' => $memberRole->id,
    ]);

    $response->assertForbidden();
    expect(User::where('email', 'tidak-boleh@example.com')->exists())->toBeFalse();
});

test('updating a user without filling password keeps the old password', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['organization_id' => $admin->organization_id, 'password' => Hash::make('OldPassword1!')]);

    $this->actingAs($admin)->put(route('users.update', $user), [
        'name' => 'Nama Baru',
        'email' => $user->email,
        'password' => '',
        'password_confirmation' => '',
        'role_id' => $user->role_id,
        'employment_type' => $user->employment_type,
    ])->assertSessionDoesntHaveErrors();

    $user->refresh();
    expect($user->name)->toBe('Nama Baru')
        ->and(Hash::check('OldPassword1!', $user->password))->toBeTrue();
});

test('an admin cannot deactivate their own account', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->patch(route('users.toggle-active', $admin));

    $response->assertForbidden();
    expect($admin->refresh()->is_active)->toBeTrue();
});

test('toggling active status flips it without deleting the user (F-16)', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['organization_id' => $admin->organization_id, 'is_active' => true]);

    $this->actingAs($admin)->patch(route('users.toggle-active', $user));

    expect($user->refresh())
        ->is_active->toBeFalse()
        ->deleted_at->toBeNull();
});
