<?php

/**
 * ==========================================================
 * MODUL       : NotificationSchedulerTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi 2 trigger notifikasi berbasis scheduler (F-35 #4/#5) —
 *               due besok & overdue — termasuk guard idempotency F-80 (cron jalan
 *               2x tidak boleh kirim notif ganda).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : NotifyDueSoonCommand, NotifyOverdueCommand, TaskNotification
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test idempotency adalah pagar F-80 — tanpa guard ini, cron yang
 *               retry/dijalankan manual dua kali membanjiri inbox tim (F-36 percuma).
 *               travelTo() dipakai (bukan now()->addDay() relatif) — pelajaran Hari-2:
 *               tanggal relatif terhadap waktu jalan test = flaky.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notifications\TaskNotification;
use Carbon\Carbon;

function createSchedulerProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Scheduler Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createSchedulerTask(Project $project, User $admin, TaskStatus $status, Carbon $dueDate): Task
{
    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Scheduler task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 5,
        'due_date' => $dueDate,
        'created_by' => $admin->id,
    ]);
}

test('a task due tomorrow notifies its assignee (F-35 #4)', function () {
    $anchor = Carbon::create(2026, 8, 3, 8, 0, 0);
    $this->travelTo($anchor);

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSchedulerProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createSchedulerTask($project, $admin, $todo, $anchor->copy()->addDay()->setTime(17, 0));
    $task->assignees()->sync([$member->id]);

    $this->artisan('tasks:notify-due-soon')->assertSuccessful();

    expect($member->notifications()->where('data->type', TaskNotification::DUE_SOON)->count())->toBe(1);
});

test('a completed task due tomorrow does not get a due-soon notification', function () {
    $anchor = Carbon::create(2026, 8, 3, 8, 0, 0);
    $this->travelTo($anchor);

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSchedulerProject($admin, [$member->id]);
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();
    $task = createSchedulerTask($project, $admin, $done, $anchor->copy()->addDay()->setTime(17, 0));
    $task->assignees()->sync([$member->id]);

    $this->artisan('tasks:notify-due-soon')->assertSuccessful();

    expect($member->notifications()->where('data->type', TaskNotification::DUE_SOON)->count())->toBe(0);
});

test('running the due-soon command twice does not duplicate notifications (F-80)', function () {
    $anchor = Carbon::create(2026, 8, 3, 8, 0, 0);
    $this->travelTo($anchor);

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSchedulerProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createSchedulerTask($project, $admin, $todo, $anchor->copy()->addDay()->setTime(17, 0));
    $task->assignees()->sync([$member->id]);

    $this->artisan('tasks:notify-due-soon')->assertSuccessful();
    $this->artisan('tasks:notify-due-soon')->assertSuccessful();

    expect($member->notifications()->where('data->type', TaskNotification::DUE_SOON)->count())->toBe(1);
});

test('an overdue task notifies both its assignee and the admin (F-35 #5)', function () {
    $anchor = Carbon::create(2026, 8, 3, 8, 0, 0);
    $this->travelTo($anchor);

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSchedulerProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createSchedulerTask($project, $admin, $todo, $anchor->copy()->subDay());
    $task->assignees()->sync([$member->id]);

    $this->artisan('tasks:notify-overdue')->assertSuccessful();

    expect($member->notifications()->where('data->type', TaskNotification::OVERDUE)->count())->toBe(1)
        ->and($admin->notifications()->where('data->type', TaskNotification::OVERDUE)->count())->toBe(1);
});

test('a completed task past its deadline does not get an overdue notification', function () {
    $anchor = Carbon::create(2026, 8, 3, 8, 0, 0);
    $this->travelTo($anchor);

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSchedulerProject($admin, [$member->id]);
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();
    $task = createSchedulerTask($project, $admin, $done, $anchor->copy()->subDay());
    $task->assignees()->sync([$member->id]);

    $this->artisan('tasks:notify-overdue')->assertSuccessful();

    expect($member->notifications()->where('data->type', TaskNotification::OVERDUE)->count())->toBe(0);
});

test('running the overdue command twice does not duplicate notifications (F-80)', function () {
    $anchor = Carbon::create(2026, 8, 3, 8, 0, 0);
    $this->travelTo($anchor);

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSchedulerProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createSchedulerTask($project, $admin, $todo, $anchor->copy()->subDay());
    $task->assignees()->sync([$member->id]);

    $this->artisan('tasks:notify-overdue')->assertSuccessful();
    $this->artisan('tasks:notify-overdue')->assertSuccessful();

    expect($member->notifications()->where('data->type', TaskNotification::OVERDUE)->count())->toBe(1);
});
