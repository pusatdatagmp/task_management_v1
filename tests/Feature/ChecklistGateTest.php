<?php

/**
 * ==========================================================
 * MODUL       : ChecklistGateTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi gate F-127 — transisi ke status is_review DITOLAK bila
 *               ada item checklist belum dicentang, checklist KOSONG tetap LOLOS.
 *               Gate ditegakkan SATU tempat (TaskTransitionService::changeStatus(),
 *               F-111) yang dipakai BAIK oleh dropdown (TaskStatusCell) MAUPUN
 *               drag board — keduanya memanggil endpoint HTTP tasks.status yang
 *               SAMA PERSIS, jadi 1 test HTTP di sini membuktikan kedua jalur.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::updateStatus(), TaskTransitionService
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "checklist kosong lolos" adalah pagar F-127 RESOLVED — kalau
 *               ini rusak jadi "wajib >=1 item", SETIAP task lama tanpa checklist
 *               mendadak terkunci tidak bisa direview sama sekali.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

function createChecklistGateProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Checklist Gate Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

function createChecklistGateTask(Project $project, TaskStatus $status, User $admin): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Checklist gate task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync([$admin->id]);

    return $task;
}

test('transisi ke review DITOLAK kalau ada item checklist belum dicentang (F-127)', function () {
    $admin = User::factory()->admin()->create();
    $project = createChecklistGateProject($admin);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createChecklistGateTask($project, $inProgress, $admin);

    $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'Item belum selesai', 'is_done' => false, 'position' => 0]);

    $response = $this->actingAs($admin)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $review->id,
    ]);

    $response->assertSessionHasErrors('task_status_id');
    expect($task->fresh()->task_status_id)->toBe($inProgress->id);
});

test('transisi ke review LOLOS kalau semua item checklist sudah dicentang', function () {
    $admin = User::factory()->admin()->create();
    $project = createChecklistGateProject($admin);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createChecklistGateTask($project, $inProgress, $admin);

    $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'Item selesai', 'is_done' => true, 'position' => 0]);

    $response = $this->actingAs($admin)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $review->id,
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($task->fresh()->task_status_id)->toBe($review->id);
});

test('checklist KOSONG tetap LOLOS transisi ke review (F-127 RESOLVED: gate-only)', function () {
    $admin = User::factory()->admin()->create();
    $project = createChecklistGateProject($admin);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createChecklistGateTask($project, $inProgress, $admin);

    expect($task->checklistItems)->toHaveCount(0);

    $response = $this->actingAs($admin)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $review->id,
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($task->fresh()->task_status_id)->toBe($review->id);
});

/**
 * B3 (edge case, dicatat di prompt): gate dicek SAAT TRANSISI, bukan retroaktif.
 * Task yang SUDAH di review lalu ada item BARU ditambah (belum dicentang) TIDAK
 * ditendang mundur otomatis — dia tetap di review sampai ada transisi BARU yang
 * memicu pengecekan gate lagi.
 */
test('item checklist ditambah SETELAH task sudah di review TIDAK menendang mundur otomatis (B3)', function () {
    $admin = User::factory()->admin()->create();
    $project = createChecklistGateProject($admin);
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createChecklistGateTask($project, $review, $admin);

    $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'Item baru belum dicentang', 'is_done' => false, 'position' => 0]);

    expect($task->fresh()->task_status_id)->toBe($review->id);
});
