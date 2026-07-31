<?php

/**
 * ==========================================================
 * MODUL       : TaskTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi CRUD task Hari-4 §D — status default (D7), due_date
 *               wajib (F-31), subtask 1 level (F-20), assign lewat sync() bikin
 *               log 'assigned' (F-51), delete = soft delete (F-16).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController, Task, TaskStatus, Project
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test 'assign lewat sync()' adalah pagar utama F-51 — kalau ini
 *               lolos padahal assign lewat DB::table() manual, lubang audit trail
 *               tidak akan pernah ketahuan sampai data KPI sudah bolong permanen.
 * ==========================================================
 */

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

function createTaskProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Task Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

test('creating a task assigns the status with the smallest position (D7)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->orderBy('position')->firstOrFail();

    $response = $this->actingAs($admin)->post(route('tasks.store', $project), [
        'title' => 'Task pertama',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 10,
        'due_date' => now()->addWeek()->toDateTimeString(),
    ]);

    $response->assertRedirect(route('tasks.index', $project));

    $task = Task::where('project_id', $project->id)->latest('id')->firstOrFail();
    expect($task->task_status_id)->toBe($todo->id);
});

test('due_date is required when creating a task (F-31)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);

    $response = $this->actingAs($admin)->post(route('tasks.store', $project), [
        'title' => 'Tanpa due date',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 10,
    ]);

    $response->assertSessionHasErrors('due_date');
    expect(Task::where('project_id', $project->id)->count())->toBe(0);
});

test('subtask cannot be nested 2 levels deep (F-20)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->orderBy('position')->firstOrFail();

    $parent = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Parent',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $child = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'parent_task_id' => $parent->id,
        'title' => 'Child',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post(route('tasks.store', $project), [
        'title' => 'Grandchild',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 0,
        'due_date' => now()->addWeek()->toDateTimeString(),
        'parent_task_id' => $child->id,
    ]);

    $response->assertSessionHasErrors('parent_task_id');
    expect(Task::where('title', 'Grandchild')->count())->toBe(0);
});

test('assigning via sync() logs an assigned activity event (F-51)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project->members()->sync([$admin->id, $member->id]);

    $response = $this->actingAs($admin)->post(route('tasks.store', $project), [
        'title' => 'Task assign',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 0,
        'due_date' => now()->addWeek()->toDateTimeString(),
        'assignees' => [$member->id],
    ]);

    $response->assertRedirect();

    $task = Task::where('title', 'Task assign')->firstOrFail();

    expect($task->assignees()->whereKey($member->id)->exists())->toBeTrue()
        ->and(ActivityLog::where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->where('event', 'assigned')
            ->exists())->toBeTrue();
});

test('deleting a task soft deletes it (F-16)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->orderBy('position')->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Task hapus',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->delete(route('tasks.destroy', [$project, $task]));

    $response->assertRedirect(route('tasks.index', $project));
    expect($task->fresh()->deleted_at)->not->toBeNull();
});

test('deleting a parent task soft deletes its subtasks too (D6)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->orderBy('position')->firstOrFail();

    $parent = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Parent hapus',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $child = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'parent_task_id' => $parent->id,
        'title' => 'Child ikut hapus',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->delete(route('tasks.destroy', [$project, $parent]));

    expect($parent->fresh()->deleted_at)->not->toBeNull()
        ->and($child->fresh()->deleted_at)->not->toBeNull();
});

test('member cannot create a task (F-29)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($member)->post(route('tasks.store', $project), [
        'title' => 'Task member',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 0,
        'due_date' => now()->addWeek()->toDateTimeString(),
    ]);

    $response->assertForbidden();
});
