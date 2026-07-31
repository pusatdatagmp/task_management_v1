<?php

/**
 * ==========================================================
 * MODUL       : CommentTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi CRUD komentar (v1.0 H3) — gating project member (F-95),
 *               edit/hapus HANYA penulis via soft-delete (F-115), dan bukti KERAS
 *               komentar TIDAK PERNAH masuk activity_log (F-113).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : CommentController
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test F-113 adalah pagar SATU-SATUNYA yang mencegah isi obrolan
 *               user diam-diam mencemari activity_logs (sumber 4/6 metrik KPI) —
 *               kalau ini lolos diam-diam, data KPI tercampur data non-KPI selamanya.
 * ==========================================================
 */

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

function createCommentProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Comment Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createCommentTask(Project $project, User $admin, array $assigneeIds = []): Task
{
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Comment task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    if ($assigneeIds) {
        $task->assignees()->sync($assigneeIds);
    }

    return $task;
}

test('a project member can post a comment on a task (F-95)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCommentProject($admin, [$member->id]);
    $task = createCommentTask($project, $admin);

    $response = $this->actingAs($member)->post(route('comments.store', [$project, $task]), [
        'body' => 'Halo, ini komentar pertama.',
    ]);

    $response->assertRedirect();
    $comment = Comment::where('task_id', $task->id)->firstOrFail();
    expect($comment->body)->toBe('Halo, ini komentar pertama.')
        ->and($comment->user_id)->toBe($member->id);
});

test('a user who is not a project member cannot comment (F-95)', function () {
    $admin = User::factory()->admin()->create();
    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCommentProject($admin);
    $task = createCommentTask($project, $admin);

    $response = $this->actingAs($outsider)->post(route('comments.store', [$project, $task]), [
        'body' => 'Saya bukan member.',
    ]);

    $response->assertNotFound();
    expect(Comment::where('task_id', $task->id)->count())->toBe(0);
});

test('editing someone else\'s comment is forbidden (F-115)', function () {
    $admin = User::factory()->admin()->create();
    $author = User::factory()->create(['organization_id' => $admin->organization_id]);
    $otherMember = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCommentProject($admin, [$author->id, $otherMember->id]);
    $task = createCommentTask($project, $admin);

    $this->actingAs($author)->post(route('comments.store', [$project, $task]), [
        'body' => 'Komentar asli.',
    ])->assertRedirect();
    $comment = Comment::where('task_id', $task->id)->firstOrFail();

    $response = $this->actingAs($otherMember)->put(route('comments.update', [$project, $task, $comment]), [
        'body' => 'Coba ubah punya orang lain.',
    ]);

    $response->assertForbidden();
    expect($comment->fresh()->body)->toBe('Komentar asli.');
});

test('the author can soft-delete their own comment, the row still exists', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCommentProject($admin, [$member->id]);
    $task = createCommentTask($project, $admin);

    $this->actingAs($member)->post(route('comments.store', [$project, $task]), [
        'body' => 'Akan dihapus.',
    ])->assertRedirect();
    $comment = Comment::where('task_id', $task->id)->firstOrFail();

    $response = $this->actingAs($member)->delete(route('comments.destroy', [$project, $task, $comment]));

    $response->assertRedirect();
    expect(Comment::withTrashed()->whereKey($comment->id)->exists())->toBeTrue()
        ->and(Comment::whereKey($comment->id)->exists())->toBeFalse() // scope default exclude trashed
        ->and($comment->fresh()->deleted_at)->not->toBeNull();
});

test('comments are never written to activity_log (F-113)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCommentProject($admin, [$member->id]);
    $task = createCommentTask($project, $admin);

    $this->actingAs($member)->post(route('comments.store', [$project, $task]), [
        'body' => 'Komentar ini tidak boleh ada di activity_logs.',
    ])->assertRedirect();
    $comment = Comment::where('task_id', $task->id)->firstOrFail();

    $this->actingAs($member)->put(route('comments.update', [$project, $task, $comment]), [
        'body' => 'Komentar diedit, tetap tidak boleh ada di log.',
    ])->assertRedirect();

    $this->actingAs($member)->delete(route('comments.destroy', [$project, $task, $comment]));

    expect(ActivityLog::where('subject_type', Comment::class)->count())->toBe(0)
        ->and(ActivityLog::where('subject_type', Task::class)->where('subject_id', $task->id)->pluck('event'))
        ->not->toContain('comment_added', 'comment_updated', 'comment_deleted');
});
