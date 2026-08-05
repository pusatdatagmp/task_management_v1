<?php

/**
 * ==========================================================
 * MODUL       : BoardDragTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi SERVER-SIDE drop kartu Board (v1.0 H2, F-110/F-111) —
 *               drag visual tidak bisa diuji Pest, jadi ini menguji endpoint YANG
 *               SAMA dipanggil drop (`tasks.status`, identik TaskStatusCell) untuk
 *               membuktikan F-45 tetap ditegakkan server meski client sudah
 *               memfilter kolom tak-sah.
 *               H7/F-138c (F-78): drag SEKARANG status SAJA, NOL segmen — TIDAK
 *               PEDULI berapa assignee-nya (dulu C3/resolveSegmentWorker()
 *               mendisambiguasi tunggal/nol/multi assignee KARENA drag dulu
 *               membuka segmen; sekarang disambiguasi itu MOOT total karena
 *               drag tidak pernah membuka segmen untuk siapa pun). Segmen
 *               HANYA lewat Mulai/Lanjut eksplisit (TaskWorkActionsTest::D7).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::updateStatus(), TaskTransitionService, TaskObserver
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "drag = nol segmen SELALU" adalah pagar F-138c — kalau
 *               regresi (drag diam-diam membuka segmen lagi), pelaku drag
 *               (sering admin merapikan board) akan tercatat sebagai "pekerja"
 *               di data KPI padahal cuma memindah kartu (bug asal C3 kembali).
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

test('drop maju +1 (TODO -> IN PROGRESS) changes status but opens NO segment (H7/F-138c, F-78 -- dulu buka F-41)', function () {
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
        ->and($task->timeSegments()->count())->toBe(0);
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

test('admin dropping a task with a single assignee STILL opens no segment (H7/F-138c, F-78 -- dulu C3 buka atas nama assignee)', function () {
    // F-78: SEBELUM H7, drag membuka segmen dan C3 memastikan atas nama assignee
    // (BUKAN admin yang menggeser). H7/F-138c menghapus pembukaan segmen dari
    // drag SAMA SEKALI -- disambiguasi "siapa pekerjanya" jadi tidak relevan lagi
    // untuk jalur ini, karena TIDAK ADA segmen yang dibuka SIAPA PUN. Assignee
    // membuka sesinya SENDIRI lewat Mulai/Lanjut (TaskWorkActionsTest::D7).
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDragProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createDragTask($project, $todo, $admin, [$member->id]);

    $this->actingAs($admin)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    expect($task->fresh()->task_status_id)->toBe($inProgress->id)
        ->and($task->timeSegments()->count())->toBe(0);
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

test('a member dropping their OWN task ALSO opens no segment now (H7/F-138c, F-78 -- dulu buka atas nama sendiri)', function () {
    // F-78: SEBELUM H7 kasus ini (pelaku=assignee sendiri) adalah kasus "normal"
    // C3 -- segmen dibuka atas nama diri sendiri. H7/F-138c MENYAMARATAKAN drag
    // jadi status-saja TERLEPAS siapa pelakunya (member sendiri ATAU admin) --
    // assignee WAJIB klik Mulai eksplisit sendiri untuk membuka sesi kerjanya
    // (TaskWorkActionsTest::D1/D7), drag/dropdown TIDAK LAGI jalan pintas itu.
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDragProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createDragTask($project, $todo, $admin, [$member->id]);

    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    expect($task->fresh()->task_status_id)->toBe($inProgress->id)
        ->and($task->timeSegments()->count())->toBe(0);
});
