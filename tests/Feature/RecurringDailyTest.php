<?php

/**
 * ==========================================================
 * MODUL       : RecurringDailyTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi engine recurring untuk task_type=daily (F-46, v0.8 H4) —
 *               generate hari kerja, SKIP TOTAL di libur/weekend (F-102, TIDAK
 *               digeser), idempotency (F-61), dan TIDAK BACKFILL (F-100).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : GenerateRecurringTasksCommand
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "scheduler terlewat" adalah pagar F-100 — kalau generator
 *               diam-diam merekonstruksi hari yang terlewat, data KPI tercemar
 *               obligasi retroaktif yang tidak pernah benar-benar terjadi.
 *               travelTo() dipakai untuk SEMUA tanggal (pelajaran H2 v0.5 — tanggal
 *               relatif terhadap waktu jalan test = flaky).
 * ==========================================================
 */

use App\Models\ActivityLog;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;

function createRecurringProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Recurring Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedRecurringSchedule(User $admin, Carbon $effectiveFrom, array $daysOfWeek = [1, 2, 3, 4, 5]): WorkSchedule
{
    return WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $effectiveFrom->toDateString(),
        'days_of_week' => $daysOfWeek,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

function createDailyTemplate(Project $project, array $assigneeIds = []): TaskTemplate
{
    return TaskTemplate::create([
        'organization_id' => $project->organization_id,
        'project_id' => $project->id,
        'title' => 'Daily template '.uniqid(),
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => $assigneeIds,
        'is_active' => true,
    ]);
}

test('hari kerja normal menghasilkan 1 instance daily', function () {
    $admin = User::factory()->admin()->create();
    $project = createRecurringProject($admin);
    seedRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createDailyTemplate($project);

    $monday = Carbon::create(2026, 8, 3, 0, 0, 0); // Senin
    $this->travelTo($monday);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $tasks = Task::where('task_template_id', $template->id)->get();
    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->due_date->toDateString())->toBe('2026-08-03');
    expect($tasks->first()->due_date->format('H:i'))->toBe('17:00');

    $template->refresh();
    expect($template->last_generated_date->toDateString())->toBe('2026-08-03');
});

test('scheduler jalan 2x hari yang sama tidak menggandakan (F-61)', function () {
    $admin = User::factory()->admin()->create();
    $project = createRecurringProject($admin);
    seedRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createDailyTemplate($project);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 0, 0));

    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_template_id', $template->id)->count())->toBe(1);
});

test('hari libur -> 0 instance, TIDAK digeser (F-102)', function () {
    $admin = User::factory()->admin()->create();
    $project = createRecurringProject($admin);
    seedRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createDailyTemplate($project);

    // Selasa 4 Agustus 2026 -- hari kerja biasa, dijadikan libur.
    Holiday::create(['organization_id' => $admin->organization_id, 'date' => '2026-08-04', 'name' => 'Libur Uji']);

    $this->travelTo(Carbon::create(2026, 8, 4, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_template_id', $template->id)->count())->toBe(0);
    expect($template->fresh()->last_generated_date)->toBeNull();
});

test('akhir pekan -> 0 instance', function () {
    $admin = User::factory()->admin()->create();
    $project = createRecurringProject($admin);
    seedRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createDailyTemplate($project);

    $this->travelTo(Carbon::create(2026, 8, 8, 0, 0, 0)); // Sabtu
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_template_id', $template->id)->count())->toBe(0);
});

test('scheduler terlewat 3 hari -> saat jalan HANYA hari ini, bukan backfill (F-100)', function () {
    $admin = User::factory()->admin()->create();
    $project = createRecurringProject($admin);
    seedRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createDailyTemplate($project);

    // Template dibuat Senin 3 Agustus, scheduler BARU jalan Kamis 6 Agustus --
    // 3 hari kerja (Sen/Sel/Rab) "terlewat" tanpa satu kali pun cron jalan.
    $this->travelTo(Carbon::create(2026, 8, 6, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $tasks = Task::where('task_template_id', $template->id)->get();
    expect($tasks)->toHaveCount(1); // BUKAN 3
    expect($tasks->first()->due_date->toDateString())->toBe('2026-08-06');
});

test('is_active=false tidak generate', function () {
    $admin = User::factory()->admin()->create();
    $project = createRecurringProject($admin);
    seedRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createDailyTemplate($project);
    $template->update(['is_active' => false]);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_template_id', $template->id)->count())->toBe(0);
});

test('generate tercatat lewat activity_log observer, bukan panggilan manual (F-22/F-51)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createRecurringProject($admin, [$member->id]);
    seedRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createDailyTemplate($project, [$member->id]);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $task = Task::where('task_template_id', $template->id)->firstOrFail();

    expect(ActivityLog::where('subject_type', Task::class)->where('subject_id', $task->id)->where('event', 'created')->exists())->toBeTrue();
    expect(ActivityLog::where('subject_type', Task::class)->where('subject_id', $task->id)->where('event', 'assigned')->exists())->toBeTrue();
    expect($member->notifications()->count())->toBe(1);
});
