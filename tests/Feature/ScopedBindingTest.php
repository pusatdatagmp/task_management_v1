<?php

/**
 * ==========================================================
 * MODUL       : ScopedBindingTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi F-76 — {task}/{taskStatus} di URL WAJIB anak dari
 *               {project} di URL yang sama. Task/status milik project lain
 *               HARUS 404, bukan diam-diam ter-load lewat ID yang salah.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController, TaskStatusController, scopeBindings() (routes/web.php, routes/admin.php)
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau test ini lolos padahal scopeBindings() tercabut/salah pasang,
 *               admin bisa mengedit task/status project lain lewat URL yang salah.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

test('a task belonging to another project 404s under the wrong project URL (F-76)', function () {
    $admin = User::factory()->admin()->create();

    $projectA = Project::create(['organization_id' => $admin->organization_id, 'name' => 'Project A', 'owner_id' => $admin->id]);
    TaskStatus::seedDefaults($projectA);
    $statusA = TaskStatus::where('project_id', $projectA->id)->orderBy('position')->firstOrFail();

    $projectB = Project::create(['organization_id' => $admin->organization_id, 'name' => 'Project B', 'owner_id' => $admin->id]);
    TaskStatus::seedDefaults($projectB);
    $statusB = TaskStatus::where('project_id', $projectB->id)->orderBy('position')->firstOrFail();

    $taskInB = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $projectB->id,
        'task_status_id' => $statusB->id,
        'title' => 'Task milik B',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get(route('tasks.edit', [$projectA, $taskInB]));

    $response->assertNotFound();
});

test('a task status belonging to another project 404s under the wrong project URL (F-76)', function () {
    $admin = User::factory()->admin()->create();

    $projectA = Project::create(['organization_id' => $admin->organization_id, 'name' => 'Project A', 'owner_id' => $admin->id]);
    TaskStatus::seedDefaults($projectA);

    $projectB = Project::create(['organization_id' => $admin->organization_id, 'name' => 'Project B', 'owner_id' => $admin->id]);
    TaskStatus::seedDefaults($projectB);
    $statusInB = TaskStatus::where('project_id', $projectB->id)->orderBy('position')->firstOrFail();

    $response = $this->actingAs($admin)->get(route('task-statuses.edit', [$projectA, $statusInB]));

    $response->assertNotFound();
});

test('task update via the wrong project URL is rejected, not silently applied (F-76)', function () {
    $admin = User::factory()->admin()->create();

    $projectA = Project::create(['organization_id' => $admin->organization_id, 'name' => 'Project A', 'owner_id' => $admin->id]);
    TaskStatus::seedDefaults($projectA);

    $projectB = Project::create(['organization_id' => $admin->organization_id, 'name' => 'Project B', 'owner_id' => $admin->id]);
    TaskStatus::seedDefaults($projectB);
    $statusB = TaskStatus::where('project_id', $projectB->id)->orderBy('position')->firstOrFail();

    $taskInB = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $projectB->id,
        'task_status_id' => $statusB->id,
        'title' => 'Task asli',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->put(route('tasks.update', [$projectA, $taskInB]), [
        'title' => 'Diubah lewat project salah',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 0,
        'due_date' => now()->addWeek()->toDateTimeString(),
    ]);

    $response->assertNotFound();
    expect($taskInB->fresh()->title)->toBe('Task asli');
});
