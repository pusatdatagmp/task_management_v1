<?php

/**
 * ==========================================================
 * MODUL       : TaskTemplateChecklistFormTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi form CRUD template MENGELOLA checklist_items (F-123) —
 *               'sometimes' (opsional) supaya caller lama tanpa field ini (lihat
 *               TaskTemplateTest.php) tetap lulus, sync = hapus-lalu-buat-ulang
 *               saat field ini BENAR-BENAR dikirim, TIDAK menyentuh checklist
 *               existing kalau field-nya absen dari request.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskTemplateController::store()/update()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "field absen tidak wipe" adalah pagar regresi — kalau hilang,
 *               admin yang cuma mengubah title lewat integrasi API lama (belum
 *               tahu field checklist_items) diam-diam MENGHAPUS checklist template.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;

function createTemplateChecklistFormProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Template Checklist Form Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

test('buat template dengan checklist_items menyimpan item dalam urutan position', function () {
    $admin = User::factory()->admin()->create();
    $project = createTemplateChecklistFormProject($admin);

    $response = $this->actingAs($admin)->post(route('task-templates.store', $project), [
        'title' => 'Template dengan checklist form',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'recurrence_config' => [],
        'default_assignees' => [],
        'checklist_items' => ['Langkah 1', 'Langkah 2'],
    ]);

    $response->assertRedirect(route('task-templates.index', $project));
    $template = TaskTemplate::where('project_id', $project->id)->where('title', 'Template dengan checklist form')->firstOrFail();
    $items = $template->checklistItems()->orderBy('position')->get();

    expect($items->pluck('text')->all())->toBe(['Langkah 1', 'Langkah 2']);
});

test('update template dengan checklist_items baru MENGGANTI seluruh daftar lama', function () {
    $admin = User::factory()->admin()->create();
    $project = createTemplateChecklistFormProject($admin);

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Template ganti checklist',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
    ]);
    $template->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'Lama', 'position' => 0]);

    $response = $this->actingAs($admin)->put(route('task-templates.update', [$project, $template]), [
        'title' => $template->title,
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'recurrence_config' => [],
        'default_assignees' => [],
        'checklist_items' => ['Baru 1', 'Baru 2'],
    ]);

    $response->assertRedirect(route('task-templates.index', $project));
    $items = $template->fresh()->checklistItems()->orderBy('position')->get();
    expect($items->pluck('text')->all())->toBe(['Baru 1', 'Baru 2']);
});

test('update template TANPA field checklist_items TIDAK menyentuh checklist yang sudah ada', function () {
    $admin = User::factory()->admin()->create();
    $project = createTemplateChecklistFormProject($admin);

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Template tanpa field checklist di update',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
    ]);
    $template->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'Tetap ada', 'position' => 0]);

    // Simulasi caller lama (mis. TaskTemplateTest.php existing) yang belum tahu
    // field checklist_items -- TIDAK dikirim sama sekali.
    $response = $this->actingAs($admin)->put(route('task-templates.update', [$project, $template]), [
        'title' => 'Judul berubah saja',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'recurrence_config' => [],
        'default_assignees' => [],
    ]);

    $response->assertRedirect(route('task-templates.index', $project));
    expect($template->fresh()->checklistItems()->pluck('text')->all())->toBe(['Tetap ada']);
});
