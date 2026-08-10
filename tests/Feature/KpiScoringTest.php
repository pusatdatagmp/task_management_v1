<?php

/**
 * ==========================================================
 * MODUL       : KpiScoringTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : v1.4 KPI-1/KPI-2 (F-166/F-167) — buktikan freeze kpi_score saat
 *               approve pakai config org-level SAAT ITU (tak retroaktif, F-39),
 *               on-time reuse Task::isOnTime() (F-109, satu sumber dengan
 *               LeaderboardService — REVISI KEDUA 2026-08-10: GABUNGAN due_date
 *               (F-47) DAN actual_minutes<=estimated_minutes, keduanya WAJIB
 *               terpenuhi), master toggle kpi_enabled, dan registry strategy
 *               key->class (pola F-158). D1 DIPERBARUI DUA KALI (F-78 —
 *               perilaku sengaja diubah instruksi Boss): revisi pertama ganti
 *               ke actual/estimated murni, revisi KEDUA (kasus nyata Boss:
 *               submit 2 menit lewat due_date tapi actual masih di bawah
 *               estimasi TETAP HARUS telat) gabung due_date+estimasi (AND).
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
// createApprovedTask() di LeaderboardTest.php. $overrides (pola sama
// LeaderboardTest::createApprovedTask()) -- default due_date +1 minggu (aman,
// tidak dilanggar) supaya test yang HANYA fokus estimasi tak perlu isi due_date.
function createKpiReviewTask(Project $project, User $admin, int $estimatedMinutes = 60, array $overrides = []): Task
{
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();

    return Task::create(array_merge([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $review->id,
        'title' => 'KPI task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => $estimatedMinutes,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ], $overrides));
}

// =============================================================================
// D1 -- freeze on-time=5/telat=3 (default config org). REVISI KEDUA 2026-08-10
// (keputusan Boss, kasus nyata): "tepat waktu" = due_date TERPENUHI DAN
// actual_minutes<=estimated_minutes -- DUA-DUANYA wajib, salah satu gagal = telat.
// =============================================================================

test('due_date terpenuhi DAN actual<=estimasi -- on-time, kpi_score=kpi_points_ontime (D1)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 8, 0, 0);
    seedKpiFullDaySchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Estimasi 60 menit, realisasi 50 menit (<=60); submit SEBELUM due_date.
    $task = createKpiReviewTask($project, $admin, 60, [
        'due_date' => $anchor->copy()->addHour(),
        'submitted_at' => $anchor->copy()->addMinutes(50),
    ]);
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
    expect($task->actual_minutes)->toBe(50) // guard: actual_minutes SUDAH benar saat kpi_score dihitung (urutan approve()).
        ->and($task->kpi_score)->toBe(5);
});

test('KASUS NYATA Boss (2026-08-10): submit lewat due_date TAPI actual<=estimasi -- TETAP telat (D1)', function () {
    // Reproduksi PERSIS laporan Boss: estimasi 10 menit, due_date 12:25, mulai
    // 12:23, submit 12:27 -- actual 4 menit (<=10, "dalam estimasi") TAPI
    // submit 2 menit LEWAT due_date -> WAJIB telat (revisi KEDUA, sebelumnya
    // sempat murni-estimasi dan salah menganggap ini on-time).
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $mulai = Carbon::create(2026, 8, 10, 12, 23, 0);
    $dueDate = Carbon::create(2026, 8, 10, 12, 25, 0);
    $submit = Carbon::create(2026, 8, 10, 12, 27, 0);
    seedKpiFullDaySchedule($admin, $mulai);
    $this->travelTo($mulai);

    $task = createKpiReviewTask($project, $admin, 10, ['due_date' => $dueDate, 'submitted_at' => $submit]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $mulai,
        'ended_at' => $submit,
    ]);
    $this->travelTo($submit);

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 4])
        ->assertSessionDoesntHaveErrors();

    $task->refresh();
    expect($task->actual_minutes)->toBe(4) // dalam estimasi (guard: bukan ini penyebab telat).
        ->and($task->kpi_score)->toBe(3); // TAPI telat -- due_date dilanggar.
});

test('due_date terpenuhi TAPI actual>estimasi -- telat, kpi_score=kpi_points_late (D1)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 8, 0, 0);
    seedKpiFullDaySchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Estimasi 60 menit, realisasi 90 menit (>60); due_date masih jauh
    // (+1 minggu, terpenuhi) -- TETAP telat murni karena estimasi dilanggar.
    $task = createKpiReviewTask($project, $admin, 60, ['submitted_at' => $anchor->copy()->addMinutes(90)]);
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

test('due_date DAN estimasi dua-duanya dilanggar -- tetap telat, bukan dihitung dobel (D1)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 8, 0, 0);
    seedKpiFullDaySchedule($admin, $anchor);
    $this->travelTo($anchor);

    $task = createKpiReviewTask($project, $admin, 60, [
        'due_date' => $anchor->copy()->addMinutes(30),
        'submitted_at' => $anchor->copy()->addMinutes(90),
    ]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $anchor->copy(),
        'ended_at' => $anchor->copy()->addMinutes(90),
    ]);
    $this->travelTo($anchor->copy()->addMinutes(90));

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 2])
        ->assertSessionDoesntHaveErrors();

    expect($task->fresh()->kpi_score)->toBe(3);
});

test('batas <= (actual TEPAT SAMA estimasi, submit TEPAT SAMA due_date) -- dihitung on-time, bukan telat (D1 boundary)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 8, 0, 0);
    seedKpiFullDaySchedule($admin, $anchor);
    $this->travelTo($anchor);

    $batas = $anchor->copy()->addMinutes(60);
    $task = createKpiReviewTask($project, $admin, 60, ['due_date' => $batas, 'submitted_at' => $batas]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $anchor->copy(),
        'ended_at' => $batas,
    ]);
    $this->travelTo($batas);

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 4])
        ->assertSessionDoesntHaveErrors();

    expect($task->fresh()->kpi_score)->toBe(5);
});

test('perpanjangan disetujui TIDAK palsukan on-time -- pakai original_due_date, bukan due_date tergeser (D1/F-47 aktif lagi)', function () {
    $admin = User::factory()->admin()->create();
    $project = createKpiProject($admin);
    $anchor = Carbon::create(2026, 8, 10, 8, 0, 0);
    seedKpiFullDaySchedule($admin, $anchor);
    $this->travelTo($anchor);

    // due_date SEKARANG sudah digeser maju (extension disetujui) ke +10 hari,
    // tapi original_due_date (tenggat ASLI) cuma +1 jam dari anchor -- submit
    // di +5 hari kelihatan "on-time" kalau salah pakai due_date, tapi
    // SESUDAH original_due_date (yang benar: telat). actual tetap <=estimasi
    // (murni guard due_date, bukan estimasi, yang menyebabkan telat).
    $task = createKpiReviewTask($project, $admin, 60, [
        'due_date' => $anchor->copy()->addDays(10),
        'original_due_date' => $anchor->copy()->addHour(),
        'submitted_at' => $anchor->copy()->addDays(5),
    ]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $anchor->copy(),
        'ended_at' => $anchor->copy()->addMinutes(30),
    ]);
    $this->travelTo($anchor->copy()->addDays(5));

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 3])
        ->assertSessionDoesntHaveErrors();

    $task->refresh();
    expect($task->actual_minutes)->toBe(30) // dalam estimasi -- guard: BUKAN ini penyebab telat.
        ->and($task->kpi_score)->toBe(3); // telat -- original_due_date dilanggar.
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
