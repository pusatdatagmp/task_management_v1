<?php

/**
 * ==========================================================
 * MODUL       : ExtensionNotifTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi trigger notifikasi #9 (diajukan -> admin) & #10
 *               (diputuskan -> pemohon) — F-35 genap 10 trigger. F-36: pelaku
 *               tidak dapat notif atas aksinya sendiri, termasuk kasus admin
 *               mengajukan lalu memutuskan pengajuannya sendiri (matriks BF §6
 *               mengizinkan admin ajukan extension).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : DeadlineExtensionObserver, TaskNotification
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test F-36 self-decide adalah SATU-SATUNYA pagar untuk kasus admin
 *               ajukan+putuskan sendiri — tanpa ini, inbox bisa kebanjiran notif
 *               yang tidak berarti dari aksi sendiri (semangat F-36).
 * ==========================================================
 */

use App\Models\DeadlineExtension;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Collection;

function createNotifExtProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Extension Notif Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createNotifExtTask(Project $project, User $admin, User $assignee): Task
{
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Extension notif task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addDay(),
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync([$assignee->id]);

    return $task;
}

function notificationsOfType(User $user, string $type): Collection
{
    return $user->notifications()->get()->filter(fn ($n) => ($n->data['type'] ?? null) === $type);
}

test('submitting an extension request notifies admins (trigger #9)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createNotifExtProject($admin, [$member->id]);
    $task = createNotifExtTask($project, $admin, $member);

    $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'reason' => 'Butuh waktu tambahan.',
    ])->assertRedirect();

    expect(notificationsOfType($admin, 'extension_requested'))->toHaveCount(1);
});

test('deciding an extension notifies the requester with the outcome (trigger #10)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createNotifExtProject($admin, [$member->id]);
    $task = createNotifExtTask($project, $admin, $member);

    $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'reason' => 'Butuh waktu tambahan.',
    ])->assertRedirect();
    $extension = DeadlineExtension::where('task_id', $task->id)->firstOrFail();

    $this->actingAs($admin)->patch(route('extensions.reject', $extension), [
        'review_note' => 'Belum cukup alasan.',
    ])->assertRedirect();

    $decided = notificationsOfType($member, 'extension_decided');
    expect($decided)->toHaveCount(1);

    $notification = $decided->first();
    expect($notification->data['extension_outcome'])->toBe('rejected')
        ->and($notification->data['reason'])->toBe('Belum cukup alasan.');
});

test('the actor never receives a notification for their own action (F-36)', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create(['organization_id' => $adminA->organization_id]);
    $member = User::factory()->create(['organization_id' => $adminA->organization_id]);
    $project = createNotifExtProject($adminA, [$adminB->id, $member->id]);
    $task = createNotifExtTask($project, $adminA, $member);

    // adminA sendiri yang mengajukan (matriks BF §6 mengizinkan admin ajukan).
    $this->actingAs($adminA)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'reason' => 'Admin mengajukan untuk task ini.',
    ])->assertRedirect();
    $extension = DeadlineExtension::where('task_id', $task->id)->firstOrFail();

    // Trigger #9: adminB (bukan pelaku) dapat notif, adminA (pelaku) TIDAK.
    expect(notificationsOfType($adminB, 'extension_requested'))->toHaveCount(1);
    expect(notificationsOfType($adminA, 'extension_requested'))->toHaveCount(0);

    // adminA memutuskan pengajuannya SENDIRI.
    $this->actingAs($adminA)->patch(route('extensions.approve', $extension), [])->assertRedirect();

    // Trigger #10: adminA adalah requested_by SEKALIGUS pelaku -> TIDAK dinotif.
    expect(notificationsOfType($adminA, 'extension_decided'))->toHaveCount(0);
});
