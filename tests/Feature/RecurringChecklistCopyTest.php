<?php

/**
 * ==========================================================
 * MODUL       : RecurringChecklistCopyTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi copy-on-generate checklist (F-123/F-127) —
 *               GenerateRecurringTasksCommand menyalin TaskTemplateChecklistItem
 *               blueprint ke TaskChecklistItem instance BARU dengan is_done=false
 *               (fresh tiap instance), TANPA merusak idempotency (F-61) atau
 *               no-backfill (F-100) yang sudah diuji terpisah di RecurringDailyTest dkk.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : GenerateRecurringTasksCommand
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau copy gagal diam-diam (mis. lupa eager load -> lazy load
 *               dilarang di non-produksi, F-85), instance recurring lahir tanpa
 *               checklist walau template-nya sudah diisi — silent gap (dicatat
 *               di header TaskTemplateChecklistItem model).
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;

function createChecklistCopyProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Recurring Checklist Copy Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedChecklistCopySchedule(User $admin, Carbon $effectiveFrom): WorkSchedule
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

test('template dengan checklist -> instance baru punya checklist item is_done=false (F-123)', function () {
    $admin = User::factory()->admin()->create();
    $project = createChecklistCopyProject($admin);
    seedChecklistCopySchedule($admin, Carbon::create(2026, 1, 1));

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Template dengan checklist',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
    ]);

    $template->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'Cek A', 'position' => 0]);
    $template->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'Cek B', 'position' => 1]);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 0, 0)); // Senin, hari kerja
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $task = Task::where('task_template_id', $template->id)->firstOrFail();
    $items = $task->checklistItems()->orderBy('position')->get();

    expect($items)->toHaveCount(2)
        ->and($items->pluck('text')->all())->toBe(['Cek A', 'Cek B'])
        ->and($items->every(fn ($i) => $i->is_done === false))->toBeTrue();
});

test('template TANPA checklist -> instance lahir tanpa checklist item, tidak error', function () {
    $admin = User::factory()->admin()->create();
    $project = createChecklistCopyProject($admin);
    seedChecklistCopySchedule($admin, Carbon::create(2026, 1, 1));

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Template tanpa checklist',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
    ]);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $task = Task::where('task_template_id', $template->id)->firstOrFail();
    expect($task->checklistItems)->toHaveCount(0);
});

test('scheduler jalan 2x hari sama TIDAK menggandakan checklist item (F-61 utuh)', function () {
    $admin = User::factory()->admin()->create();
    $project = createChecklistCopyProject($admin);
    seedChecklistCopySchedule($admin, Carbon::create(2026, 1, 1));

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Template idempotency checklist',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
    ]);
    $template->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'Cek idempotency', 'position' => 0]);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $task = Task::where('task_template_id', $template->id)->sole();
    expect($task->checklistItems()->count())->toBe(1);
});
