<?php

/**
 * ==========================================================
 * MODUL       : TaskTimelineTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi riwayat (activity log) PER-TASK di halaman detail
 *               (v1.0 H4) — gating F-95 (membership, BUKAN activity.view), dan
 *               label manusiawi (F-106), bukan event string mentah.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::show()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test label manusiawi adalah pagar SATU-SATUNYA F-106 di jalur
 *               timeline per-task — kalau lolos diam-diam, member awam melihat
 *               string teknis seperti "status_changed" alih-alih kalimat wajar.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function createTimelineProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Timeline Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

test('a member can see the activity history of their own assigned task (F-95)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createTimelineProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Timeline task',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
    $task->assignees()->sync([$member->id]); // memicu ActivityLog 'assigned'

    $response = $this->actingAs($member)->get(route('tasks.show', [$project, $task]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('task.activity_logs')
        ->where('task.activity_logs.0.message', fn (string $message) => str_contains($message, 'created') === false
            && str_contains($message, $task->title)));
});

test('a member from another project gets a 404, never sees the task history (F-95)', function () {
    $admin = User::factory()->admin()->create();
    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createTimelineProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Private timeline task',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($outsider)->get(route('tasks.show', [$project, $task]));

    $response->assertNotFound();
});

test('event labels are rendered as human sentences, never the raw event string (F-106)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createTimelineProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Status label task',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
    $task->assignees()->sync([$member->id]);

    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    $response = $this->actingAs($member)->get(route('tasks.show', [$project, $task]));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('task.activity_logs', function ($logs) {
            $messages = collect($logs)->pluck('message');

            // TIDAK ADA satu pun pesan yang berisi event string mentah.
            foreach (['status_changed', 'assigned', 'created'] as $rawEvent) {
                if ($messages->contains(fn ($m) => $m === $rawEvent || str_contains($m, "\"{$rawEvent}\""))) {
                    return false;
                }
            }

            // TAPI ada kalimat manusiawi yang menyebut status tujuan (IN PROGRESS)
            // dan judul task, persis pola contoh prompt ("... -> DIKERJAKAN").
            return $messages->contains(fn ($m) => str_contains($m, 'IN PROGRESS') && str_contains($m, 'Status label task'));
        }));
});
