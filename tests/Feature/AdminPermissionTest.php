<?php

/**
 * ==========================================================
 * MODUL       : AdminPermissionTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi menyeluruh 03-BUSINESS-FLOW §6 — member (role tanpa
 *               permission apa pun, D2) DITOLAK di SETIAP route admin-only,
 *               sedangkan Project index TETAP bisa diakses member.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : middleware `can:xxx` per route (routes/admin.php, F-90)
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau satu route admin lupa dipasangi middleware `can:xxx`, test ini
 *               yang menangkapnya sebelum member menemukannya sendiri di produksi.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\User;

test('member is forbidden from every admin-only route', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Permission Test Project',
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);
    $status = TaskStatus::where('project_id', $project->id)->firstOrFail();

    $adminRoutes = [
        ['GET', route('work-schedules.index')],
        ['POST', route('work-schedules.store')],
        ['GET', route('projects.create')],
        ['POST', route('projects.store')],
        ['GET', route('projects.edit', $project)],
        ['PUT', route('projects.update', $project)],
        ['PATCH', route('projects.archive', $project)],
        ['GET', route('task-statuses.index', $project)],
        ['GET', route('task-statuses.create', $project)],
        ['POST', route('task-statuses.store', $project)],
        ['GET', route('task-statuses.edit', [$project, $status])],
        ['PUT', route('task-statuses.update', [$project, $status])],
        ['PATCH', route('task-statuses.reorder', [$project, $status])],
        ['DELETE', route('task-statuses.destroy', [$project, $status])],
    ];

    foreach ($adminRoutes as [$method, $url]) {
        $response = $this->actingAs($member)->call($method, $url);

        expect($response->status())->toBe(403, "Diharapkan 403 untuk {$method} {$url}, dapat {$response->status()}");
    }
});

test('project index is NOT admin-only — member can view their own list', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($member)->get(route('projects.index'));

    $response->assertOk();
});
