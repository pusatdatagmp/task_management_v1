<?php

/**
 * ==========================================================
 * MODUL       : MentionNotifTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi notifikasi @mention (v1.0 H3, F-114) — hanya member
 *               project yang bisa disebut (C1), pelaku tidak dapat notif atas
 *               mention diri sendiri (F-36), dan edit yang menambah mention baru
 *               cuma menotif yang BARU disebut (C4), bukan mengulang yang lama.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : CommentController, CommentObserver, MentionNotification
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test C4 adalah pagar SATU-SATUNYA yang mencegah spam — tanpa ini,
 *               tiap edit komentar yang menyebut orang yang sama berulang kali akan
 *               membanjiri notifikasi orang itu (persis yang F-36 coba cegah).
 * ==========================================================
 */

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Collection;

function createMentionProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Mention Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createMentionTask(Project $project, User $admin): Task
{
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Mention task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
}

function mentionsOfType(User $user, string $type): Collection
{
    return $user->notifications()->get()->filter(fn ($n) => ($n->data['type'] ?? null) === $type);
}

test('mentioning a project member notifies them (F-114)', function () {
    $admin = User::factory()->admin()->create();
    $author = User::factory()->create(['organization_id' => $admin->organization_id]);
    $mentioned = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createMentionProject($admin, [$author->id, $mentioned->id]);
    $task = createMentionTask($project, $admin);

    $this->actingAs($author)->post(route('comments.store', [$project, $task]), [
        'body' => "Tolong dicek ya @[{$mentioned->name}]({$mentioned->id}).",
    ])->assertRedirect();

    $comment = Comment::where('task_id', $task->id)->firstOrFail();
    expect($comment->mentioned_user_ids)->toBe([$mentioned->id]);
    expect(mentionsOfType($mentioned, 'mentioned'))->toHaveCount(1);
});

test('mentioning yourself does not generate a notification (F-36)', function () {
    $admin = User::factory()->admin()->create();
    $author = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createMentionProject($admin, [$author->id]);
    $task = createMentionTask($project, $admin);

    $this->actingAs($author)->post(route('comments.store', [$project, $task]), [
        'body' => "Catatan buat diri sendiri @[{$author->name}]({$author->id}).",
    ])->assertRedirect();

    expect(mentionsOfType($author, 'mentioned'))->toHaveCount(0);
});

test('mentioning a user who is not a project member is silently ignored, no notification (C1)', function () {
    $admin = User::factory()->admin()->create();
    $author = User::factory()->create(['organization_id' => $admin->organization_id]);
    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createMentionProject($admin, [$author->id]); // outsider bukan member

    $task = createMentionTask($project, $admin);

    $this->actingAs($author)->post(route('comments.store', [$project, $task]), [
        'body' => "Halo @[{$outsider->name}]({$outsider->id}), kamu bukan member project ini.",
    ])->assertRedirect();

    $comment = Comment::where('task_id', $task->id)->firstOrFail();
    expect($comment->mentioned_user_ids)->toBe([]);
    expect(mentionsOfType($outsider, 'mentioned'))->toHaveCount(0);
});

test('editing a comment to add a new mention only notifies the newly mentioned user, not a repeat (C4)', function () {
    $admin = User::factory()->admin()->create();
    $author = User::factory()->create(['organization_id' => $admin->organization_id]);
    $userA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $userB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createMentionProject($admin, [$author->id, $userA->id, $userB->id]);
    $task = createMentionTask($project, $admin);

    $this->actingAs($author)->post(route('comments.store', [$project, $task]), [
        'body' => "Pertama, sebut @[{$userA->name}]({$userA->id}) saja.",
    ])->assertRedirect();
    $comment = Comment::where('task_id', $task->id)->firstOrFail();

    expect(mentionsOfType($userA, 'mentioned'))->toHaveCount(1);
    expect(mentionsOfType($userB, 'mentioned'))->toHaveCount(0);

    // Edit: tambah mention userB, userA TETAP disebut (tidak dihapus dari body).
    $this->actingAs($author)->put(route('comments.update', [$project, $task, $comment]), [
        'body' => "Pertama, sebut @[{$userA->name}]({$userA->id}) saja. Sekarang juga @[{$userB->name}]({$userB->id}).",
    ])->assertRedirect();

    // userA TIDAK dapat notif KEDUA (masih 1 dari sebelumnya) -- C4.
    expect(mentionsOfType($userA, 'mentioned'))->toHaveCount(1);
    // userB BARU disebut -> dapat notif PERTAMA kalinya.
    expect(mentionsOfType($userB, 'mentioned'))->toHaveCount(1);
});
