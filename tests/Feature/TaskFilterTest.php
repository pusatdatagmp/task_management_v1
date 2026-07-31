<?php

/**
 * ==========================================================
 * MODUL       : TaskFilterTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi filter/sort server-side task index (Hari-5 §C).
 *               Filter kombinasi HARUS irisan (AND), bukan gabungan (OR) — C5/C8.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::index(), FilterTaskRequest
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau filter kombinasi ternyata OR bukan AND, admin yang menyaring
 *               "task saya + terlambat" akan melihat SEMUA task terlambat siapa pun
 *               — bukan cuma miliknya. Payload salah, keputusan salah.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

function createFilterProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Filter Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

function createFilterTask(Project $project, User $admin, array $overrides = []): Task
{
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Filter task '.uniqid(),
        'task_type' => 'tentative',
        'priority' => 'normal',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
        ...$overrides,
    ]);
}

test('filtering by status only returns tasks in that status', function () {
    $admin = User::factory()->admin()->create();
    $project = createFilterProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $todoTask = createFilterTask($project, $admin, ['task_status_id' => $todo->id]);
    createFilterTask($project, $admin, ['task_status_id' => $inProgress->id]);

    $response = $this->actingAs($admin)->get(route('tasks.index', $project).'?'.http_build_query(['status' => [$todo->id]]));

    $response->assertInertia(fn ($page) => $page->component('tasks/index')->has('tasks.data', 1)->where('tasks.data.0.id', $todoTask->id));
});

test('filtering by assignee only returns tasks assigned to that user', function () {
    $admin = User::factory()->admin()->create();
    $project = createFilterProject($admin);
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project->members()->sync([$admin->id, $memberA->id, $memberB->id]);

    $taskA = createFilterTask($project, $admin);
    $taskA->assignees()->sync([$memberA->id]);
    $taskB = createFilterTask($project, $admin);
    $taskB->assignees()->sync([$memberB->id]);

    $response = $this->actingAs($admin)->get(route('tasks.index', $project).'?'.http_build_query(['assignee' => [$memberA->id]]));

    $response->assertInertia(fn ($page) => $page->component('tasks/index')->has('tasks.data', 1)->where('tasks.data.0.id', $taskA->id));
});

test('overdue filter returns only past-due, not-yet-completed tasks (C8, F-44)', function () {
    $admin = User::factory()->admin()->create();
    $project = createFilterProject($admin);
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    $overdueTask = createFilterTask($project, $admin, ['due_date' => now()->subDays(2)]);
    createFilterTask($project, $admin, ['due_date' => now()->addDays(2)]); // belum jatuh tempo
    createFilterTask($project, $admin, ['due_date' => now()->subDays(3), 'task_status_id' => $done->id]); // terlambat TAPI sudah selesai

    $response = $this->actingAs($admin)->get(route('tasks.index', $project).'?'.http_build_query(['due' => 'overdue']));

    $response->assertInertia(fn ($page) => $page->component('tasks/index')->has('tasks.data', 1)->where('tasks.data.0.id', $overdueTask->id));
});

test('combining status and priority filters is an intersection, not a union', function () {
    $admin = User::factory()->admin()->create();
    $project = createFilterProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $match = createFilterTask($project, $admin, ['task_status_id' => $todo->id, 'priority' => 'urgent']);
    createFilterTask($project, $admin, ['task_status_id' => $todo->id, 'priority' => 'low']); // status cocok, priority tidak
    createFilterTask($project, $admin, ['task_status_id' => $inProgress->id, 'priority' => 'urgent']); // priority cocok, status tidak

    $response = $this->actingAs($admin)->get(
        route('tasks.index', $project).'?'.http_build_query(['status' => [$todo->id], 'priority' => ['urgent']])
    );

    $response->assertInertia(fn ($page) => $page->component('tasks/index')->has('tasks.data', 1)->where('tasks.data.0.id', $match->id));
});

test('a user who is not a project member cannot view its task list', function () {
    $admin = User::factory()->admin()->create();
    $project = createFilterProject($admin);
    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($outsider)->get(route('tasks.index', $project));

    $response->assertForbidden();
});

test('a project member can view the task list even without admin role', function () {
    $admin = User::factory()->admin()->create();
    $project = createFilterProject($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project->members()->sync([$admin->id, $member->id]);
    createFilterTask($project, $admin);

    $response = $this->actingAs($member)->get(route('tasks.index', $project));

    $response->assertOk();
});
