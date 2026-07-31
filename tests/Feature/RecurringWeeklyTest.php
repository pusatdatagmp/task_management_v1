<?php

/**
 * ==========================================================
 * MODUL       : RecurringWeeklyTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi engine recurring untuk task_type=weekly (F-46, v0.8 H4) —
 *               generate di day_of_week, GESER MAJU ke hari kerja berikutnya kalau
 *               jatuh libur/weekend (F-102, beda dari daily), idempotency (F-61).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : GenerateRecurringTasksCommand
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "geser Sabtu -> Senin" MELINTASI batas minggu (Sabtu minggu
 *               ini -> Senin MINGGU BERIKUTNYA) — pagar utama algoritma dua-periode
 *               di GenerateRecurringTasksCommand::resolveEffectiveDate(). Kalau
 *               salah, occurrence yang digeser lintas-minggu tidak pernah ketemu.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;

function createWeeklyRecurringProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Weekly Recurring Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedWeeklyRecurringSchedule(User $admin, Carbon $effectiveFrom, array $daysOfWeek = [1, 2, 3, 4, 5]): WorkSchedule
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

function createWeeklyTemplate(Project $project, int $dayOfWeek, array $assigneeIds = []): TaskTemplate
{
    return TaskTemplate::create([
        'organization_id' => $project->organization_id,
        'project_id' => $project->id,
        'title' => 'Weekly template '.uniqid(),
        'task_type' => 'weekly',
        'estimated_minutes' => 60,
        'points' => 10,
        'priority' => 'normal',
        'recurrence_config' => ['day_of_week' => $dayOfWeek],
        'default_assignees' => $assigneeIds,
        'is_active' => true,
    ]);
}

test('day_of_week jatuh hari kerja -> generate hari itu', function () {
    $admin = User::factory()->admin()->create();
    $project = createWeeklyRecurringProject($admin);
    seedWeeklyRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createWeeklyTemplate($project, 1); // Senin

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 0, 0)); // Senin 3 Agustus
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $tasks = Task::where('task_template_id', $template->id)->get();
    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->due_date->toDateString())->toBe('2026-08-03');
});

test('day_of_week jatuh Sabtu -> GESER ke Senin minggu berikutnya (F-102)', function () {
    $admin = User::factory()->admin()->create();
    $project = createWeeklyRecurringProject($admin);
    seedWeeklyRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createWeeklyTemplate($project, 6); // Sabtu

    // Sabtu 8 Agustus (natural date) -- scheduler jalan tiap hari, HARUS 0 sampai
    // Senin 10 Agustus (minggu BERIKUTNYA) baru generate.
    $this->travelTo(Carbon::create(2026, 8, 8, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    expect(Task::where('task_template_id', $template->id)->count())->toBe(0);

    $this->travelTo(Carbon::create(2026, 8, 9, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    expect(Task::where('task_template_id', $template->id)->count())->toBe(0);

    $this->travelTo(Carbon::create(2026, 8, 10, 0, 0, 0)); // Senin, minggu berikutnya
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $tasks = Task::where('task_template_id', $template->id)->get();
    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->due_date->toDateString())->toBe('2026-08-10');

    $template->refresh();
    expect($template->last_generated_date->toDateString())->toBe('2026-08-10');
});

test('idempotency: scheduler jalan 2x di hari occurrence tergeser tidak menggandakan (F-61)', function () {
    $admin = User::factory()->admin()->create();
    $project = createWeeklyRecurringProject($admin);
    seedWeeklyRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createWeeklyTemplate($project, 6); // Sabtu -> geser Senin

    $this->travelTo(Carbon::create(2026, 8, 10, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_template_id', $template->id)->count())->toBe(1);
});
