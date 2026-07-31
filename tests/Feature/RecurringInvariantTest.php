<?php

/**
 * ==========================================================
 * MODUL       : RecurringInvariantTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi invarian KRITIS engine recurring yang biasanya dijaga
 *               StoreTaskRequest tapi di-bypass oleh generator (F-86) — plus F-51
 *               (log tidak boleh bolong) dan F-60 (instance lama tidak dihapus).
 *               Recurring adalah sumber terbesar "state mustahil dibuat lewat UI"
 *               (prompt v0.8 H4) — test ini pagar terhadap itu.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : GenerateRecurringTasksCommand
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau assignee non-member ikut ter-attach, itu SAMA PERSIS dengan
 *               F-86 (pelanggaran yang sudah pernah kejadian di seeder Hari-7) --
 *               kali ini lewat jalur generator, bukan seeder.
 * ==========================================================
 */

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;

function createInvariantRecurringProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Invariant Recurring Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedInvariantRecurringSchedule(User $admin, Carbon $effectiveFrom): WorkSchedule
{
    return WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $effectiveFrom->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

function createInvariantDailyTemplate(Project $project, array $assigneeIds): TaskTemplate
{
    return TaskTemplate::create([
        'organization_id' => $project->organization_id,
        'project_id' => $project->id,
        'title' => 'Invariant daily template '.uniqid(),
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => $assigneeIds,
        'is_active' => true,
    ]);
}

test('default_assignee yang sudah bukan member di-drop, task tetap lahir (F-86)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createInvariantRecurringProject($admin, [$member->id]);
    seedInvariantRecurringSchedule($admin, Carbon::create(2026, 1, 1));

    $template = createInvariantDailyTemplate($project, [$member->id]);

    // Member dikeluarkan dari project SETELAH template dibuat (F-86 A3: member
    // bisa berubah lagi setelah template dibuat).
    $project->members()->detach($member->id);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $task = Task::where('task_template_id', $template->id)->firstOrFail();

    expect($task->assignees()->count())->toBe(0);
    expect($task->assignees()->whereKey($member->id)->exists())->toBeFalse();

    // Log tidak boleh bolong (F-51) walau assignee-nya di-drop, bukan di-unassign
    // (tidak pernah di-attach sama sekali).
    $dropLog = ActivityLog::where('subject_type', Task::class)
        ->where('subject_id', $task->id)
        ->where('event', 'recurring_assignee_dropped')
        ->first();

    expect($dropLog)->not->toBeNull();
    expect($dropLog->properties['new']['dropped_user_ids'])->toBe([$member->id]);
});

test('semua default_assignee bukan member -> task lahir UNASSIGNED, tidak crash', function () {
    $admin = User::factory()->admin()->create();
    $outsider = User::factory()->create(); // organisasi BEDA, bukan member sama sekali
    $project = createInvariantRecurringProject($admin);
    seedInvariantRecurringSchedule($admin, Carbon::create(2026, 1, 1));

    $template = createInvariantDailyTemplate($project, [$outsider->id]);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 0, 0));

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $task = Task::where('task_template_id', $template->id)->firstOrFail();
    expect($task->assignees()->count())->toBe(0);
});

test('assign lewat relasi Eloquent -> activity_log assigned tercatat (F-51)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createInvariantRecurringProject($admin, [$member->id]);
    seedInvariantRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createInvariantDailyTemplate($project, [$member->id]);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $task = Task::where('task_template_id', $template->id)->firstOrFail();

    expect($task->assignees()->whereKey($member->id)->exists())->toBeTrue();
    expect(ActivityLog::where('subject_type', Task::class)
        ->where('subject_id', $task->id)
        ->where('event', 'assigned')
        ->exists())->toBeTrue();
});

test('instance lama yang belum selesai TIDAK dihapus saat instance baru lahir (F-60)', function () {
    $admin = User::factory()->admin()->create();
    $project = createInvariantRecurringProject($admin);
    seedInvariantRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createInvariantDailyTemplate($project, []);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 0, 0)); // Senin
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $this->travelTo(Carbon::create(2026, 8, 4, 0, 0, 0)); // Selasa, instance kemarin BELUM done
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $tasks = Task::where('task_template_id', $template->id)->with('taskStatus')->orderBy('due_date')->get();
    expect($tasks)->toHaveCount(2);
    expect($tasks->pluck('due_date')->map(fn ($d) => $d->toDateString())->all())
        ->toBe(['2026-08-03', '2026-08-04']);

    // Instance kemarin masih ada di status TODO (BUKAN is_completed) -> overdue.
    $yesterday = $tasks->first();
    expect($yesterday->taskStatus->is_completed)->toBeFalse();
    expect($yesterday->deleted_at)->toBeNull();
});
