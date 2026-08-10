<?php

/**
 * ==========================================================
 * MODUL       : TaskKpiVisibilityTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi gating kpi_score di halaman detail task (F-136, v1.4
 *               KPI-2) — assignee (member) TIDAK PERNAH melihat skornya sendiri
 *               (cegah gaming, F-4), viewer management (leaderboard.view ATAU
 *               settings.manage) melihatnya, key TIDAK ADA sama sekali di payload
 *               (bukan null) kalau gate gagal, dan kpi_enabled=false menyembunyikan
 *               total walau viewer management.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::show()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau gate ini bocor, member bisa lihat skor sendiri dan mulai
 *               menggame sistem (F-4/F-136 — inti kenapa halaman ini management-only).
 * ==========================================================
 */

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function createKpiVisProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'KPI Visibility Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

// SUMBER: set kolom kpi_score RAW (bukan lewat approve()) -- test ini fokus ke
// LAPISAN TAMPILAN/gating (TaskController::show()), bukan perhitungan skor itu
// sendiri (sudah dites KpiScoringTest.php). Pola sama createApprovedTask() di
// LeaderboardTest.php.
function createKpiScoredTask(Project $project, User $admin, array $assigneeIds, ?int $kpiScore): Task
{
    $completed = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $completed->id,
        'title' => 'KPI vis task '.uniqid(),
        'task_type' => 'tentative',
        'points' => 5,
        'estimated_minutes' => 60,
        'due_date' => now(),
        'approved_at' => now(),
        'kpi_score' => $kpiScore,
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync($assigneeIds);

    return $task;
}

function grantLeaderboardViewToKpiVisTester(User $user): void
{
    $permission = Permission::where('permission_name', 'leaderboard.view')->firstOrFail();
    Role::whereKey($user->role_id)->firstOrFail()->permissions()->syncWithoutDetaching([$permission->id]);
}

test('assignee (member) TIDAK PERNAH melihat kpi_score sendiri -- key absen total dari payload (D4/F-136)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createKpiVisProject($admin, [$member->id]);
    $task = createKpiScoredTask($project, $admin, [$member->id], 5);

    $response = $this->actingAs($member)->get(route('tasks.show', [$project, $task]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->missing('task.kpi_score'));
});

test('admin (settings.manage default) melihat kpi_score task yang sudah disetujui (D4/F-136)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiVisProject($admin);
    $task = createKpiScoredTask($project, $admin, [$admin->id], 5);

    $response = $this->actingAs($admin)->get(route('tasks.show', [$project, $task]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->where('task.kpi_score', 5));
});

test('viewer dengan leaderboard.view (tanpa settings.manage) TETAP melihat kpi_score -- gate OR, bukan AND (D4)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    grantLeaderboardViewToKpiVisTester($member);
    $project = createKpiVisProject($admin, [$member->id]);
    // Task di-assign ke ADMIN (bukan $member) -- $member di sini murni viewer
    // management (leaderboard.view), bukan assignee task ini.
    $task = createKpiScoredTask($project, $admin, [$admin->id], 3);

    $response = $this->actingAs($member)->get(route('tasks.show', [$project, $task]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->where('task.kpi_score', 3));
});

test('kpi_enabled=false menyembunyikan kpi_score total walau viewer management (D4/F-166)', function () {
    $admin = User::factory()->admin()->create();
    $admin->organization->update(['kpi_enabled' => false]);
    $project = createKpiVisProject($admin);
    $task = createKpiScoredTask($project, $admin, [$admin->id], 5);

    $response = $this->actingAs($admin)->get(route('tasks.show', [$project, $task]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->missing('task.kpi_score'));
});

test('task belum di-approve (kpi_score null) tetap kirim key ke management, BEDA dari disembunyikan (D4)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiVisProject($admin);
    $task = createKpiScoredTask($project, $admin, [$admin->id], null);

    $response = $this->actingAs($admin)->get(route('tasks.show', [$project, $task]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->where('task.kpi_score', null));
});
