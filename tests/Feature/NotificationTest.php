<?php

/**
 * ==========================================================
 * MODUL       : NotificationTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi 6 trigger notifikasi event-driven (F-35 #1/#3/#6/#7/#8)
 *               yang berjalan lewat Observer, termasuk guard F-36 (pelaku tidak
 *               dapat notif atas aksinya sendiri).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController (store/update/status/approve/reject), TaskObserver,
 *               TaskUserObserver, TaskNotification
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test F-36 adalah pagar terhadap inbox banjir — kalau pelaku ikut
 *               dapat notif atas aksinya sendiri, fitur ini akan diabaikan tim (F-1).
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notifications\TaskNotification;

function createNotificationProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Notification Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createNotificationTask(Project $project, User $admin, TaskStatus $status): Task
{
    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Notification task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 5,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
}

function baseTaskPayload(Task $task, array $overrides = []): array
{
    return [
        'title' => $task->title,
        'task_type' => $task->task_type,
        'estimated_minutes' => $task->estimated_minutes,
        'points' => $task->points,
        'due_date' => $task->due_date->toDateTimeString(),
        ...$overrides,
    ];
}

test('assigning a task notifies the new assignee (F-35 #1)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createNotificationProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createNotificationTask($project, $admin, $todo);

    $this->actingAs($admin)->put(route('tasks.update', [$project, $task]), baseTaskPayload($task, [
        'assignees' => [$member->id],
    ]))->assertSessionDoesntHaveErrors();

    expect($member->notifications()->where('data->type', TaskNotification::ASSIGNED)->count())->toBe(1);
});

test('the actor does not get notified for assigning themselves (F-36)', function () {
    $admin = User::factory()->admin()->create();
    $project = createNotificationProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createNotificationTask($project, $admin, $todo);

    $this->actingAs($admin)->put(route('tasks.update', [$project, $task]), baseTaskPayload($task, [
        'assignees' => [$admin->id],
    ]))->assertSessionDoesntHaveErrors();

    expect($admin->notifications()->where('data->type', TaskNotification::ASSIGNED)->count())->toBe(0);
});

test('unassigning a task notifies the former assignee (F-35 #2)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createNotificationProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createNotificationTask($project, $admin, $todo);
    $task->assignees()->sync([$member->id]);
    $member->notifications()->delete();

    $this->actingAs($admin)->put(route('tasks.update', [$project, $task]), baseTaskPayload($task, [
        'assignees' => [],
    ]))->assertSessionDoesntHaveErrors();

    expect($member->notifications()->where('data->type', TaskNotification::UNASSIGNED)->count())->toBe(1);
});

test('a status change notifies other assignees but not the actor (F-35 #3, F-36)', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createNotificationProject($admin, [$memberA->id, $memberB->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createNotificationTask($project, $admin, $todo);
    $task->assignees()->sync([$memberA->id, $memberB->id]);

    $this->actingAs($memberA)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    expect($memberB->notifications()->where('data->type', TaskNotification::STATUS_CHANGED)->count())->toBe(1)
        ->and($memberA->notifications()->where('data->type', TaskNotification::STATUS_CHANGED)->count())->toBe(0);
});

test('a task entering review notifies the admin (F-35 #6)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createNotificationProject($admin, [$member->id]);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createNotificationTask($project, $admin, $inProgress);
    $task->assignees()->sync([$member->id]);

    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $review->id,
    ])->assertSessionDoesntHaveErrors();

    expect($admin->notifications()->where('data->type', TaskNotification::ENTERED_REVIEW)->count())->toBe(1);
});

test('a task entering review does not also send a generic status-changed notification to co-assignees (F-84)', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createNotificationProject($admin, [$memberA->id, $memberB->id]);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createNotificationTask($project, $admin, $inProgress);
    $task->assignees()->sync([$memberA->id, $memberB->id]);
    $memberB->notifications()->delete(); // buang notif ASSIGNED dari setup sync() di atas

    // memberA submit ke review — memberB (co-assignee) SEBELUM F-84 akan dapat
    // STATUS_CHANGED (#3) di atas ENTERED_REVIEW milik admin. Keputusan Boss opsi
    // (b): #3 diam total kalau transisi ini sudah ditangkap #6, jadi memberB
    // seharusnya TIDAK dapat notifikasi apa pun dari transisi ini.
    $this->actingAs($memberA)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $review->id,
    ])->assertSessionDoesntHaveErrors();

    expect($memberB->notifications()->count())->toBe(0)
        ->and($admin->notifications()->where('data->type', TaskNotification::ENTERED_REVIEW)->count())->toBe(1);
});

test('approving a task notifies the assignee (F-35 #7)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createNotificationProject($admin, [$member->id]);
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createNotificationTask($project, $admin, $review);
    $task->assignees()->sync([$member->id]);
    $member->notifications()->delete(); // buang notif ASSIGNED dari setup sync() di atas

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), [
        'quality_rating' => 5,
    ])->assertSessionDoesntHaveErrors();

    // F-84: approve HANYA is_review -> is_completed, satu transisi, jadi HARUS
    // tepat 1 notif (APPROVED) — bukan 2 (APPROVED + STATUS_CHANGED generik).
    expect($member->notifications()->where('data->type', TaskNotification::APPROVED)->count())->toBe(1)
        ->and($member->notifications()->count())->toBe(1);
});

test('rejecting a task notifies the assignee with the reason included (F-35 #8)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createNotificationProject($admin, [$member->id]);
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createNotificationTask($project, $admin, $review);
    $task->assignees()->sync([$member->id]);
    $member->notifications()->delete(); // buang notif ASSIGNED dari setup sync() di atas

    $this->actingAs($admin)->patch(route('tasks.reject', [$project, $task]), [
        'reason' => 'Data belum lengkap.',
    ])->assertSessionDoesntHaveErrors();

    $notification = $member->notifications()->where('data->type', TaskNotification::REJECTED)->first();

    // F-84: reject HANYA is_review -> is_work_state, satu transisi, jadi HARUS
    // tepat 1 notif (REJECTED) — bukan 2 (REJECTED + STATUS_CHANGED generik).
    expect($notification)->not->toBeNull()
        ->and($notification->data['reason'])->toBe('Data belum lengkap.')
        ->and($member->notifications()->count())->toBe(1);
});
