<?php

/**
 * ==========================================================
 * MODUL       : ExtensionFlowTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi alur perpanjangan deadline (F-50) — gating assignee/admin
 *               (F-95), evidence via infra Attachment H5, F-47 (original_due_date
 *               diisi hanya sekali, tidak ditimpa extension kedua), reject tidak
 *               mengubah due_date.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : DeadlineExtensionController, DeadlineExtensionObserver
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "approve kedua tidak menimpa original_due_date" adalah pagar
 *               SATU-SATUNYA F-47 untuk kasus multi-extension — kalau ini lolos
 *               diam-diam, metrik on-time bohong untuk task yang diperpanjang >1x.
 * ==========================================================
 */

use App\Models\Attachment;
use App\Models\DeadlineExtension;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createExtProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Extension Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createExtTask(Project $project, User $admin, User $assignee, ?Carbon $dueDate = null): Task
{
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Extension task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => $dueDate ?? now()->addDay(),
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync([$assignee->id]);

    return $task;
}

function extEvidence(string $name = 'bukti.pdf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'attach');
    file_put_contents($path, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\n");

    return new UploadedFile($path, $name, null, null, true);
}

test('assignee can submit an extension request with evidence, starting as pending', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createExtProject($admin, [$member->id]);
    $task = createExtTask($project, $admin, $member);

    $response = $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'additional_minutes' => 30,
        'reason' => 'Butuh waktu tambahan karena revisi scope.',
        'evidence_type' => 'file',
        'evidence_file' => extEvidence(),
    ]);

    $response->assertRedirect(route('extensions.my'));

    $extension = DeadlineExtension::where('task_id', $task->id)->firstOrFail();
    expect($extension->status)->toBe('pending')
        ->and($extension->requested_by)->toBe($member->id)
        ->and($extension->additional_minutes)->toBe(30);

    $evidence = Attachment::where('deadline_extension_id', $extension->id)->firstOrFail();
    expect($evidence->type)->toBe('evidence')
        ->and($evidence->task_id)->toBe($task->id);
    Storage::disk('local')->assertExists($evidence->file_path);
});

test('revisi 2026-08-06 item 4: evidence berupa link tersimpan, type=evidence', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createExtProject($admin, [$member->id]);
    $task = createExtTask($project, $admin, $member);

    $response = $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'reason' => 'Bukti ada di link ini.',
        'evidence_type' => 'link',
        'evidence_url' => 'https://drive.google.com/file/d/bukti',
    ]);

    $response->assertRedirect(route('extensions.my'));

    $extension = DeadlineExtension::where('task_id', $task->id)->firstOrFail();
    $evidence = Attachment::where('deadline_extension_id', $extension->id)->firstOrFail();
    expect($evidence->type)->toBe('evidence')
        ->and($evidence->content_type)->toBe('link')
        ->and($evidence->url)->toBe('https://drive.google.com/file/d/bukti');
});

test('revisi 2026-08-06 item 4: evidence_type null -- pengajuan tetap sah tanpa lampiran apa pun (F-49 opsional)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createExtProject($admin, [$member->id]);
    $task = createExtTask($project, $admin, $member);

    $response = $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'reason' => 'Tanpa bukti.',
    ]);

    $response->assertRedirect(route('extensions.my'));

    $extension = DeadlineExtension::where('task_id', $task->id)->firstOrFail();
    expect(Attachment::where('deadline_extension_id', $extension->id)->count())->toBe(0);
});

test('a user who is not the assignee nor admin cannot submit an extension request (F-95)', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $bystander = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createExtProject($admin, [$assignee->id, $bystander->id]);
    $task = createExtTask($project, $admin, $assignee);

    $response = $this->actingAs($bystander)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'reason' => 'Coba ajukan punya orang lain.',
    ]);

    $response->assertForbidden();
    expect(DeadlineExtension::where('task_id', $task->id)->count())->toBe(0);
});

test('requested_due_date equal to the current due_date is accepted — only backward is rejected (F-108, H7 fix)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createExtProject($admin, [$member->id]);
    $dueDate = now()->addDay();
    $task = createExtTask($project, $admin, $member, $dueDate);

    // SAMA dengan due_date saat ini -> DITERIMA (cuma nambah additional_minutes
    // tanpa geser tenggat, kasus sah menurut F-108).
    $sameDate = $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => $dueDate->format('Y-m-d H:i:s'),
        'additional_minutes' => 60,
        'reason' => 'Cuma butuh tambahan waktu, tenggat tidak perlu geser.',
    ]);
    $sameDate->assertSessionDoesntHaveErrors();
    expect(DeadlineExtension::where('task_id', $task->id)->count())->toBe(1);

    // MUNDUR dari due_date saat ini -> DITOLAK.
    $backward = $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => $dueDate->copy()->subHour()->format('Y-m-d H:i:s'),
        'reason' => 'Coba mundurkan tenggat.',
    ]);
    $backward->assertSessionHasErrors('requested_due_date');
    expect(DeadlineExtension::where('task_id', $task->id)->count())->toBe(1);
});

