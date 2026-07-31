<?php

/**
 * ==========================================================
 * MODUL       : EisenhowerPriorityTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi F-122/F-126 lewat form CRUD task — priority_quadrant
 *               nullable (task lama/baru TIDAK dipaksa 'p4'), bisa di-set/ubah via
 *               StoreTaskRequest/UpdateTaskRequest, enum `priority` lama TIDAK
 *               tersentuh oleh perubahan quadrant.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::store()/update()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "nullable, tidak dipaksa p4" adalah pagar F-122/F-126 header
 *               migrasi — kalau ada default diam-diam disisipkan, dashboard donut
 *               (command-center.tsx) akan menampilkan data palsu (task seolah
 *               sudah diklasifikasi padahal belum).
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

function createEisenhowerProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Eisenhower Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

test('task baru tanpa priority_quadrant tersimpan NULL (belum diklasifikasi, bukan p4)', function () {
    $admin = User::factory()->admin()->create();
    $project = createEisenhowerProject($admin);

    $response = $this->actingAs($admin)->post(route('tasks.store', $project), [
        'title' => 'Task tanpa quadrant',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 0,
        'due_date' => now()->addWeek(),
    ]);

    $response->assertSessionDoesntHaveErrors();
    $task = Task::where('project_id', $project->id)->where('title', 'Task tanpa quadrant')->firstOrFail();
    expect($task->priority_quadrant)->toBeNull()
        ->and($task->priority)->toBe('normal'); // enum lama tetap default DB, tidak dipaksa apa pun
});

test('admin bisa set priority_quadrant p1 saat buat task', function () {
    $admin = User::factory()->admin()->create();
    $project = createEisenhowerProject($admin);

    $response = $this->actingAs($admin)->post(route('tasks.store', $project), [
        'title' => 'Task quadrant p1',
        'task_type' => 'tentative',
        'priority_quadrant' => 'p1',
        'estimated_minutes' => 60,
        'points' => 0,
        'due_date' => now()->addWeek(),
    ]);

    $response->assertSessionDoesntHaveErrors();
    $task = Task::where('project_id', $project->id)->where('title', 'Task quadrant p1')->firstOrFail();
    expect($task->priority_quadrant)->toBe('p1');
});

test('quadrant tidak valid ditolak validasi', function () {
    $admin = User::factory()->admin()->create();
    $project = createEisenhowerProject($admin);

    $response = $this->actingAs($admin)->post(route('tasks.store', $project), [
        'title' => 'Task quadrant invalid',
        'task_type' => 'tentative',
        'priority_quadrant' => 'p5',
        'estimated_minutes' => 60,
        'points' => 0,
        'due_date' => now()->addWeek(),
    ]);

    $response->assertSessionHasErrors('priority_quadrant');
    expect(Task::where('project_id', $project->id)->where('title', 'Task quadrant invalid')->exists())->toBeFalse();
});

test('admin bisa ubah priority_quadrant task existing lewat update', function () {
    $admin = User::factory()->admin()->create();
    $project = createEisenhowerProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Task untuk update quadrant',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    expect($task->priority_quadrant)->toBeNull();

    $response = $this->actingAs($admin)->put(route('tasks.update', [$project, $task]), [
        'title' => $task->title,
        'task_type' => 'tentative',
        'priority_quadrant' => 'p3',
        'estimated_minutes' => 60,
        'points' => 0,
        'due_date' => $task->due_date->toDateTimeString(),
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($task->fresh()->priority_quadrant)->toBe('p3');
});
