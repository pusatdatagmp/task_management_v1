<?php

/**
 * ==========================================================
 * MODUL       : BoardViewTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi papan Kanban (v1.0 H1, F-109) — kolom dari status project
 *               (F-44, urut position), kartu di kolom benar, subtask BUKAN kartu
 *               terpisah (A4), gating F-95, filter server-side, dan counter live
 *               di kartu SAMA SUMBER dengan LiveTaskCounter (F-94 — bukti board
 *               tidak menghitung ulang).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : BoardController::index()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test konsistensi counter adalah pagar SATU-SATUNYA F-109 untuk
 *               Board — kalau board diam-diam mulai menghitung angkanya sendiri
 *               di masa depan, test ini akan pecah lebih dulu daripada Boss sadar
 *               ada dua sumber realisasi yang bisa drift.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\LiveTaskCounter;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

function createBoardProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Board Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createBoardTask(Project $project, TaskStatus $status, User $admin, array $assigneeIds = [], array $overrides = []): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Board task '.uniqid(),
        'task_type' => 'tentative',
        'priority' => 'normal',
        'estimated_minutes' => 60,
        'points' => 5,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
        ...$overrides,
    ]);

    if ($assigneeIds) {
        $task->assignees()->sync($assigneeIds);
    }

    return $task;
}

test('board renders columns from project statuses, ordered by position (F-44)', function () {
    $admin = User::factory()->admin()->create();
    $project = createBoardProject($admin);

    $response = $this->actingAs($admin)->get(route('tasks.board', $project));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('tasks/board')
        ->has('columns', 4)
        ->where('columns.0.name', 'TODO')
        ->where('columns.1.name', 'IN PROGRESS')
        ->where('columns.2.name', 'REVIEW')
        ->where('columns.3.name', 'DONE'));
});

test('cards appear in the correct column matching their task_status_id', function () {
    $admin = User::factory()->admin()->create();
    $project = createBoardProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();

    $todoTask = createBoardTask($project, $todo, $admin);
    $reviewTask = createBoardTask($project, $review, $admin);

    $response = $this->actingAs($admin)->get(route('tasks.board', $project));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('columns.0.cards.0.id', $todoTask->id)
        ->where('columns.2.cards.0.id', $reviewTask->id)
        ->has('columns.1.cards', 0));
});

test('a member of another project gets a 404 on the board (F-95)', function () {
    $admin = User::factory()->admin()->create();
    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createBoardProject($admin);

    $response = $this->actingAs($outsider)->get(route('tasks.board', $project));

    $response->assertNotFound();
});

test('a member of the project can view its board', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createBoardProject($admin, [$member->id]);

    $response = $this->actingAs($member)->get(route('tasks.board', $project));

    $response->assertOk();
});

test('assignee filter only returns cards for that assignee, server-side', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createBoardProject($admin, [$memberA->id, $memberB->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $taskA = createBoardTask($project, $todo, $admin, [$memberA->id]);
    createBoardTask($project, $todo, $admin, [$memberB->id]);

    $response = $this->actingAs($admin)->get(route('tasks.board', [$project, 'assignee' => [$memberA->id]]));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('columns.0.cards', 1)
        ->where('columns.0.cards.0.id', $taskA->id)
        // BUG FIX (audit Boss 2026-08-14, F-176): rule validasi 'integer' cuma
        // MENGECEK, tidak MENGUBAH TIPE -- tanpa array_map('intval', ...) di
        // BoardController, nilai ini tetap string dari query string ("27" bukan
        // 27), checkbox board.tsx (`filters.assignee.includes(m.id)`, m.id NUMBER
        // asli) gagal match walau data SUDAH terfilter benar -- checkbox TIDAK
        // PERNAH tampil tercentang meski filter aktif. Assersi sebelumnya (string)
        // DIPERBARUI ke integer (F-78) -- pola identik TaskFilterTest.php
        // (fix 2026-08-10, TaskController::index()/all()), sekarang BENAR-BENAR
        // konsisten, bukan cuma diklaim konsisten.
        ->where('filters.assignee.0', fn (mixed $v) => $v === $memberA->id && is_int($v)));
});

test('a subtask never appears as its own card, only as children_count on the parent (A4)', function () {
    $admin = User::factory()->admin()->create();
    $project = createBoardProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $parent = createBoardTask($project, $todo, $admin);
    $subtask = createBoardTask($project, $todo, $admin, [], ['parent_task_id' => $parent->id]);

    $response = $this->actingAs($admin)->get(route('tasks.board', $project));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('columns.0.cards', 1)
        ->where('columns.0.cards.0.id', $parent->id)
        ->where('columns.0.cards.0.children_count', 1));

    expect(Task::whereKey($subtask->id)->exists())->toBeTrue(); // subtask tetap ada di DB, cuma tidak jadi kartu
});

test('the live counter shown on a board card is IDENTICAL to LiveTaskCounter called directly (F-94/F-109)', function () {
    // F-130: pin ke hari kerja tetap (Rabu) — tanpa ini, WorkSchedule Sen-Jum
    // di bawah menghasilkan 0 menit overlap tiap Sabtu/Minggu, dan assertion
    // accumulated_minutes>=29 gagal bersyarat-hari (pola F-73). 'next Wednesday'
    // selalu melompat ke Rabu berikutnya dari HARI APA PUN, termasuk dari Rabu
    // itu sendiri — jadi selalu jatuh ke hari kerja, tak pernah ke hari yang sama.
    $this->travelTo(Carbon::parse('next Wednesday')->setTime(10, 0));

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createBoardProject($admin, [$member->id]);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => now()->subMonth()->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '00:00',
        'end_time' => '23:59',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);

    $task = createBoardTask($project, $inProgress, $admin, [$member->id]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $member->id,
        'started_at' => Carbon::now()->subMinutes(30),
    ]);

    $directCounter = (new LiveTaskCounter)->forTask($task->fresh(), $member);

    $response = $this->actingAs($member)->get(route('tasks.board', $project));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('columns.1.cards.0.live_counter.accumulated_minutes', $directCounter['accumulated_minutes']));

    expect($directCounter['accumulated_minutes'])->toBeGreaterThanOrEqual(29);
});
