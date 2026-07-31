<?php

/**
 * ==========================================================
 * MODUL       : LiveTaskCounterTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi counter live (F-94, HARDEN pasca-v0.5) — akumulasi via
 *               BusinessHoursCalculator yang sudah ada (F-57/F-66/F-43), isolasi
 *               per-user pada task multi-assignee (B5), dan gating via flag
 *               is_work_state (F-44). DIUJI di 3 permukaan: detail task, My Tasks,
 *               List View — satu service (LiveTaskCounter), tiga pemanggil.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::show()/myTasks()/index(), LiveTaskCounter
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "Jumat sore -> Senin pagi" adalah gerbang F-57/F-94 yang sama
 *               dengan BusinessHoursCalculatorTest — kalau live counter lolos gerbang
 *               ini tapi cara pakainya salah (mis. lupa cap), UI menampilkan wall-clock
 *               mentah (65 jam) yang menyesatkan admin sebelum task di-approve.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia;

function createLiveCounterProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Live Counter Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

// SUMBER: jendela Sen-Jum 08:00-17:00 — sama persis dengan fixture
// BusinessHoursCalculatorTest (tests/Unit) supaya nilai gerbang F-57 (120 menit)
// bisa dibandingkan apel-ke-apel antara level kalkulator dan level HTTP di sini.
function seedBusinessHoursSchedule(User $admin, Carbon $anchor): void
{
    WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $anchor->copy()->subMonth()->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

function createLiveCounterTask(Project $project, TaskStatus $status, User $admin, ?Carbon $dueDate = null): Task
{
    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Live counter task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 300,
        'due_date' => $dueDate ?? now()->addWeek(),
        'created_by' => $admin->id,
    ]);
}

test('task is_work_state dengan segmen terbuka -> live_counter akumulasi benar', function () {
    $admin = User::factory()->admin()->create();
    $project = createLiveCounterProject($admin);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $anchor = Carbon::create(2024, 1, 8, 9, 0, 0); // Senin 09:00
    seedBusinessHoursSchedule($admin, $anchor);

    $task = createLiveCounterTask($project, $inProgress, $admin);
    $task->assignees()->sync([$admin->id]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $anchor->copy(),
        'ended_at' => null,
    ]);

    $this->travelTo($anchor->copy()->addMinutes(30)); // sekarang Senin 09:30

    $response = $this->actingAs($admin)->get(route('tasks.show', [$project, $task]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('task.live_counter.accumulated_minutes', 30)
        ->where('task.live_counter.is_in_work_window', true));
});

test('segmen dibuka Jumat sore, sekarang Senin pagi -> akumulasi = jam kerja saja, bukan wall-clock (F-57/F-94)', function () {
    $admin = User::factory()->admin()->create();
    $project = createLiveCounterProject($admin);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $friday = Carbon::create(2024, 1, 5, 16, 0, 0); // Jumat 16:00
    seedBusinessHoursSchedule($admin, $friday);

    $task = createLiveCounterTask($project, $inProgress, $admin);
    $task->assignees()->sync([$admin->id]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $friday->copy(),
        'ended_at' => null,
    ]);

    $this->travelTo(Carbon::create(2024, 1, 8, 9, 0, 0)); // Senin 09:00 -> wall-clock = 3900 menit

    $response = $this->actingAs($admin)->get(route('tasks.show', [$project, $task]));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        // Gerbang F-57 persis: Jumat 16:00-17:00 (60) + Senin 08:00-09:00 (60) = 120.
        ->where('task.live_counter.accumulated_minutes', 120)
        ->where('task.live_counter.is_in_work_window', true));
});

test('task bukan is_work_state -> live_counter null (tidak ada counter berjalan)', function () {
    $admin = User::factory()->admin()->create();
    $project = createLiveCounterProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = Carbon::create(2024, 1, 8, 9, 0, 0);
    seedBusinessHoursSchedule($admin, $anchor);
    $this->travelTo($anchor);

    $task = createLiveCounterTask($project, $todo, $admin);
    $task->assignees()->sync([$admin->id]);

    $response = $this->actingAs($admin)->get(route('tasks.show', [$project, $task]));

    $response->assertInertia(fn (AssertableInertia $page) => $page->where('task.live_counter', null));
});

test('multi-assignee: user hanya melihat counter segmennya sendiri, bukan gabungan (B5)', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createLiveCounterProject($admin, [$memberA->id, $memberB->id]);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $anchor = Carbon::create(2024, 1, 8, 9, 0, 0); // Senin 09:00
    seedBusinessHoursSchedule($admin, $anchor);

    $task = createLiveCounterTask($project, $inProgress, $admin);
    $task->assignees()->sync([$memberA->id, $memberB->id]);

    // A mulai 09:00, B mulai 09:20 -- DUA segmen terbuka BERSAMAAN di task yang sama.
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $memberA->id,
        'started_at' => $anchor->copy(),
        'ended_at' => null,
    ]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $memberB->id,
        'started_at' => $anchor->copy()->addMinutes(20),
        'ended_at' => null,
    ]);

    $this->travelTo($anchor->copy()->addMinutes(40)); // sekarang 09:40

    $responseA = $this->actingAs($memberA)->get(route('tasks.show', [$project, $task]));
    $responseA->assertInertia(fn (AssertableInertia $page) => $page
        ->where('task.live_counter.accumulated_minutes', 40)); // A: 09:00 -> 09:40

    $responseB = $this->actingAs($memberB)->get(route('tasks.show', [$project, $task]));
    $responseB->assertInertia(fn (AssertableInertia $page) => $page
        ->where('task.live_counter.accumulated_minutes', 20)); // B: 09:20 -> 09:40, BUKAN gabungan (60)
});

test('My Tasks: live_counter tersedia di baris task is_work_state milik user (B2)', function () {
    $admin = User::factory()->admin()->create();
    $project = createLiveCounterProject($admin);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $anchor = Carbon::create(2024, 1, 8, 9, 0, 0);
    seedBusinessHoursSchedule($admin, $anchor);

    // due_date akhir hari ini supaya masuk grup 'today' (D2, myTasks()).
    $task = createLiveCounterTask($project, $inProgress, $admin, $anchor->copy()->endOfDay());
    $task->assignees()->sync([$admin->id]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $anchor->copy(),
        'ended_at' => null,
    ]);

    $this->travelTo($anchor->copy()->addMinutes(15));

    $response = $this->actingAs($admin)->get(route('tasks.my'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('groups.today.0.live_counter.accumulated_minutes', 15));
});

test('List View: live_counter tersedia di baris task work-state (B3)', function () {
    $admin = User::factory()->admin()->create();
    $project = createLiveCounterProject($admin);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $anchor = Carbon::create(2024, 1, 8, 9, 0, 0);
    seedBusinessHoursSchedule($admin, $anchor);

    $task = createLiveCounterTask($project, $inProgress, $admin);
    $task->assignees()->sync([$admin->id]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $anchor->copy(),
        'ended_at' => null,
    ]);

    $this->travelTo($anchor->copy()->addMinutes(10));

    $response = $this->actingAs($admin)->get(route('tasks.index', $project));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tasks.data.0.live_counter.accumulated_minutes', 10));
});
