<?php

/**
 * ==========================================================
 * MODUL       : KpiScoringTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : v1.4 KPI-1/KPI-2 (F-166/F-167) — buktikan freeze kpi_score saat
 *               approve pakai config org-level SAAT ITU (tak retroaktif, F-39),
 *               on-time reuse Task::isOnTime() (F-109, satu sumber dengan
 *               LeaderboardService — REVISI 2026-08-10: actual_minutes vs
 *               estimated_minutes, due_date TIDAK LAGI dicek), master toggle
 *               kpi_enabled, dan registry strategy key->class (pola F-158).
 *               D1 DIPERBARUI (F-78 — perilaku sengaja diubah instruksi Boss,
 *               due-date-based scenario diganti actual/estimated-based, cakupan
 *               setara: masih 2 test on-time+telat, masih guard "nilai lama tak
 *               dipakai" -- dulu original_due_date vs due_date, sekarang
 *               estimated_minutes SAAT INI vs actual_minutes murni).
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
use App\Models\WorkSchedule;
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

// SUMBER: jendela kerja 24 jam penuh (pola sama DashboardAnomalyTest) -- test
// D1 fokus ke RASIO actual vs estimasi, BUKAN cap jendela kerja F-57 (sudah
// diuji terpisah BusinessHoursCalculatorTest/LiveTaskCounterTest).
function seedKpiFullDaySchedule(User $admin, Carbon $anchor): void
{
    WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $anchor->copy()->subDay()->toDateString(),
        'days_of_week' => range(1, 7),
        'start_time' => '00:00',
        'end_time' => '23:59',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

// SUMBER: task dibuat LANGSUNG di status is_review (bukan lewat alur Mulai/Submit
// H7) -- KPI-1 fokus ke PERHITUNGAN skor saat approve(), bukan alur transisi
// status itu sendiri (sudah dites TaskTransitionTest.php). Pola sama
// createApprovedTask() di LeaderboardTest.php. $dueDate TETAP diisi (kolom NOT
// NULL) tapi TIDAK RELEVAN lagi ke on-time (revisi 2026-08-10) -- nilai apapun sah.
function createKpiReviewTask(Project $project, User $admin, int $estimatedMinutes = 60): Task
{
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();

    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $review->id,
        'title' => 'KPI task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => $estimatedMinutes,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
}

// =============================================================================
// D1 -- freeze on-time=5/telat=3 (default config org). REVISI 2026-08-10
// (keputusan Boss): on-time = actual_minutes<=estimated_minutes, due_date
// TIDAK LAGI dicek sama sekali (ganti dari due-date-based, F-78 -- lihat
// header file utk cakupan setara).
// =============================================================================

test('approve task actual<=estimasi membekukan kpi_score = kpi_points_ontime default (D1)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 8, 0, 0);
    seedKpiFullDaySchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Estimasi 60 menit, realisasi 50 menit (<=60) -- on-time.
    $task = createKpiReviewTask($project, $admin, 60);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $anchor->copy(),
        'ended_at' => $anchor->copy()->addMinutes(50),
    ]);
    $this->travelTo($anchor->copy()->addMinutes(50));

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 4])
        ->assertSessionDoesntHaveErrors();

    $task->refresh();
    expect($task->actual_minutes)->toBe(50) // guard: pastikan actual_minutes SUDAH benar saat kpi_score dihitung (urutan approve()).
        ->and($task->kpi_score)->toBe(5);
});

test('approve task actual>estimasi membekukan kpi_score = kpi_points_late default (D1)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 8, 0, 0);
    seedKpiFullDaySchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Estimasi 60 menit, realisasi 90 menit (>60) -- telat, MESKI due_date
    // masih jauh (createKpiReviewTask default +1 minggu) -- guard due_date
    // BENAR-BENAR tidak lagi dicek (revisi 2026-08-10).
    $task = createKpiReviewTask($project, $admin, 60);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $anchor->copy(),
        'ended_at' => $anchor->copy()->addMinutes(90),
    ]);
    $this->travelTo($anchor->copy()->addMinutes(90));

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 3])
        ->assertSessionDoesntHaveErrors();

    $task->refresh();
    expect($task->actual_minutes)->toBe(90)
        ->and($task->kpi_score)->toBe(3);
});

test('approve task actual TEPAT SAMA estimasi -- batas <= dihitung on-time, bukan telat (D1 boundary)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 8, 0, 0);
    seedKpiFullDaySchedule($admin, $anchor);
    $this->travelTo($anchor);

    $task = createKpiReviewTask($project, $admin, 60);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $anchor->copy(),
        'ended_at' => $anchor->copy()->addMinutes(60),
    ]);
    $this->travelTo($anchor->copy()->addMinutes(60));

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 4])
        ->assertSessionDoesntHaveErrors();

    expect($task->fresh()->kpi_score)->toBe(5);
});

// =============================================================================
// D2 -- config diubah SETELAH approve TIDAK menulis ulang skor task lama (F-39/F-167)
// =============================================================================

test('ubah kpi_points_ontime SETELAH task lama di-approve tidak mengubah skor beku task lama (D2/F-39)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    // Nol time segment -- actual_minutes beku 0 (<=60 estimasi) -- selalu on-time.
    $oldTask = createKpiReviewTask($project, $admin);
    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $oldTask]), ['quality_rating' => 5])
        ->assertSessionDoesntHaveErrors();
    expect($oldTask->fresh()->kpi_score)->toBe(5);

    $admin->organization->update(['kpi_points_ontime' => 10]);

    $newTask = createKpiReviewTask($project, $admin);
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

    $task = createKpiReviewTask($project, $admin);

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 4])
        ->assertSessionDoesntHaveErrors();

    expect($task->fresh()->kpi_score)->toBeNull();
});
