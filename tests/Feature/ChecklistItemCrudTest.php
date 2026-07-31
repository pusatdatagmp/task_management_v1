<?php

/**
 * ==========================================================
 * MODUL       : ChecklistItemCrudTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi kepemilikan checklist item (F-123, keputusan Boss
 *               LANGKAH 0 v1.2 H5): task.manage tambah/ubah-teks/hapus ("syarat
 *               kerja"); assignee task ini mencentang (toggle) DAN boleh menambah
 *               item baru, TAPI tidak bisa ubah teks/hapus item; outsider (bukan
 *               task.manage, bukan assignee) ditolak semua aksi (F-95).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskChecklistItemController
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "assignee tidak bisa hapus/ubah teks" adalah pagar kepemilikan
 *               — kalau lubang, assignee bisa membuang syarat kerja yang ditetapkan
 *               task.manage sebelum submit review, membuat gate F-127 percuma.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskStatus;
use App\Models\User;

function createChecklistCrudProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Checklist CRUD Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createChecklistCrudTask(Project $project, User $admin, array $assigneeIds = []): Task
{
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Checklist CRUD task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync($assigneeIds);

    return $task;
}

test('task.manage bisa tambah item checklist', function () {
    $admin = User::factory()->admin()->create();
    $project = createChecklistCrudProject($admin);
    $task = createChecklistCrudTask($project, $admin);

    $response = $this->actingAs($admin)->post(route('checklist-items.store', [$project, $task]), ['text' => 'Item admin']);

    $response->assertSessionDoesntHaveErrors();
    expect($task->checklistItems()->where('text', 'Item admin')->exists())->toBeTrue();
});

test('assignee bisa tambah item checklist (langkah tambahan yang ditemukan)', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createChecklistCrudProject($admin, [$assignee->id]);
    $task = createChecklistCrudTask($project, $admin, [$assignee->id]);

    $response = $this->actingAs($assignee)->post(route('checklist-items.store', [$project, $task]), ['text' => 'Item assignee']);

    $response->assertSessionDoesntHaveErrors();
    expect($task->checklistItems()->where('text', 'Item assignee')->exists())->toBeTrue();
});

test('outsider (bukan task.manage, bukan assignee) ditolak tambah item checklist (F-95)', function () {
    $admin = User::factory()->admin()->create();
    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createChecklistCrudProject($admin, [$outsider->id]);
    $task = createChecklistCrudTask($project, $admin);

    $response = $this->actingAs($outsider)->post(route('checklist-items.store', [$project, $task]), ['text' => 'Item outsider']);

    $response->assertForbidden();
    expect($task->checklistItems()->where('text', 'Item outsider')->exists())->toBeFalse();
});

test('assignee bisa toggle is_done', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createChecklistCrudProject($admin, [$assignee->id]);
    $task = createChecklistCrudTask($project, $admin, [$assignee->id]);
    $item = $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'x', 'position' => 0]);

    $this->actingAs($assignee)->patch(route('checklist-items.toggle', [$project, $task, $item]))->assertSessionDoesntHaveErrors();

    expect($item->fresh()->is_done)->toBeTrue();
});

test('outsider ditolak toggle is_done (F-95)', function () {
    $admin = User::factory()->admin()->create();
    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createChecklistCrudProject($admin, [$outsider->id]);
    $task = createChecklistCrudTask($project, $admin);
    $item = $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'x', 'position' => 0]);

    $this->actingAs($outsider)->patch(route('checklist-items.toggle', [$project, $task, $item]))->assertForbidden();

    expect($item->fresh()->is_done)->toBeFalse();
});

test('assignee TIDAK bisa ubah teks item (task.manage only)', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createChecklistCrudProject($admin, [$assignee->id]);
    $task = createChecklistCrudTask($project, $admin, [$assignee->id]);
    $item = $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'Teks asli', 'position' => 0]);

    $this->actingAs($assignee)->put(route('checklist-items.update', [$project, $task, $item]), ['text' => 'Diubah assignee'])->assertForbidden();

    expect($item->fresh()->text)->toBe('Teks asli');
});

test('task.manage bisa ubah teks item', function () {
    $admin = User::factory()->admin()->create();
    $project = createChecklistCrudProject($admin);
    $task = createChecklistCrudTask($project, $admin);
    $item = $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'Teks asli', 'position' => 0]);

    $this->actingAs($admin)->put(route('checklist-items.update', [$project, $task, $item]), ['text' => 'Teks baru'])->assertSessionDoesntHaveErrors();

    expect($item->fresh()->text)->toBe('Teks baru');
});

test('assignee TIDAK bisa hapus item (task.manage only)', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createChecklistCrudProject($admin, [$assignee->id]);
    $task = createChecklistCrudTask($project, $admin, [$assignee->id]);
    $item = $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'x', 'position' => 0]);

    $this->actingAs($assignee)->delete(route('checklist-items.destroy', [$project, $task, $item]))->assertForbidden();

    expect(TaskChecklistItem::whereKey($item->id)->exists())->toBeTrue();
});

test('task.manage bisa hapus item', function () {
    $admin = User::factory()->admin()->create();
    $project = createChecklistCrudProject($admin);
    $task = createChecklistCrudTask($project, $admin);
    $item = $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'x', 'position' => 0]);

    $this->actingAs($admin)->delete(route('checklist-items.destroy', [$project, $task, $item]))->assertSessionDoesntHaveErrors();

    expect(TaskChecklistItem::whereKey($item->id)->exists())->toBeFalse();
});
