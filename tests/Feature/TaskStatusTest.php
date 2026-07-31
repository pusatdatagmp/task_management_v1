<?php

/**
 * ==========================================================
 * MODUL       : TaskStatusTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi constraint TaskStatus — tepat 1 completed, boleh 0/1
 *               review, min 1 work_state (F-74, radio+checkbox Hari-5 §B), tolak
 *               hapus kalau masih dipakai task (D5), reorder swap atomik (D3).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskStatusController, TaskStatus, Task
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau constraint "tepat 1 completed" bolong, project bisa berakhir
 *               dengan 0 status selesai — task tidak akan pernah bisa masuk F-39 freeze.
 *
 * DEVIASI Hari-5: 3 test versi checkbox lama ("cannot add a second is_completed
 * status", "cannot unset the only is_completed status", "cannot add a second
 * is_review status") DIGANTI di sini — bukan dihapus diam-diam. Ketiganya menguji
 * validasi flagConstraintViolation() yang PERINTAH B4 secara eksplisit minta
 * dihapus untuk is_completed/is_review (radio membuat 0/2 tidak mungkin secara
 * struktur, jadi tidak ada lagi apa pun untuk divalidasi di jalur store/update).
 * Cakupan yang sama sekarang diuji lewat endpoint updateFlags() yang baru.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

function createProjectWithDefaultStatuses(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    TaskStatus::seedDefaults($project);

    return $project;
}

test('a new status is always created neutral — store() no longer accepts flags (F-74)', function () {
    $admin = User::factory()->admin()->create();
    $project = createProjectWithDefaultStatuses($admin);

    $response = $this->actingAs($admin)->post(route('task-statuses.store', $project), [
        'name' => 'ARCHIVED',
        'color' => '#000000',
        // Kalaupun field flag dikirim (klien lama/nakal), harus diabaikan —
        // store() tidak lagi menerimanya sama sekali (StoreTaskStatusRequest
        // cuma punya rules name/color).
        'is_completed' => true,
    ]);

    $response->assertSessionDoesntHaveErrors();
    $created = TaskStatus::where('project_id', $project->id)->where('name', 'ARCHIVED')->firstOrFail();
    expect($created->is_completed)->toBeFalse()
        ->and($created->is_review)->toBeFalse()
        ->and($created->is_work_state)->toBeFalse()
        ->and(TaskStatus::where('project_id', $project->id)->where('is_completed', true)->count())->toBe(1);
});

test('updateFlags moves is_completed to a different status in one submit, still exactly 1 (F-74/B5)', function () {
    $admin = User::factory()->admin()->create();
    $project = createProjectWithDefaultStatuses($admin);
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $workState = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $response = $this->actingAs($admin)->patch(route('task-statuses.update-flags', $project), [
        'is_completed_id' => $todo->id,
        'is_review_id' => null,
        'work_state_ids' => [$workState->id],
    ]);

    $response->assertRedirect(route('task-statuses.index', $project));
    expect($todo->fresh()->is_completed)->toBeTrue()
        ->and($done->fresh()->is_completed)->toBeFalse()
        ->and(TaskStatus::where('project_id', $project->id)->where('is_completed', true)->count())->toBe(1);
});

test('updateFlags requires is_completed_id — cannot submit with none selected', function () {
    $admin = User::factory()->admin()->create();
    $project = createProjectWithDefaultStatuses($admin);
    $workState = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $response = $this->actingAs($admin)->patch(route('task-statuses.update-flags', $project), [
        'is_completed_id' => null,
        'work_state_ids' => [$workState->id],
    ]);

    $response->assertSessionHasErrors('is_completed_id');
});

test('updateFlags allows is_review_id to be empty (F-74/B2 — boleh tidak ada)', function () {
    $admin = User::factory()->admin()->create();
    $project = createProjectWithDefaultStatuses($admin);
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();
    $workState = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $response = $this->actingAs($admin)->patch(route('task-statuses.update-flags', $project), [
        'is_completed_id' => $done->id,
        'is_review_id' => null,
        'work_state_ids' => [$workState->id],
    ]);

    $response->assertRedirect(route('task-statuses.index', $project));
    expect(TaskStatus::where('project_id', $project->id)->where('is_review', true)->count())->toBe(0);
});

test('updateFlags requires at least 1 work_state status (F-41 minimum)', function () {
    $admin = User::factory()->admin()->create();
    $project = createProjectWithDefaultStatuses($admin);
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    $response = $this->actingAs($admin)->patch(route('task-statuses.update-flags', $project), [
        'is_completed_id' => $done->id,
        'work_state_ids' => [],
    ]);

    $response->assertSessionHasErrors('work_state_ids');
});

test('deleting a status still used by a task is rejected (D5)', function () {
    $admin = User::factory()->admin()->create();
    $project = createProjectWithDefaultStatuses($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Some task',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->delete(route('task-statuses.destroy', [$project, $todo]));

    $response->assertSessionHasErrors('status');
    expect(TaskStatus::find($todo->id))->not->toBeNull();
});

test('deleting the current is_completed status is rejected even with no tasks (F-19)', function () {
    $admin = User::factory()->admin()->create();
    $project = createProjectWithDefaultStatuses($admin);
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    $response = $this->actingAs($admin)->delete(route('task-statuses.destroy', [$project, $done]));

    $response->assertSessionHasErrors('status');
    expect(TaskStatus::find($done->id))->not->toBeNull();
});

test('deleting the only is_work_state status is rejected (F-41 minimum)', function () {
    $admin = User::factory()->admin()->create();
    $project = createProjectWithDefaultStatuses($admin);
    $workState = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $response = $this->actingAs($admin)->delete(route('task-statuses.destroy', [$project, $workState]));

    $response->assertSessionHasErrors('status');
    expect(TaskStatus::find($workState->id))->not->toBeNull();
});

test('reorder swaps position atomically between two statuses', function () {
    $admin = User::factory()->admin()->create();
    $project = createProjectWithDefaultStatuses($admin);

    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('position', 1)->firstOrFail();

    $this->actingAs($admin)->patch(route('task-statuses.reorder', [$project, $todo]), [
        'direction' => 'down',
    ]);

    expect($todo->fresh()->position)->toBe(1)
        ->and($inProgress->fresh()->position)->toBe(0);
});

test('member cannot access task status management', function () {
    $admin = User::factory()->admin()->create();
    $project = createProjectWithDefaultStatuses($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($member)->get(route('task-statuses.index', $project));

    $response->assertForbidden();
});
