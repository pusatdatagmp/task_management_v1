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

test('checklist_items dikirim saat create task langsung tersimpan sebagai task_checklist_items (revisi 2026-08-06 item 5)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);

    $response = $this->actingAs($admin)->post(route('tasks.store', $project), [
        'title' => 'Task dengan checklist saat create',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 10,
        'due_date' => now()->addWeek()->toDateTimeString(),
        'checklist_items' => ['Langkah 1', 'Langkah 2'],
    ]);

    $response->assertRedirect(route('tasks.index', $project));

    $task = Task::where('project_id', $project->id)->where('title', 'Task dengan checklist saat create')->firstOrFail();
    $items = $task->checklistItems()->orderBy('position')->get();

    expect($items->pluck('text')->all())->toBe(['Langkah 1', 'Langkah 2'])
        ->and($items->pluck('organization_id')->unique()->all())->toBe([$admin->organization_id]);
});

test('create task TANPA checklist_items tidak error, task lahir tanpa checklist (revisi 2026-08-06 item 5)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);

    $response = $this->actingAs($admin)->post(route('tasks.store', $project), [
        'title' => 'Task tanpa checklist',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 10,
        'due_date' => now()->addWeek()->toDateTimeString(),
    ]);

    $response->assertRedirect(route('tasks.index', $project));

    $task = Task::where('project_id', $project->id)->where('title', 'Task tanpa checklist')->firstOrFail();
    expect($task->checklistItems()->count())->toBe(0);
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

/**
 * PERMINTAAN BOSS (audit): subtask HANYA boleh dibuat task.manage (admin by
 * default) -- member SELAIN itu cuma boleh update progres lewat checklist
 * (lihat ChecklistItemCrudTest -- assignee boleh toggle/tambah item, TIDAK
 * boleh ubah teks/hapus). Store() SATU jalur untuk task & subtask (parent_task_id
 * cuma field opsional di form yang sama), gate-nya SUDAH task.manage-only
 * (StoreTaskRequest::authorize(), F-90) -- test ini KHUSUS mengunci skenario
 * parent_task_id terisi (subtask), bukan cuma task biasa yang sudah dites di atas.
 */
test('member selain task.manage TIDAK bisa membuat subtask (audit F-90)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project->members()->sync([$admin->id, $member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->orderBy('position')->firstOrFail();

    $parent = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Parent audit',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    // Sengaja assign member sebagai assignee parent -- membuktikan bahkan
    // assignee task itu SENDIRI tidak otomatis dapat hak buat subtask (beda
    // dari checklist item yang MEMANG boleh ditambah assignee).
    $parent->assignees()->sync([$member->id]);

    $response = $this->actingAs($member)->post(route('tasks.store', $project), [
        'title' => 'Subtask oleh member',
        'task_type' => 'tentative',
        'estimated_minutes' => 30,
        'points' => 0,
        'due_date' => now()->addWeek()->toDateTimeString(),
        'parent_task_id' => $parent->id,
    ]);

    $response->assertForbidden();
    expect(Task::where('title', 'Subtask oleh member')->count())->toBe(0);
});

test('task.manage bisa membuat subtask (audit F-90 -- kontrol positif)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->orderBy('position')->firstOrFail();

    $parent = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Parent audit 2',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post(route('tasks.store', $project), [
        'title' => 'Subtask oleh admin',
        'task_type' => 'tentative',
        'estimated_minutes' => 30,
        'points' => 0,
        'due_date' => now()->addWeek()->toDateTimeString(),
        'parent_task_id' => $parent->id,
    ]);

    $response->assertRedirect(route('tasks.index', $project));
    expect(Task::where('title', 'Subtask oleh admin')->where('parent_task_id', $parent->id)->exists())->toBeTrue();
});

test('progressPercent(): checklist kosong -> 0 kalau belum selesai (revisi 2026-08-06 item 1)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = Task::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id, 'task_status_id' => $todo->id,
        'title' => 'Tanpa checklist', 'task_type' => 'tentative', 'estimated_minutes' => 60,
        'due_date' => now()->addWeek(), 'created_by' => $admin->id,
    ]);

    expect($task->progressPercent())->toBe(0);
});

test('progressPercent(): task Selesai SELALU 100 walau checklist belum lengkap -- freeze (revisi 2026-08-06 item 1)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();
    $task = Task::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id, 'task_status_id' => $done->id,
        'title' => 'Selesai dengan checklist bolong', 'task_type' => 'tentative', 'estimated_minutes' => 60,
        'due_date' => now()->addWeek(), 'created_by' => $admin->id,
    ]);
    $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'A', 'position' => 0, 'is_done' => true]);
    $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'B', 'position' => 1, 'is_done' => false]);

    expect($task->fresh()->progressPercent())->toBe(100);
});

test('progressPercent(): checklist 2 dari 4 dicentang -> 50 (revisi 2026-08-06 item 1)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = Task::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id, 'task_status_id' => $inProgress->id,
        'title' => 'Checklist separuh', 'task_type' => 'tentative', 'estimated_minutes' => 60,
        'due_date' => now()->addWeek(), 'created_by' => $admin->id,
    ]);
    foreach ([true, true, false, false] as $i => $done) {
        $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => "Item {$i}", 'position' => $i, 'is_done' => $done]);
    }

    expect($task->fresh()->progressPercent())->toBe(50);
});

test('progressPercent() dari withChecklistCounts() (listing) IDENTIK dengan dari relasi eager-load (detail) -- satu sumber (revisi 2026-08-06 item 1)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTaskProject($admin);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = Task::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id, 'task_status_id' => $inProgress->id,
        'title' => 'Konsistensi listing vs detail', 'task_type' => 'tentative', 'estimated_minutes' => 60,
        'due_date' => now()->addWeek(), 'created_by' => $admin->id,
    ]);
    foreach ([true, false, false] as $i => $done) {
        $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => "Item {$i}", 'position' => $i, 'is_done' => $done]);
    }

    // Jalur "detail" (F-123 pattern show()): relasi checklistItems eager-loaded penuh.
    $viaRelation = Task::with(['taskStatus', 'checklistItems'])->findOrFail($task->id);

    // Jalur "listing" (index()/all()/myTasks()/Board): withCount() alias, TANPA
    // eager-load relasi penuh -- membuktikan cabang fallback withCount() di
    // progressPercent() menghasilkan angka SAMA PERSIS, bukan rumus kedua.
    $viaWithCount = Task::withCount([
        'checklistItems as checklist_items_count',
        'checklistItems as checklist_done_items_count' => fn ($q) => $q->where('is_done', true),
    ])->with('taskStatus')->findOrFail($task->id);

    expect($viaRelation->progressPercent())->toBe(33)
        ->and($viaWithCount->progressPercent())->toBe(33);
});
