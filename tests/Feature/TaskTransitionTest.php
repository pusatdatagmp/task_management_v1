<?php

/**
 * ==========================================================
 * MODUL       : TaskTransitionTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi F-45 (transisi berurutan), F-28 (approve/reject admin
 *               only), F-41 (segmen buka/tutup), F-39 (freeze actual_minutes) —
 *               termasuk akumulasi multi-segmen end-to-end (Hari-4 §F3) yang
 *               sebelumnya cuma diuji di level kalkulator (catatan audit Hari-2).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController, TaskTransitionService, TaskObserver, WorkSchedule
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test F3 adalah pagar F-39 — actual_minutes yang dibekukan salah di
 *               sini berarti dasar KPI tim salah selamanya, tidak bisa dihitung ulang.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;

function createTransitionProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Transition Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

function createTransitionTask(Project $project, TaskStatus $status, User $admin): Task
{
    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Transition task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
}

test('forward transition can only go to position+1, TODO to DONE is rejected (F-45)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTransitionProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();
    $task = createTransitionTask($project, $todo, $admin);

    $response = $this->actingAs($admin)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $done->id,
    ]);

    $response->assertSessionHasErrors('task_status_id');
    expect($task->fresh()->task_status_id)->toBe($todo->id);
});

test('TODO to IN_PROGRESS is allowed and opens a time segment (F-41)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTransitionProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createTransitionTask($project, $todo, $admin);
    // C3 (v1.0 H2): segmen HANYA dibuka atas nama assignee. Admin di sini WAJIB
    // jadi assignee task-nya sendiri supaya skenario "F-41: segmen terbuka" tetap
    // punya pekerja yang jelas — bukan lagi admin mengerjakan task orang lain.
    $task->assignees()->sync([$admin->id]);

    $response = $this->actingAs($admin)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($task->fresh()->task_status_id)->toBe($inProgress->id)
        ->and($task->timeSegments()->whereNull('ended_at')->count())->toBe(1);
});

test('IN_PROGRESS to REVIEW is allowed and closes the open time segment', function () {
    $admin = User::factory()->admin()->create();
    $project = createTransitionProject($admin);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createTransitionTask($project, $inProgress, $admin);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => now()->subMinutes(30),
    ]);

    $response = $this->actingAs($admin)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $review->id,
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($task->timeSegments()->whereNull('ended_at')->count())->toBe(0);
});

test('rejecting a task in review increments rejection_count and opens a new segment', function () {
    $admin = User::factory()->admin()->create();
    $project = createTransitionProject($admin);
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createTransitionTask($project, $review, $admin);
    // C3 (v1.0 H2): sama seperti test F-41 di atas — admin di sini WAJIB assignee
    // supaya segmen re-open ("mundur ke is_work_state" saat reject) punya pekerja jelas.
    $task->assignees()->sync([$admin->id]);

    $response = $this->actingAs($admin)->patch(route('tasks.reject', [$project, $task]), [
        'reason' => 'Belum sesuai spesifikasi.',
    ]);

    $response->assertRedirect(route('tasks.index', $project));
    $task->refresh();
    expect($task->rejection_count)->toBe(1)
        ->and($task->taskStatus->is_work_state)->toBeTrue()
        ->and($task->timeSegments()->whereNull('ended_at')->count())->toBe(1);
});

test('approving a task in review freezes actual_minutes and fills completed_at (F-39)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTransitionProject($admin);
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createTransitionTask($project, $review, $admin);

    $anchor = Carbon::create(2026, 7, 20, 8, 0, 0);
    WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $anchor->copy()->subDay()->toDateString(),
        'days_of_week' => range(1, 7),
        'start_time' => '00:00',
        'end_time' => '23:59',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);

    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'started_at' => $anchor->copy(),
        'ended_at' => $anchor->copy()->addMinutes(40),
    ]);

    $this->travelTo($anchor->copy()->addMinutes(40));

    $response = $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), [
        'quality_rating' => 4,
    ]);

    $response->assertRedirect(route('tasks.index', $project));
    $task->refresh();
    expect($task->task_status_id)->toBe(TaskStatus::where('project_id', $project->id)->where('is_completed', true)->value('id'))
        ->and($task->completed_at)->not->toBeNull()
        ->and($task->approved_at)->not->toBeNull()
        ->and($task->approved_by)->toBe($admin->id)
        ->and($task->quality_rating)->toBe(4)
        ->and($task->actual_minutes)->toBe(40);
});

test('member cannot change the status of a task assigned to someone else', function () {
    $admin = User::factory()->admin()->create();
    $project = createTransitionProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createTransitionTask($project, $todo, $admin);

    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($outsider)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ]);

    $response->assertForbidden();
});

/**
 * F3 — AKUMULASI MULTI-SEGMEN END-TO-END (catatan audit Hari-2: sebelumnya cuma
 * diuji di level kalkulator, belum pernah lewat alur HTTP penuh). Skenario:
 * kerja 30 menit -> review -> ditolak -> kerja 45 menit lagi -> review -> approve.
 * actual_minutes HARUS = jumlah SEMUA segmen (30 + 45 = 75), dihitung dengan cap
 * jendela kerja (F-57) — jendela di test ini dibuat 24 jam penuh supaya assert
 * murni menguji AKUMULASI, bukan cap jendela (itu sudah diuji WorkScheduleTest/
 * BusinessHoursCalculator terpisah).
 */
test('actual_minutes accumulates across multiple work/reject/rework segments end-to-end (F3)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTransitionProject($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project->members()->sync([$admin->id, $member->id]);

    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    $task = createTransitionTask($project, $todo, $admin);
    $task->assignees()->sync([$member->id]);

    $anchor = Carbon::create(2026, 7, 20, 8, 0, 0);

    // Jendela kerja 24 jam penuh, semua hari — test ini fokus ke akumulasi
    // Σ segmen, bukan cap jendela (F-57 sudah diuji terpisah).
    WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $anchor->copy()->subDay()->toDateString(),
        'days_of_week' => range(1, 7),
        'start_time' => '00:00',
        'end_time' => '23:59',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);

    // T0: member mulai kerja -> segmen 1 dibuka.
    $this->travelTo($anchor->copy());
    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    // T0+30m: member submit review -> segmen 1 ditutup (30 menit).
    $this->travelTo($anchor->copy()->addMinutes(30));
    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $review->id,
    ])->assertSessionDoesntHaveErrors();

    // Admin tolak -> rejection_count++, segmen 2 dibuka (mundur ke is_work_state).
    $this->actingAs($admin)->patch(route('tasks.reject', [$project, $task]), [
        'reason' => 'Revisi diperlukan.',
    ])->assertRedirect();

    // T0+30m+45m: member submit review lagi -> segmen 2 ditutup (45 menit).
    $this->travelTo($anchor->copy()->addMinutes(30)->addMinutes(45));
    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $review->id,
    ])->assertSessionDoesntHaveErrors();

    // Admin approve -> FREEZE actual_minutes (F-39).
    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), [
        'quality_rating' => 5,
    ])->assertRedirect(route('tasks.index', $project));

    $task->refresh();

    expect($task->rejection_count)->toBe(1)
        ->and($task->timeSegments()->count())->toBe(2)
        ->and($task->timeSegments()->whereNull('ended_at')->count())->toBe(0)
        ->and($task->task_status_id)->toBe($done->id)
        ->and($task->actual_minutes)->toBe(75);
});
