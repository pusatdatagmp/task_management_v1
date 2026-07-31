<?php

/**
 * ==========================================================
 * MODUL       : PermissionEnforcementTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi GRANULAR per-permission (RBAC §D/G) — role CUSTOM yang
 *               cuma pegang SATU permission dari katalog bisa akses gerbang
 *               `can:xxx`-nya SAJA, ditolak di gerbang permission lain. Beda dari
 *               AdminPermissionTest (member TANPA permission apa pun ditolak
 *               semua) — di sini pembuktiannya per-permission, bukan biner
 *               admin/member, supaya role custom (mis. "Supervisor") benar-benar
 *               teruji bukan cuma diasumsikan dari Fase B. Termasuk F-15 —
 *               role/user organisasi lain tidak bisa diakses walau ID ditebak.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : middleware `can:xxx` per route (routes/admin.php, F-90),
 *               BelongsToOrganization/OrganizationScope (F-15)
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau satu permission ternyata menggerbangi route yang BUKAN
 *               miliknya (typo string permission, dsb — F-44 melarang hardcode
 *               nama tapi tetap rawan salah ketik string), test ini pemisah
 *               "role custom dengan 1 permission" vs "akses penuh tidak sengaja".
 * ==========================================================
 */

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

/**
 * KONTRAK: user baru di organisasi $organizationId dengan role custom yang HANYA
 * pegang $permissionName (atau tanpa permission sama sekali kalau null) — dipakai
 * membuktikan gerbang route bereaksi PERSIS ke permission itu, bukan ke identitas
 * admin/member.
 */
function userWithOnlyPermission(int $organizationId, ?string $permissionName): User
{
    $role = Role::create([
        'organization_id' => $organizationId,
        'role_name' => 'Single-'.($permissionName ?? 'none').'-'.uniqid(),
        'is_system' => false,
        'is_default' => false,
    ]);

    if ($permissionName) {
        $permission = Permission::where('permission_name', $permissionName)->firstOrFail();
        $role->permissions()->attach($permission->id);
    }

    return User::factory()->create(['organization_id' => $organizationId, 'role_id' => $role->id]);
}

test('a role holding ONLY workschedule.manage can reach it, and nothing else', function () {
    $admin = User::factory()->admin()->create();
    $holder = userWithOnlyPermission($admin->organization_id, 'workschedule.manage');
    $stranger = userWithOnlyPermission($admin->organization_id, 'task.manage');

    $this->actingAs($holder)->get(route('work-schedules.index'))->assertOk();
    $this->actingAs($stranger)->get(route('work-schedules.index'))->assertForbidden();
});

test('a role holding ONLY user.manage can reach it, and nothing else', function () {
    $admin = User::factory()->admin()->create();
    $holder = userWithOnlyPermission($admin->organization_id, 'user.manage');
    $stranger = userWithOnlyPermission($admin->organization_id, 'project.manage');

    $this->actingAs($holder)->get(route('users.index'))->assertOk();
    $this->actingAs($holder)->get(route('roles.index'))->assertOk();
    $this->actingAs($stranger)->get(route('users.index'))->assertForbidden();
    $this->actingAs($stranger)->get(route('roles.index'))->assertForbidden();
});

test('a role holding ONLY project.manage can reach it, and nothing else', function () {
    $admin = User::factory()->admin()->create();
    $holder = userWithOnlyPermission($admin->organization_id, 'project.manage');
    $stranger = userWithOnlyPermission($admin->organization_id, 'status.manage');

    $this->actingAs($holder)->get(route('projects.create'))->assertOk();
    $this->actingAs($stranger)->get(route('projects.create'))->assertForbidden();
});

test('a role holding ONLY status.manage can reach it, and nothing else', function () {
    $admin = User::factory()->admin()->create();
    $project = Project::create(['organization_id' => $admin->organization_id, 'name' => 'P', 'owner_id' => $admin->id]);
    $holder = userWithOnlyPermission($admin->organization_id, 'status.manage');
    $stranger = userWithOnlyPermission($admin->organization_id, 'task.manage');

    $this->actingAs($holder)->get(route('task-statuses.index', $project))->assertOk();
    $this->actingAs($stranger)->get(route('task-statuses.index', $project))->assertForbidden();
});

test('a role holding ONLY task.manage can reach it, and nothing else', function () {
    $admin = User::factory()->admin()->create();
    $project = Project::create(['organization_id' => $admin->organization_id, 'name' => 'P', 'owner_id' => $admin->id]);
    $holder = userWithOnlyPermission($admin->organization_id, 'task.manage');
    $stranger = userWithOnlyPermission($admin->organization_id, 'task.approve');

    $this->actingAs($holder)->get(route('tasks.create', $project))->assertOk();
    $this->actingAs($stranger)->get(route('tasks.create', $project))->assertForbidden();
});

test('a role holding ONLY task.approve can reach it, and nothing else', function () {
    $admin = User::factory()->admin()->create();
    $project = Project::create(['organization_id' => $admin->organization_id, 'name' => 'P', 'owner_id' => $admin->id]);
    TaskStatus::seedDefaults($project);
    $reviewStatus = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $reviewStatus->id,
        'title' => 'Butuh approval',
        'task_type' => 'tentative',
        'priority' => 'normal',
        'points' => 1,
        'estimated_minutes' => 30,
        'due_date' => now()->addDay(),
        'created_by' => $admin->id,
    ]);

    $holder = userWithOnlyPermission($admin->organization_id, 'task.approve');
    $stranger = userWithOnlyPermission($admin->organization_id, 'task.manage');

    // GUARD: bukan assertOk() — reject() bisa lanjut ke business-rule error lain
    // (400/302) tergantung state task. Yang dibuktikan di sini KHUSUS gerbang
    // permission (403 vs bukan-403), bukan seluruh alur approve/reject.
    $holderResponse = $this->actingAs($holder)->patch(route('tasks.reject', [$project, $task]), ['reason' => 'test']);
    expect($holderResponse->status())->not->toBe(403);

    $this->actingAs($stranger)->patch(route('tasks.reject', [$project, $task]), ['reason' => 'test'])
        ->assertForbidden();
});

test('F-15: a role/user from another organization is 404, not 403 — never visible to guess', function () {
    $adminOrgA = User::factory()->admin()->create();
    $adminOrgB = User::factory()->admin()->create();

    $roleFromOrgB = Role::create(['organization_id' => $adminOrgB->organization_id, 'role_name' => 'Rahasia B', 'is_system' => false, 'is_default' => false]);
    $userFromOrgB = User::factory()->create(['organization_id' => $adminOrgB->organization_id]);

    $this->actingAs($adminOrgA)->get(route('roles.edit', $roleFromOrgB))->assertNotFound();
    $this->actingAs($adminOrgA)->get(route('users.edit', $userFromOrgB))->assertNotFound();
});
