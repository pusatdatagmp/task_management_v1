<?php

/**
 * ==========================================================
 * MODUL       : UserManagementTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi CRUD user (F-90 permission `user.manage`), organization_id
 *               auto-fill (F-5/F-15), password opsional saat edit, guard tidak bisa
 *               menonaktifkan/menghapus akun sendiri, dan fitur Hapus Akun (soft
 *               delete, 2026-09-11) — destroy()/restore() + guard task aktif (F-87-style).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : UserController, UserService
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test organization_id auto-fill adalah pagar F-15 — kalau gagal,
 *               user baru bisa lolos tanpa tenant isolation. Test guard destroy()
 *               adalah pagar F-39/F-41 — kalau gagal, segmen waktu bisa menggantung
 *               tak pernah ditutup saat user penanggung jawabnya dihapus.
 * PERUBAHAN   : F-78 — diperbarui (bukan ditambal) mengikuti RBAC §C/F-92: 'role'
 *               enum + password manual di form onboarding PENSIUN, diganti
 *               role_id (mode 1 payload) + password acak dari UserService.
 *               Cakupan tes SETARA (create/forbidden/update/toggle), cuma bentuk
 *               payload yang menyesuaikan kontrak baru. Cakupan onboarding 3-mode
 *               lengkap + transaction rollback ada di tests/Feature/OnboardingTest.php.
 *               F-93 — role_mode kini WAJIB (OnboardUserRequest), payload store()
 *               ditambah 'role_mode' => 'existing' supaya tetap valid.
 *               2026-09-11 (keputusan Boss) — ditambah cakupan destroy()/restore()
 *               fitur Hapus Akun, TIDAK mengubah test lama satu pun (F-78).
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
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

test('an admin cannot delete their own account (2026-09-11)', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->delete(route('users.destroy', $admin));

    $response->assertForbidden();
    expect($admin->refresh()->deleted_at)->toBeNull();
});

test('deleting a user without active tasks soft-deletes it (F-16, 2026-09-11)', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($admin)->delete(route('users.destroy', $user));

    $response->assertRedirect(route('users.index'));
    expect($user->fresh()->deleted_at)->not->toBeNull()
        // SUMBER F-16: baris TIDAK hilang dari DB, cuma tersembunyi dari query default.
        ->and(User::withTrashed()->find($user->id))->not->toBeNull()
        ->and(User::find($user->id))->toBeNull();
});

test('deleting a user with an active (is_work_state) task is rejected (F-87-style guard, 2026-09-11)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Hapus Akun Guard Test',
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);
    $project->members()->attach([$admin->id, $member->id]);

    $workState = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $workState->id,
        'title' => 'Sedang dikerjakan',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
    $task->assignees()->attach($member->id);

    $response = $this->actingAs($admin)->delete(route('users.destroy', $member));

    $response->assertSessionHasErrors('user');
    expect($member->fresh()->deleted_at)->toBeNull();
});

test('restoring a deleted user makes it visible again (2026-09-11)', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['organization_id' => $admin->organization_id]);
    $user->delete();

    $response = $this->actingAs($admin)->patch(route('users.restore', $user));

    $response->assertRedirect(route('users.trash'));
    expect($user->fresh()->deleted_at)->toBeNull();
});

test('a deleted user still shows up as a task assignee, preserving KPI attribution (withTrashed, 2026-09-11)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'withTrashed Test',
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);
    $project->members()->attach([$admin->id, $member->id]);

    $status = TaskStatus::where('project_id', $project->id)->where('is_work_state', false)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Sudah selesai sebelum user dihapus',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
    $task->assignees()->attach($member->id);

    $member->delete();

    expect($task->fresh()->assignees->pluck('id'))->toContain($member->id);
});
