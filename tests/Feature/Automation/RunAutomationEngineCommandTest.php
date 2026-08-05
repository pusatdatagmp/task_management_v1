<?php

/**
 * ==========================================================
 * MODUL       : RunAutomationEngineCommandTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : G4-G6 (prompt AE-2) — uji END-TO-END command `automation:run`:
 *               idempotency (F-61), isolasi per-template (F-160), Forward-Shift
 *               ter-log benar (F-153), automation_run_log terisi org_id (F-5).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : RunAutomationEngineCommand (via $this->artisan())
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test ini pagar F-61 (dobel run tak boleh dobel task) DAN F-160
 *               (1 template rusak tak boleh gagalkan template lain) sekaligus --
 *               dua properti paling kritis Automation Engine v1.3.
 * ==========================================================
 */

use App\Models\AutomationRunLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;

function createCommandTestProject(User $admin, bool $withStatuses = true): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Command Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    if ($withStatuses) {
        TaskStatus::seedDefaults($project);
    }

    return $project;
}

function seedCommandSchedule(User $admin, Carbon $effectiveFrom): WorkSchedule
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

function createCommandTestTemplate(Project $project, array $overrides = []): TaskTemplate
{
    return TaskTemplate::create(array_merge([
        'organization_id' => $project->organization_id,
        'project_id' => $project->id,
        'title' => 'Command Test Template '.uniqid(),
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
        'anchor_strategy' => 'time_based',
        'interval_value' => 1,
        'interval_unit' => 'day',
    ], $overrides));
}

test('F-61: run 2x hari yang sama tidak menggandakan task', function () {
    $admin = User::factory()->admin()->create();
    $project = createCommandTestProject($admin);
    seedCommandSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createCommandTestTemplate($project);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 1, 0)); // Senin

    $this->artisan('automation:run')->assertSuccessful();
    $this->artisan('automation:run')->assertSuccessful();

    expect(Task::where('task_template_id', $template->id)->count())->toBe(1);
    expect($template->fresh()->last_generated_date->toDateString())->toBe('2026-08-03');

    $actions = AutomationRunLog::where('task_template_id', $template->id)->orderBy('id')->pluck('action')->all();
    expect($actions)->toBe(['generate', 'skip']);
});

test('F-153: target di weekend digeser -> action shift, last_generated_date = TANGGAL EVALUASI bukan target', function () {
    $admin = User::factory()->admin()->create();
    $project = createCommandTestProject($admin);
    seedCommandSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createCommandTestTemplate($project);

    $this->travelTo(Carbon::create(2026, 8, 8, 0, 1, 0)); // Sabtu

    $this->artisan('automation:run')->assertSuccessful();

    $task = Task::where('task_template_id', $template->id)->firstOrFail();
    expect($task->period_key)->toBe('2026-08-10'); // Senin
    expect($task->due_date->toDateString())->toBe('2026-08-10');

    // F-152: last_generated_date = now_WIB (Sabtu), BUKAN target_date (Senin).
    expect($template->fresh()->last_generated_date->toDateString())->toBe('2026-08-08');

    $log = AutomationRunLog::where('task_template_id', $template->id)->firstOrFail();
    expect($log->action)->toBe('shift');
    expect($log->target_date->toDateString())->toBe('2026-08-10');
});

test('F-160: 1 template gagal (project tanpa status) TIDAK menghentikan template lain', function () {
    $admin = User::factory()->admin()->create();
    seedCommandSchedule($admin, Carbon::create(2026, 1, 1));

    $goodProject = createCommandTestProject($admin, withStatuses: true);
    $goodTemplate = createCommandTestTemplate($goodProject);

    // Project SENGAJA tanpa TaskStatus::seedDefaults() -- statusId akan NULL,
    // Task::create() menabrak constraint NOT NULL task_status_id -> QueryException.
    $brokenProject = createCommandTestProject($admin, withStatuses: false);
    $brokenTemplate = createCommandTestTemplate($brokenProject);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 1, 0)); // Senin

    $this->artisan('automation:run')->assertSuccessful();

    expect(Task::where('task_template_id', $goodTemplate->id)->count())->toBe(1);
    expect(Task::where('task_template_id', $brokenTemplate->id)->count())->toBe(0);

    $goodLog = AutomationRunLog::where('task_template_id', $goodTemplate->id)->firstOrFail();
    expect($goodLog->action)->toBe('generate');

    $brokenLog = AutomationRunLog::where('task_template_id', $brokenTemplate->id)->firstOrFail();
    expect($brokenLog->action)->toBe('error');
});

test('F-5: automation_run_log terisi organization_id benar', function () {
    $admin = User::factory()->admin()->create();
    $project = createCommandTestProject($admin);
    seedCommandSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createCommandTestTemplate($project);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 1, 0));
    $this->artisan('automation:run')->assertSuccessful();

    $log = AutomationRunLog::where('task_template_id', $template->id)->firstOrFail();
    expect($log->organization_id)->toBe($admin->organization_id);
});

test('is_active=false: query command TIDAK memuat template ini sama sekali (pre-filter F1)', function () {
    // ActiveTemplateGuard sendiri sudah diuji lepas di AutomationGuardsTest (G1) --
    // di sini pagar bahwa command TIDAK menghasilkan baris run_log untuk template
    // yang bahkan tidak pernah masuk query is_active=true (bukan Skip via Guard,
    // tapi memang tidak pernah dievaluasi -- pola SPEK v1.3 §3/F1 "chunkById template Active").
    $admin = User::factory()->admin()->create();
    $project = createCommandTestProject($admin);
    seedCommandSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createCommandTestTemplate($project, ['is_active' => false]);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 1, 0));
    $this->artisan('automation:run')->assertSuccessful();

    expect(Task::where('task_template_id', $template->id)->count())->toBe(0);
    expect(AutomationRunLog::where('task_template_id', $template->id)->exists())->toBeFalse();
});
