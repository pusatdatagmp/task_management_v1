<?php

/**
 * ==========================================================
 * MODUL       : AllTasksTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi halaman "Semua Tugas" (F-140/F-144/F-147, v1.2 H7b) —
 *               flat lintas project, gating project.viewAll (F-90), status difilter
 *               via FLAG bukan id mentah (F-44), filter/sort prioritas migrasi ke
 *               priority_quadrant (F-139), N+1 konstan (F-85).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::all(), FilterAllTasksRequest
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau gating project.viewAll bocor, member biasa bisa lihat task
 *               SELURUH organisasi (bukan cuma miliknya) lewat halaman ini.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function createAllTasksProject(User $admin, string $suffix = ''): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'All Tasks Project '.$suffix.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

function createAllTasksTask(Project $project, User $admin, array $overrides = []): Task
{
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'All tasks '.uniqid(),
        'task_type' => 'tentative',
        'priority' => 'normal',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
        ...$overrides,
    ]);
}

test('a user without project.viewAll cannot access Semua Tugas', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($member)->get(route('tasks.all'));

    $response->assertForbidden();
});

test('a user with project.viewAll sees tasks from multiple projects on one page', function () {
    $admin = User::factory()->admin()->create();
    $projectA = createAllTasksProject($admin, 'A');
    $projectB = createAllTasksProject($admin, 'B');

    $taskA = createAllTasksTask($projectA, $admin);
    $taskB = createAllTasksTask($projectB, $admin);

    $response = $this->actingAs($admin)->get(route('tasks.all'));

    $response->assertInertia(fn ($page) => $page
        ->component('tasks/all')
        ->has('tasks.data', 2)
        ->where('tasks.data.0.id', fn ($id) => in_array($id, [$taskA->id, $taskB->id])));
});

test('project_id filter narrows Semua Tugas to a single project', function () {
    $admin = User::factory()->admin()->create();
    $projectA = createAllTasksProject($admin, 'A');
    $projectB = createAllTasksProject($admin, 'B');

    $taskA = createAllTasksTask($projectA, $admin);
    createAllTasksTask($projectB, $admin);

    $response = $this->actingAs($admin)->get(route('tasks.all').'?'.http_build_query(['project_id' => $projectA->id]));

    $response->assertInertia(fn ($page) => $page->has('tasks.data', 1)->where('tasks.data.0.id', $taskA->id));
});

test('status_flag filter uses TaskStatus flags, not raw status ids, across projects with different status sets (F-44)', function () {
    $admin = User::factory()->admin()->create();
    $projectA = createAllTasksProject($admin, 'A');
    $projectB = createAllTasksProject($admin, 'B');

    $inProgressA = TaskStatus::where('project_id', $projectA->id)->where('is_work_state', true)->firstOrFail();
    $inProgressB = TaskStatus::where('project_id', $projectB->id)->where('is_work_state', true)->firstOrFail();
    $todoA = TaskStatus::where('project_id', $projectA->id)->where('position', 0)->firstOrFail();

    $workingA = createAllTasksTask($projectA, $admin, ['task_status_id' => $inProgressA->id]);
    $workingB = createAllTasksTask($projectB, $admin, ['task_status_id' => $inProgressB->id]);
    createAllTasksTask($projectA, $admin, ['task_status_id' => $todoA->id]);

    $response = $this->actingAs($admin)->get(route('tasks.all').'?'.http_build_query(['status_flag' => ['in_progress']]));

    $response->assertInertia(fn ($page) => $page
        ->has('tasks.data', 2)
        ->where('tasks.data.0.id', fn ($id) => in_array($id, [$workingA->id, $workingB->id]))
        ->where('tasks.data.1.id', fn ($id) => in_array($id, [$workingA->id, $workingB->id])));
});

test('priority_quadrant filter/sort uses the new quadrant field, not the legacy priority enum (F-139)', function () {
    $admin = User::factory()->admin()->create();
    $project = createAllTasksProject($admin);

    $p1 = createAllTasksTask($project, $admin, ['priority_quadrant' => 'p1', 'priority' => 'low']);
    createAllTasksTask($project, $admin, ['priority_quadrant' => 'p4', 'priority' => 'urgent']);

    $response = $this->actingAs($admin)->get(route('tasks.all').'?'.http_build_query(['priority_quadrant' => ['p1']]));

    $response->assertInertia(fn ($page) => $page->has('tasks.data', 1)->where('tasks.data.0.id', $p1->id));

    $sorted = $this->actingAs($admin)->get(
        route('tasks.all').'?'.http_build_query(['sort' => 'priority_quadrant', 'direction' => 'asc'])
    );

    $sorted->assertInertia(fn ($page) => $page->where('tasks.data.0.id', $p1->id));
});

test('sort by title (2026-08-08, permintaan Boss)', function () {
    $admin = User::factory()->admin()->create();
    $project = createAllTasksProject($admin);

    $b = createAllTasksTask($project, $admin, ['title' => 'Bravo task']);
    $a = createAllTasksTask($project, $admin, ['title' => 'Alpha task']);

    $response = $this->actingAs($admin)->get(route('tasks.all').'?'.http_build_query(['sort' => 'title', 'direction' => 'asc']));

    $response->assertInertia(fn ($page) => $page
        ->where('tasks.data.0.id', $a->id)
        ->where('tasks.data.1.id', $b->id));
});

test('sort by project name (2026-08-08, permintaan Boss)', function () {
    $admin = User::factory()->admin()->create();
    $projectZ = createAllTasksProject($admin, 'Z-later-alphabetically-');
    $projectA = createAllTasksProject($admin, 'A-first-alphabetically-');

    $taskInZ = createAllTasksTask($projectZ, $admin);
    $taskInA = createAllTasksTask($projectA, $admin);

    $response = $this->actingAs($admin)->get(route('tasks.all').'?'.http_build_query(['sort' => 'project', 'direction' => 'asc']));

    $response->assertInertia(fn ($page) => $page
        ->where('tasks.data.0.id', $taskInA->id)
        ->where('tasks.data.1.id', $taskInZ->id));
});

test('sort by assignee -- assignee PERTAMA alfabetis per task (keputusan Boss 2026-08-08)', function () {
    $admin = User::factory()->admin()->create();
    $project = createAllTasksProject($admin);
    $alice = User::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Alice']);
    $zach = User::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Zach']);
    $project->members()->sync([$admin->id, $alice->id, $zach->id]);

    $taskWithZach = createAllTasksTask($project, $admin, ['title' => 'Task Zach saja']);
    $taskWithZach->assignees()->attach($zach->id);

    // Task ini punya DUA assignee (Zach, Alice) -- assignee PERTAMA alfabetis (Alice)
    // yang harus jadi basis sort, BUKAN Zach walau dia yang di-attach lebih dulu.
    $taskWithAliceAndZach = createAllTasksTask($project, $admin, ['title' => 'Task Alice dan Zach']);
    $taskWithAliceAndZach->assignees()->attach([$zach->id, $alice->id]);

    $response = $this->actingAs($admin)->get(route('tasks.all').'?'.http_build_query(['sort' => 'assignee', 'direction' => 'asc']));

    $response->assertInertia(fn ($page) => $page
        ->where('tasks.data.0.id', $taskWithAliceAndZach->id) // "Alice" < "Zach"
        ->where('tasks.data.1.id', $taskWithZach->id));
});

test('task_type filter narrows to the selected category', function () {
    $admin = User::factory()->admin()->create();
    $project = createAllTasksProject($admin);

    // Revisi 2026-08-07 (permintaan Boss): daily/weekly/monthly dicabut dari
    // whitelist filter (FilterAllTasksRequest) -- task hasil generate template
    // sekarang task_type-nya teks bebas ringkasan jadwal, bukan kategori tetap.
    // tentative/project TETAP kategori tetap untuk task manual, jadi itu yang
    // dites di sini.
    $tentativeTask = createAllTasksTask($project, $admin, ['task_type' => 'tentative']);
    createAllTasksTask($project, $admin, ['task_type' => 'project']);

    $response = $this->actingAs($admin)->get(route('tasks.all').'?'.http_build_query(['task_type' => ['tentative']]));

    $response->assertInertia(fn ($page) => $page->has('tasks.data', 1)->where('tasks.data.0.id', $tentativeTask->id));
});

test('a task from another organization never appears on Semua Tugas even with a guessed project_id (F-15)', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $ownProject = createAllTasksProject($admin);
    $foreignProject = createAllTasksProject($otherAdmin);

    createAllTasksTask($ownProject, $admin);
    createAllTasksTask($foreignProject, $otherAdmin);

    $response = $this->actingAs($admin)->get(route('tasks.all').'?'.http_build_query(['project_id' => $foreignProject->id]));

    $response->assertInertia(fn ($page) => $page->has('tasks.data', 0));
});

test('dropdown filter assignee di Semua Tugas TIDAK menawarkan member nonaktif (bug fix 2026-08-08)', function () {
    $admin = User::factory()->admin()->create();
    $activeMember = User::factory()->create(['organization_id' => $admin->organization_id, 'is_active' => true]);
    $inactiveMember = User::factory()->create(['organization_id' => $admin->organization_id, 'is_active' => false]);

    $response = $this->actingAs($admin)->get(route('tasks.all'));

    $response->assertInertia(fn ($page) => $page->where('members', fn ($members) => collect($members)->pluck('id')->contains($activeMember->id)
        && ! collect($members)->pluck('id')->contains($inactiveMember->id)));
});

test('jumlah query Semua Tugas TETAP KONSTAN walau task bertambah banyak (F-85)', function () {
    $admin = User::factory()->admin()->create();
    $project = createAllTasksProject($admin);
    $members = User::factory()->count(3)->create(['organization_id' => $admin->organization_id]);
    $project->members()->sync([$admin->id, ...$members->pluck('id')]);

    foreach ($members as $member) {
        $task = createAllTasksTask($project, $admin);
        $task->assignees()->sync([$member->id]);
    }

    // Pemanasan (pola sama LeaderboardTest/DashboardCommandCenterTest F-85).
    $this->actingAs($admin)->get(route('tasks.all'));

    DB::enableQueryLog();
    $this->actingAs($admin)->get(route('tasks.all'))->assertOk();
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    $moreMembers = User::factory()->count(8)->create(['organization_id' => $admin->organization_id]);
    $project->members()->attach($moreMembers->pluck('id'));
    foreach ($moreMembers as $member) {
        $task = createAllTasksTask($project, $admin);
        $task->assignees()->sync([$member->id]);
    }

    DB::enableQueryLog();
    $this->actingAs($admin)->get(route('tasks.all'))->assertOk();
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});
