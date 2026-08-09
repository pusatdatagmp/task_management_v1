<?php

/**
 * ==========================================================
 * MODUL       : KpiScoringTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : v1.4 KPI-1 (F-166/F-167) — buktikan freeze kpi_score saat approve
 *               pakai config org-level SAAT ITU (tak retroaktif, F-39), on-time
 *               reuse Task::isOnTime() (F-47/F-109, satu sumber dengan
 *               LeaderboardService), master toggle kpi_enabled, dan registry
 *               strategy key->class (pola F-158).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::approve(), TaskTransitionService, KpiStrategyRegistry
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Salah di sini = kpi_score task nyata beku dengan angka salah
 *               selamanya (F-39/F-167 — tidak bisa dihitung ulang).
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\Kpi\KpiStrategyRegistry;
use App\Services\Kpi\Strategies\SimpleTimelinessStrategy;
use Illuminate\Support\Carbon;

function createKpiProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'KPI Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

// SUMBER: task dibuat LANGSUNG di status is_review (bukan lewat alur Mulai/Submit
// H7) -- KPI-1 fokus ke PERHITUNGAN skor saat approve(), bukan alur transisi
// status itu sendiri (sudah dites TaskTransitionTest.php). Pola sama
// createApprovedTask() di LeaderboardTest.php.
function createKpiReviewTask(Project $project, User $admin, Carbon $dueDate): Task
{
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();

    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $review->id,
        'title' => 'KPI task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => $dueDate,
        'created_by' => $admin->id,
    ]);
}

// =============================================================================
// D1 -- freeze on-time=5/telat=3 (default config org), on-time pakai
// original_due_date bukan due_date yang sudah digeser (F-47)
// =============================================================================

test('approve task on-time membekukan kpi_score = kpi_points_ontime default (D1)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    $task = createKpiReviewTask($project, $admin, $anchor->copy()->addDay());

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 4])
        ->assertSessionDoesntHaveErrors();

    expect($task->fresh()->kpi_score)->toBe(5);
});

test('approve task TELAT membekukan kpi_score = kpi_points_late default, pakai original_due_date bukan due_date tergeser (D1/F-47)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    // due_date SEKARANG sudah digeser maju (mis. extension disetujui), tapi
    // original_due_date (tenggat ASLI) sudah lewat -- kalau strategy salah pakai
    // due_date, task ini akan tampak on-time (palsu). Guard sama seperti
    // LeaderboardTest C3.
    $task = createKpiReviewTask($project, $admin, $anchor->copy()->addDays(10));
    $task->update(['original_due_date' => $anchor->copy()->subDay()]);

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 3])
        ->assertSessionDoesntHaveErrors();

    expect($task->fresh()->kpi_score)->toBe(3);
});

// =============================================================================
// D2 -- config diubah SETELAH approve TIDAK menulis ulang skor task lama (F-39/F-167)
// =============================================================================

test('ubah kpi_points_ontime SETELAH task lama di-approve tidak mengubah skor beku task lama (D2/F-39)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    $oldTask = createKpiReviewTask($project, $admin, $anchor->copy()->addDay());
    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $oldTask]), ['quality_rating' => 5])
        ->assertSessionDoesntHaveErrors();
    expect($oldTask->fresh()->kpi_score)->toBe(5);

    $admin->organization->update(['kpi_points_ontime' => 10]);

    $newTask = createKpiReviewTask($project, $admin, $anchor->copy()->addDay());
    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $newTask]), ['quality_rating' => 5])
        ->assertSessionDoesntHaveErrors();

    expect($newTask->fresh()->kpi_score)->toBe(10)
        ->and($oldTask->fresh()->kpi_score)->toBe(5); // TETAP beku, tidak ikut config baru.
});

// =============================================================================
// D3 -- registry: key tak dikenal error jelas, key valid resolve strategy benar
// (pola F-158/F-160 -- data korup dilempar sebagai error, bukan ditelan diam-diam)
// =============================================================================

test('KpiStrategyRegistry melempar error jelas untuk key tak dikenal, resolve strategy benar untuk key valid (D3)', function () {
    $registry = new KpiStrategyRegistry;

    expect($registry->resolve('simple_timeliness'))->toBeInstanceOf(SimpleTimelinessStrategy::class);
    expect(fn () => $registry->resolve('unknown_strategy'))->toThrow(UnhandledMatchError::class);
});

// =============================================================================
// D4 -- master toggle kpi_enabled=false -> approve TIDAK menghitung kpi_score
// =============================================================================

test('kpi_enabled=false -- approve tidak menghitung kpi_score, tetap null (D4/F-166)', function () {
    $admin = User::factory()->admin()->create();
    $admin->organization->update(['kpi_enabled' => false]);
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    $task = createKpiReviewTask($project, $admin, $anchor->copy()->addDay());

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 4])
        ->assertSessionDoesntHaveErrors();

    expect($task->fresh()->kpi_score)->toBeNull();
});
