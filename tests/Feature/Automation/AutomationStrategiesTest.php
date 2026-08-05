<?php

/**
 * ==========================================================
 * MODUL       : AutomationStrategiesTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : G2 (prompt AE-2) — tiap AnchorStrategy (F-158/161 §8.3) diuji
 *               SENDIRI: TimeBased selalu Pass, CompletionBased Skip/Pass sesuai
 *               status task sebelumnya, CalendarAnchored Pass HANYA di hari tetap.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TimeBasedStrategy, CompletionBasedStrategy, CalendarAnchoredStrategy
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : CompletionBasedStrategy salah baca is_completed = backlog Opsi B
 *               tidak pernah ter-block (atau sebaliknya macet permanen, F-154).
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Strategies\CalendarAnchoredStrategy;
use App\Services\Automation\Strategies\CompletionBasedStrategy;
use App\Services\Automation\Strategies\TimeBasedStrategy;
use Carbon\Carbon;

function createStrategyTestProject(): Project
{
    $admin = User::factory()->admin()->create();
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Strategy Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);

    return $project;
}

function createStrategyTestTemplate(Project $project, array $overrides = []): TaskTemplate
{
    return TaskTemplate::create(array_merge([
        'organization_id' => $project->organization_id,
        'project_id' => $project->id,
        'title' => 'Strategy Test Template '.uniqid(),
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

function createStrategyTestTask(Project $project, TaskTemplate $template, string $periodKey, bool $isCompleted): Task
{
    $statusId = TaskStatus::where('project_id', $project->id)->where('is_completed', $isCompleted)->value('id');

    return Task::create([
        'organization_id' => $project->organization_id,
        'project_id' => $project->id,
        'task_template_id' => $template->id,
        'period_key' => $periodKey,
        'task_status_id' => $statusId,
        'title' => 'Instance '.$periodKey,
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'due_date' => Carbon::parse($periodKey)->endOfDay(),
        'created_by' => $project->owner_id,
    ]);
}

function strategyCtx(Carbon $nowWib, array $latestTaskByTemplateId = []): AutomationContext
{
    return new AutomationContext($nowWib, collect(), collect(), $latestTaskByTemplateId, []);
}

// ---------------------------------------------------------------------------
// TimeBasedStrategy (Opsi A)
// ---------------------------------------------------------------------------

test('TimeBasedStrategy: selalu Pass', function () {
    $project = createStrategyTestProject();
    $template = createStrategyTestTemplate($project);

    $decision = (new TimeBasedStrategy)->evaluate($template, strategyCtx(Carbon::create(2026, 8, 3)));

    expect($decision)->toBeNull();
});

// ---------------------------------------------------------------------------
// CompletionBasedStrategy (Opsi B)
// ---------------------------------------------------------------------------

test('CompletionBasedStrategy: Pass kalau belum pernah ada instance sebelumnya', function () {
    $project = createStrategyTestProject();
    $template = createStrategyTestTemplate($project, ['anchor_strategy' => 'completion_based']);

    $decision = (new CompletionBasedStrategy)->evaluate($template, strategyCtx(Carbon::create(2026, 8, 3)));

    expect($decision)->toBeNull();
});

test('CompletionBasedStrategy: Skip sebelumnya-belum-selesai + set blocked_since', function () {
    $project = createStrategyTestProject();
    $template = createStrategyTestTemplate($project, ['anchor_strategy' => 'completion_based']);
    $previousTask = createStrategyTestTask($project, $template, '2026-08-02', isCompleted: false);

    $decision = (new CompletionBasedStrategy)->evaluate(
        $template,
        strategyCtx(Carbon::create(2026, 8, 3), [$template->id => $previousTask->load('taskStatus')])
    );

    expect($decision)->not->toBeNull();
    expect($decision->reason)->toBe('sebelumnya-belum-selesai');
    expect($template->fresh()->blocked_since?->toDateString())->toBe('2026-08-03');
});

test('CompletionBasedStrategy: Pass + clear blocked_since kalau sebelumnya SELESAI', function () {
    $project = createStrategyTestProject();
    $template = createStrategyTestTemplate($project, [
        'anchor_strategy' => 'completion_based',
        'blocked_since' => '2026-08-01',
    ]);
    $previousTask = createStrategyTestTask($project, $template, '2026-08-02', isCompleted: true);

    $decision = (new CompletionBasedStrategy)->evaluate(
        $template,
        strategyCtx(Carbon::create(2026, 8, 3), [$template->id => $previousTask->load('taskStatus')])
    );

    expect($decision)->toBeNull();
    expect($template->fresh()->blocked_since)->toBeNull();
});

// ---------------------------------------------------------------------------
// CalendarAnchoredStrategy (Opsi C)
// ---------------------------------------------------------------------------

test('CalendarAnchoredStrategy: Pass HANYA di day_of_month yang cocok', function () {
    $project = createStrategyTestProject();
    $template = createStrategyTestTemplate($project, [
        'anchor_strategy' => 'calendar_anchored',
        'anchor_config' => ['day_of_month' => 1],
    ]);

    $passDecision = (new CalendarAnchoredStrategy)->evaluate($template, strategyCtx(Carbon::create(2026, 8, 1)));
    $skipDecision = (new CalendarAnchoredStrategy)->evaluate($template, strategyCtx(Carbon::create(2026, 8, 2)));

    expect($passDecision)->toBeNull();
    expect($skipDecision)->not->toBeNull();
    expect($skipDecision->reason)->toBe('bukan-hari-tetap');
});

test('CalendarAnchoredStrategy: Pass HANYA di day_of_week yang cocok', function () {
    $project = createStrategyTestProject();
    $template = createStrategyTestTemplate($project, [
        'anchor_strategy' => 'calendar_anchored',
        'anchor_config' => ['day_of_week' => 1], // Senin
    ]);

    $passDecision = (new CalendarAnchoredStrategy)->evaluate($template, strategyCtx(Carbon::create(2026, 8, 3))); // Senin
    $skipDecision = (new CalendarAnchoredStrategy)->evaluate($template, strategyCtx(Carbon::create(2026, 8, 4))); // Selasa

    expect($passDecision)->toBeNull();
    expect($skipDecision)->not->toBeNull();
});