test('admin approving fills original_due_date and updates due_date + estimated_minutes (F-47)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createExtProject($admin, [$member->id]);
    $originalDue = now()->addDay();
    $task = createExtTask($project, $admin, $member, $originalDue);
    $newDue = now()->addDays(5);

    $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => $newDue->format('Y-m-d H:i:s'),
        'additional_minutes' => 45,
        'reason' => 'Perlu tambahan waktu.',
    ])->assertRedirect();

    $extension = DeadlineExtension::where('task_id', $task->id)->firstOrFail();

    $this->actingAs($admin)->patch(route('extensions.approve', $extension), [])->assertRedirect();

    $task->refresh();
    $extension->refresh();

    expect($extension->status)->toBe('approved')
        ->and($task->original_due_date?->format('Y-m-d H:i'))->toBe($originalDue->format('Y-m-d H:i'))
        ->and($task->due_date->format('Y-m-d H:i'))->toBe($newDue->format('Y-m-d H:i'))
        ->and($task->estimated_minutes)->toBe(105); // 60 + 45
});

test('a second approved extension does not overwrite original_due_date (F-47)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createExtProject($admin, [$member->id]);
    $originalDue = now()->addDay();
    $task = createExtTask($project, $admin, $member, $originalDue);

    // Extension pertama: due_date d1 -> d2.
    $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'reason' => 'Perpanjangan pertama.',
    ])->assertRedirect();
    $firstExtension = DeadlineExtension::where('task_id', $task->id)->firstOrFail();
    $this->actingAs($admin)->patch(route('extensions.approve', $firstExtension), [])->assertRedirect();

    $task->refresh();
    expect($task->original_due_date?->format('Y-m-d H:i'))->toBe($originalDue->format('Y-m-d H:i'));

    // Extension kedua: due_date d2 -> d3. original_due_date HARUS tetap d1.
    $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => now()->addDays(10)->format('Y-m-d H:i:s'),
        'reason' => 'Perpanjangan kedua.',
    ])->assertRedirect();
    $secondExtension = DeadlineExtension::where('task_id', $task->id)
        ->where('id', '!=', $firstExtension->id)
        ->firstOrFail();
    $this->actingAs($admin)->patch(route('extensions.approve', $secondExtension), [])->assertRedirect();

    $task->refresh();
    expect($task->original_due_date?->format('Y-m-d H:i'))->toBe($originalDue->format('Y-m-d H:i'))
        ->and($task->due_date->format('Y-m-d H:i'))->toBe(now()->addDays(10)->format('Y-m-d H:i'));
});

test('reject leaves due_date unchanged', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createExtProject($admin, [$member->id]);
    $originalDue = now()->addDay();
    $task = createExtTask($project, $admin, $member, $originalDue);

    $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'reason' => 'Butuh waktu tambahan.',
    ])->assertRedirect();
    $extension = DeadlineExtension::where('task_id', $task->id)->firstOrFail();

    $response = $this->actingAs($admin)->patch(route('extensions.reject', $extension), [
        'review_note' => 'Alasan tidak cukup kuat.',
    ]);

    $response->assertRedirect();
    $task->refresh();
    $extension->refresh();

    expect($extension->status)->toBe('rejected')
        ->and($extension->review_note)->toBe('Alasan tidak cukup kuat.')
        ->and($task->due_date->format('Y-m-d H:i'))->toBe($originalDue->format('Y-m-d H:i'))
        ->and($task->original_due_date)->toBeNull();
});

test('a member without task.approve cannot approve an extension', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createExtProject($admin, [$member->id]);
    $task = createExtTask($project, $admin, $member);

    $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'reason' => 'Butuh waktu tambahan.',
    ])->assertRedirect();
    $extension = DeadlineExtension::where('task_id', $task->id)->firstOrFail();

    $response = $this->actingAs($member)->patch(route('extensions.approve', $extension), []);

    $response->assertForbidden();
});
