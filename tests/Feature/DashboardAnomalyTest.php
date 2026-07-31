<?php

/**
 * ==========================================================
 * MODUL       : DashboardAnomalyTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi F-53 — realisasi > 3x estimasi ditandai untuk review
 *               admin lewat DashboardService::anomalies(), TANPA mengubah
 *               status/skor task apa pun (rem Goodhart, F-4). Anomali dibaca
 *               LANGSUNG dari actual_minutes/estimated_minutes yang sudah
 *               dibekukan F-39 saat approve — bukan pengecekan baru.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : DashboardController, DashboardService, TaskTransitionService (approve)
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau anomali otomatis mengubah data task, itu pelanggaran F-53
 *               eksplisit (jadi penalti otomatis) — test ini pagar terhadap itu.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Testing\TestResponse;

function createAnomalyProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Anomaly Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

/**
 * KONTRAK: jendela kerja 24 jam penuh, semua hari -- test ini fokus ke rasio
 * actual vs estimasi, BUKAN cap jendela kerja (F-57 sudah diuji terpisah di
 * BusinessHoursCalculatorTest/LiveTaskCounterTest).
 */
function seedAnomalyFullDaySchedule(User $admin, Carbon $anchor): void
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

function createAnomalyReviewTask(Project $project, TaskStatus $review, User $admin, User $assignee, int $estimatedMinutes): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $review->id,
        'title' => 'Anomaly task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => $estimatedMinutes,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync([$assignee->id]);

    return $task;
}

function anomalyUserRow(TestResponse $response, int $userId): array
{
    return collect($response->json('users'))->firstWhere('id', $userId);
}

test('realisasi > 3x estimasi ditandai anomali setelah approve (F-53)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createAnomalyProject($admin, [$member->id]);
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();

    $anchor = Carbon::create(2026, 7, 20, 8, 0, 0);
    seedAnomalyFullDaySchedule($admin, $anchor);

    // Estimasi 60 menit, segmen kerja 250 menit tertutup (250 > 3x60 = 180).
    $task = createAnomalyReviewTask($project, $review, $admin, $member, 60);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $member->id,
        'started_at' => $anchor->copy(),
        'ended_at' => $anchor->copy()->addMinutes(250),
    ]);

    $this->travelTo($anchor->copy()->addMinutes(250));

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), [
        'quality_rating' => 3,
    ])->assertRedirect(route('tasks.index', $project));

    $task->refresh();
    expect($task->actual_minutes)->toBe(250);

    $response = $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $task->approved_at->toDateString()]));

    $response->assertOk();
    $anomalies = anomalyUserRow($response, $member->id)['anomalies'];
    expect($anomalies)->toHaveCount(1)
        ->and($anomalies[0]['task_id'])->toBe($task->id)
        ->and($anomalies[0]['estimated_minutes'])->toBe(60)
        ->and($anomalies[0]['actual_minutes'])->toBe(250);
});

test('realisasi di bawah 3x estimasi TIDAK ditandai anomali', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createAnomalyProject($admin, [$member->id]);
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();

    $anchor = Carbon::create(2026, 7, 20, 8, 0, 0);
    seedAnomalyFullDaySchedule($admin, $anchor);

    // Estimasi 60 menit, segmen 170 menit (170 < 3x60 = 180) -- di bawah ambang.
    $task = createAnomalyReviewTask($project, $review, $admin, $member, 60);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $member->id,
        'started_at' => $anchor->copy(),
        'ended_at' => $anchor->copy()->addMinutes(170),
    ]);

    $this->travelTo($anchor->copy()->addMinutes(170));

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), [
        'quality_rating' => 4,
    ])->assertRedirect(route('tasks.index', $project));

    $task->refresh();

    $response = $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $task->approved_at->toDateString()]));

    expect(anomalyUserRow($response, $member->id)['anomalies'])->toBe([]);
});

test('anomali HANYA menandai, tidak mengubah status/skor task apa pun (F-53)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createAnomalyProject($admin, [$member->id]);
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    $anchor = Carbon::create(2026, 7, 20, 8, 0, 0);
    seedAnomalyFullDaySchedule($admin, $anchor);

    $task = createAnomalyReviewTask($project, $review, $admin, $member, 60);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $member->id,
        'started_at' => $anchor->copy(),
        'ended_at' => $anchor->copy()->addMinutes(250),
    ]);

    $this->travelTo($anchor->copy()->addMinutes(250));

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), [
        'quality_rating' => 3,
    ]);

    $task->refresh();
    $statusBefore = $task->task_status_id;
    $qualityBefore = $task->quality_rating;
    $actualBefore = $task->actual_minutes;

    // Panggil dashboard (yang membaca & menandai anomali) DUA KALI -- murni GET,
    // read-only. Kalau anomali diam-diam menghukum/menulis, nilai ini akan berubah.
    $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $task->approved_at->toDateString()]));
    $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $task->approved_at->toDateString()]));

    $task->refresh();
    expect($task->task_status_id)->toBe($statusBefore)
        ->and($task->task_status_id)->toBe($done->id)
        ->and($task->quality_rating)->toBe($qualityBefore)
        ->and($task->actual_minutes)->toBe($actualBefore);
});
