<?php

/**
 * ==========================================================
 * MODUL       : AttachmentDownloadTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi download attachment (F-95) — assignee/member project/
 *               admin boleh, project lain 404 (bukan bocorkan keberadaan), dan
 *               ID attachment tidak bisa "ditarik" lewat URL task/project lain
 *               (A1 — scopeBindings F-76, satu-satunya jalur resolusi attachment).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : AttachmentController::download()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test 404 project lain adalah pagar F-95 — kalau attachment task
 *               project lain bisa diunduh, data kerja tim lain bocor lintas project.
 * ==========================================================
 */

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createDownloadTestProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Attachment Download Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createDownloadTestTask(Project $project, TaskStatus $status, User $admin, User $assignee): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Attachment download task '.uniqid(),
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
 * catatan realUploadedFile() di AttachmentUploadTest.php.
 */
function downloadTestPdf(string $name = 'laporan.pdf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'attach');
    file_put_contents($path, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\n");

    return new UploadedFile($path, $name, null, null, true);
}

test('assignee, project member, and admin can all download the attachment', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $otherMember = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDownloadTestProject($admin, [$assignee->id, $otherMember->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createDownloadTestTask($project, $todo, $admin, $assignee);

    $this->actingAs($assignee)->post(route('attachments.store', [$project, $task]), ['file' => downloadTestPdf()])
        ->assertRedirect();
    $attachment = Attachment::where('task_id', $task->id)->firstOrFail();

    foreach ([$assignee, $otherMember, $admin] as $user) {
        $response = $this->actingAs($user)->get(route('attachments.download', [$project, $task, $attachment]));
        $response->assertOk();
    }
});

test('a member of another project gets 404 when trying to download, not 403 (F-95)', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDownloadTestProject($admin, [$assignee->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createDownloadTestTask($project, $todo, $admin, $assignee);

    $this->actingAs($assignee)->post(route('attachments.store', [$project, $task]), ['file' => downloadTestPdf()])
        ->assertRedirect();
    $attachment = Attachment::where('task_id', $task->id)->firstOrFail();

    $response = $this->actingAs($outsider)->get(route('attachments.download', [$project, $task, $attachment]));

    $response->assertNotFound();
});

test('an attachment cannot be reached through a task/project it does not belong to (A1/F-76 scopeBindings)', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $projectA = createDownloadTestProject($admin, [$assignee->id]);
    $todoA = TaskStatus::where('project_id', $projectA->id)->where('position', 0)->firstOrFail();
    $taskA = createDownloadTestTask($projectA, $todoA, $admin, $assignee);

    $this->actingAs($assignee)->post(route('attachments.store', [$projectA, $taskA]), ['file' => downloadTestPdf()])
        ->assertRedirect();
    $attachment = Attachment::where('task_id', $taskA->id)->firstOrFail();

    $projectB = createDownloadTestProject($admin, [$assignee->id]);
    $todoB = TaskStatus::where('project_id', $projectB->id)->where('position', 0)->firstOrFail();
    $taskB = createDownloadTestTask($projectB, $todoB, $admin, $assignee);

    // Attachment sungguhan (task A), tapi diakses lewat URL project/task B —
    // scopeBindings WAJIB menolak, bukan cuma mengandalkan pengecekan manual.
    $response = $this->actingAs($admin)->get(route('attachments.download', [$projectB, $taskB, $attachment]));

    $response->assertNotFound();
});
