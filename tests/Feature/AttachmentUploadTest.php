<?php

/**
 * ==========================================================
 * MODUL       : AttachmentUploadTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi upload attachment output (F-49) — gating assignee/admin
 *               (F-95), validasi isi file NYATA bukan ekstensi klaim (A2), batas
 *               10 MB, dan freeze upload begitu task disetujui (F-104).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : AttachmentController::store()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test mime-spoofing (.exe isi, ekstensi .pdf) adalah SATU-SATUNYA
 *               pagar otomatis A2 — kalau ini lolos diam-diam, upload malware
 *               berkedok dokumen tidak akan ketahuan sebelum produksi.
 * ==========================================================
 */

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createAttachmentProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Attachment Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createAttachmentTask(Project $project, TaskStatus $status, User $admin, ?User $assignee = null): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Attachment task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    if ($assignee) {
        $task->assignees()->sync([$assignee->id]);
    }

    return $task;
}

/**
 * KONTRAK: UploadedFile SUNGGUHAN (bukan Illuminate\Http\Testing\File) yang isinya
 * ditulis ke file fisik nyata — WORKAROUND: Testing\File meng-override
 * getMimeType() supaya menebak dari NAMA (MimeType::from($this->name)), bukan isi
 * file, jadi guessExtension() (dipakai rule mimes:) ikut ketipu nama, BUKAN
 * mengetes isi asli. UploadedFile dasar TIDAK override itu — guessExtension()-nya
 * genuinely membaca magic bytes lewat fileinfo (dibuktikan lewat tinker, lihat
 * laporan H5), jadi ini SATU-SATUNYA cara mengetes A2 (isi file, bukan nama) di
 * Pest secara otomatis.
 */
function realUploadedFile(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'attach');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

function fakePdf(string $name = 'laporan.pdf'): UploadedFile
{
    return realUploadedFile($name, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\n");
}

test('assignee can upload output attachment to their own task', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createAttachmentProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createAttachmentTask($project, $todo, $admin, $member);

    $response = $this->actingAs($member)->post(route('attachments.store', [$project, $task]), [
        'file' => fakePdf(),
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();

    $attachment = Attachment::where('task_id', $task->id)->firstOrFail();
    expect($attachment->type)->toBe('output')
        ->and($attachment->uploaded_by)->toBe($member->id)
        ->and($attachment->file_name)->toBe('laporan.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf');

    Storage::disk('local')->assertExists($attachment->file_path);
});

test('non-assignee non-admin cannot upload output attachment (F-95)', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $bystander = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createAttachmentProject($admin, [$assignee->id, $bystander->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createAttachmentTask($project, $todo, $admin, $assignee);

    $response = $this->actingAs($bystander)->post(route('attachments.store', [$project, $task]), [
        'file' => fakePdf(),
    ]);

    $response->assertForbidden();
    expect(Attachment::where('task_id', $task->id)->count())->toBe(0);
});

test('file larger than 10MB is rejected', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createAttachmentProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createAttachmentTask($project, $todo, $admin, $member);

    $tooBig = UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf');

    $response = $this->actingAs($member)->post(route('attachments.store', [$project, $task]), [
        'file' => $tooBig,
    ]);

    $response->assertSessionHasErrors('file');
    expect(Attachment::where('task_id', $task->id)->count())->toBe(0);
});

test('an .exe renamed to .pdf is rejected by real content sniffing (A2)', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createAttachmentProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createAttachmentTask($project, $todo, $admin, $member);

    // SUMBER: "MZ" = magic bytes header PE/EXE Windows sungguhan, BUKAN cuma
    // ekstensi yang dipalsukan — inilah yang dites A2 (isi file, bukan nama).
    $disguisedExe = realUploadedFile('dokumen.pdf', 'MZ'.str_repeat("\x90\x00", 200));

    $response = $this->actingAs($member)->post(route('attachments.store', [$project, $task]), [
        'file' => $disguisedExe,
    ]);

    $response->assertSessionHasErrors('file');
    expect(Attachment::where('task_id', $task->id)->count())->toBe(0);
});

test('uploading to an already approved task is rejected (F-104)', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createAttachmentProject($admin, [$member->id]);
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $task = createAttachmentTask($project, $review, $admin, $member);

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), [
        'quality_rating' => 5,
    ])->assertRedirect();

    $task->refresh();
    expect($task->approved_at)->not->toBeNull();

    $response = $this->actingAs($member)->post(route('attachments.store', [$project, $task]), [
        'file' => fakePdf(),
    ]);

    $response->assertForbidden();
    expect(Attachment::where('task_id', $task->id)->count())->toBe(0);
});
