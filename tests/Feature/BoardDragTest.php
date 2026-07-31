<?php

/**
 * ==========================================================
 * MODUL       : BoardDragTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi SERVER-SIDE drop kartu Board (v1.0 H2, F-110/F-111) —
 *               drag visual tidak bisa diuji Pest, jadi ini menguji endpoint YANG
 *               SAMA dipanggil drop (`tasks.status`, identik TaskStatusCell) untuk
 *               membuktikan F-45 tetap ditegakkan server meski client sudah
 *               memfilter kolom tak-sah, dan C3 (segmen atas nama assignee, bukan
 *               pelaku drag) benar.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::updateStatus(), TaskTransitionService, TaskObserver
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test C3 (segmen bukan atas nama admin) adalah pagar SATU-SATUNYA
 *               untuk bug yang ditemukan sesi ini — tanpa ini, admin yang sekadar
 *               menggeser kartu Board diam-diam tercatat sebagai "pekerja" di data KPI.
 * ==========================================================
 */

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

function createDragProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Board Drag Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createDragTask(Project $project, TaskStatus $status, User $admin, array $assigneeIds = []): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Drag task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    if ($assigneeIds) {
        $task->assignees()->sync($assigneeIds);
    }

    return $task;
}

test('drop maju +1 (TODO -> IN PROGRESS) changes status and opens a segment (F-41)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDragProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createDragTask($project, $todo, $admin, [$member->id]);

    $response = $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($task->fresh()->task_status_id)->toBe($inProgress->id)
        ->and($task->timeSegments()->whereNull('ended_at')->count())->toBe(1);
});

test('a jump drop (TODO -> DONE) is REJECTED server-side even though the client should have prevented it (F-45)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDragProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();
    $task = createDragTask($project, $todo, $admin, [$member->id]);

    $response = $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $done->id,
    ]);

    $response->assertSessionHasErrors('task_status_id');
    expect($task->fresh()->task_status_id)->toBe($todo->id);
});

test('a backward drop is allowed and does not touch rejection_count', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDragProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createDragTask($project, $inProgress, $admin, [$member->id]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $member->id,
        'started_at' => now()->subMinutes(10),
    ]);

    $response = $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $todo->id,
    ]);

    $response->assertSessionDoesntHaveErrors();
    $task->refresh();
    expect($task->task_status_id)->toBe($todo->id)
        ->and($task->rejection_count)->toBe(0);
});

test('a drop is recorded in activity_log as status_changed (F-51)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDragProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createDragTask($project, $todo, $admin, [$member->id]);

    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    expect(ActivityLog::where('subject_type', Task::class)
        ->where('subject_id', $task->id)
        ->where('event', 'status_changed')
        ->exists())->toBeTrue();
});

test('a member cannot drag/drop a task assigned to someone else (F-95)', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDragProject($admin, [$assignee->id, $outsider->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createDragTask($project, $todo, $admin, [$assignee->id]);

    $response = $this->actingAs($outsider)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ]);

    $response->assertForbidden();
});

test('admin dropping a task with a single assignee opens the segment for the ASSIGNEE, not the admin (C3)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDragProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createDragTask($project, $todo, $admin, [$member->id]);

    $this->actingAs($admin)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    $segment = $task->timeSegments()->whereNull('ended_at')->firstOrFail();
    expect($segment->user_id)->toBe($member->id)
        ->and($segment->user_id)->not->toBe($admin->id);
});

test('admin dropping a task with NO assignee opens no segment at all (C3, ambiguous worker)', function () {
    $admin = User::factory()->admin()->create();
    $project = createDragProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createDragTask($project, $todo, $admin); // tanpa assignee

    $this->actingAs($admin)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    expect($task->fresh()->task_status_id)->toBe($inProgress->id)
        ->and($task->timeSegments()->count())->toBe(0);
});

test('admin dropping a task with MULTIPLE assignees opens no segment at all (C3, ambiguous worker)', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDragProject($admin, [$memberA->id, $memberB->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createDragTask($project, $todo, $admin, [$memberA->id, $memberB->id]);

    $this->actingAs($admin)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    expect($task->fresh()->task_status_id)->toBe($inProgress->id)
        ->and($task->timeSegments()->count())->toBe(0);
});

test('a member dropping their OWN task still opens the segment under their own name as usual', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDragProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createDragTask($project, $todo, $admin, [$member->id]);

    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    $segment = $task->timeSegments()->whereNull('ended_at')->firstOrFail();
    expect($segment->user_id)->toBe($member->id);
});
