<?php

/**
 * ==========================================================
 * MODUL       : AttachmentDeleteTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi F-105 — hanya admin hapus attachment, member APPEND-ONLY
 *               (revisi = upload baru, bukan overwrite). Hapus admin tercatat di
 *               activity_log (F-51). F-107 — hapus TERKUNCI PERMANEN begitu task
 *               disetujui, bahkan untuk admin (v0.8 H6).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : AttachmentController::destroy(), AttachmentObserver::deleted()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "member 403" adalah pagar SATU-SATUNYA F-105 — member yang
 *               bisa menghapus jejak upload sendiri berarti riwayat submit untuk
 *               audit/scoring v1.5 bisa dimanipulasi pelakunya sendiri. Test F-107
 *               adalah pagar SATU-SATUNYA yang mencegah admin sendiri menghapus
 *               bukti kerja yang sudah jadi dasar quality_rating pasca-approve.
 * ==========================================================
 */

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createDeleteTestProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Attachment Delete Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createDeleteTestTask(Project $project, TaskStatus $status, User $admin, User $assignee): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Attachment delete task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync([$assignee->id]);

    return $task;
}

/**
 * KONTRAK: UploadedFile SUNGGUHAN (bukan Illuminate\Http\Testing\File) — lihat
 * catatan realUploadedFile() di AttachmentUploadTest.php (Testing\File menebak
 * mime dari NAMA file, bukan isi, jadi tidak representatif untuk test A2/mimes:).
 */
function deleteTestPdf(string $name = 'laporan.pdf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'attach');
    file_put_contents($path, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\n");

    return new UploadedFile($path, $name, null, null, true);
}

test('member cannot delete an attachment, even their own (F-105)', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDeleteTestProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createDeleteTestTask($project, $todo, $admin, $member);

    $this->actingAs($member)->post(route('attachments.store', [$project, $task]), ['content_type' => 'file', 'file' => deleteTestPdf()])
        ->assertRedirect();
    $attachment = Attachment::where('task_id', $task->id)->firstOrFail();

    $response = $this->actingAs($member)->delete(route('attachments.destroy', [$project, $task, $attachment]));

    $response->assertForbidden();
    expect(Attachment::whereKey($attachment->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($attachment->file_path);
});

test('admin can delete an attachment and it is logged (F-51)', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDeleteTestProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createDeleteTestTask($project, $todo, $admin, $member);

    $this->actingAs($member)->post(route('attachments.store', [$project, $task]), ['content_type' => 'file', 'file' => deleteTestPdf()])
        ->assertRedirect();
    $attachment = Attachment::where('task_id', $task->id)->firstOrFail();
    $path = $attachment->file_path;

    $response = $this->actingAs($admin)->delete(route('attachments.destroy', [$project, $task, $attachment]));

    $response->assertRedirect();
    expect(Attachment::whereKey($attachment->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);

    expect(ActivityLog::where('subject_type', Attachment::class)
        ->where('subject_id', $attachment->id)
        ->where('event', 'deleted')
        ->exists())->toBeTrue();
});

test('even admin cannot delete an attachment from an already approved task (F-107)', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDeleteTestProject($admin, [$member->id]);
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createDeleteTestTask($project, $review, $admin, $member);

    $this->actingAs($member)->post(route('attachments.store', [$project, $task]), ['content_type' => 'file', 'file' => deleteTestPdf()])
        ->assertRedirect();
    $attachment = Attachment::where('task_id', $task->id)->firstOrFail();

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 5])
        ->assertRedirect();
    $task->refresh();
    expect($task->approved_at)->not->toBeNull();

    $response = $this->actingAs($admin)->delete(route('attachments.destroy', [$project, $task, $attachment]));

    $response->assertForbidden();
    expect(Attachment::whereKey($attachment->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($attachment->file_path);
});

test('member revision is append-only: two uploads leave two attachment rows', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDeleteTestProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createDeleteTestTask($project, $todo, $admin, $member);

    $this->actingAs($member)->post(route('attachments.store', [$project, $task]), ['content_type' => 'file', 'file' => deleteTestPdf('v1.pdf')])
        ->assertRedirect();
    $this->actingAs($member)->post(route('attachments.store', [$project, $task]), ['content_type' => 'file', 'file' => deleteTestPdf('v2.pdf')])
        ->assertRedirect();

    expect(Attachment::where('task_id', $task->id)->count())->toBe(2);
});
