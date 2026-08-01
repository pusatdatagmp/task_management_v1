<?php

/**
 * ==========================================================
 * MODUL       : AllTaskTemplatesTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi halaman "Tugas Berulang" flat lintas project (F-140/F-144/
 *               F-147, v1.2 H7b) — listing gabungan SEMUA project, gating task.manage
 *               (F-90). CRUD template SENDIRI tidak disentuh sesi ini (F-46 utuh,
 *               sudah dites TaskTemplateTest) — test ini HANYA menyasar endpoint listing baru.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskTemplateController::allProjects()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau gating task.manage bocor, member biasa bisa lihat blueprint
 *               recurring SELURUH organisasi (bukan cuma yang relevan untuknya).
 * ==========================================================
 */

use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;

function createAllTemplatesProject(User $admin, string $suffix = ''): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'All Templates Project '.$suffix.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

test('a user without task.manage cannot access Tugas Berulang', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($member)->get(route('task-templates.all'));

    $response->assertForbidden();
});

test('Tugas Berulang lists templates from multiple projects on one page', function () {
    $admin = User::factory()->admin()->create();
    $projectA = createAllTemplatesProject($admin, 'A');
    $projectB = createAllTemplatesProject($admin, 'B');

    $templateA = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $projectA->id,
        'title' => 'Template A',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
    ]);
    $templateB = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $projectB->id,
        'title' => 'Template B',
        'task_type' => 'weekly',
        'estimated_minutes' => 60,
        'points' => 8,
        'priority' => 'normal',
        'recurrence_config' => ['day_of_week' => 1],
        'default_assignees' => [],
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('task-templates.all'));

    $response->assertInertia(fn ($page) => $page
        ->component('task-templates/all')
        ->has('templates', 2)
        ->where('templates.0.id', fn ($id) => in_array($id, [$templateA->id, $templateB->id]))
        ->where('templates.1.id', fn ($id) => in_array($id, [$templateA->id, $templateB->id])));
});

test('a template from another organization never appears on Tugas Berulang (F-15)', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    createAllTemplatesProject($admin);
    $foreignProject = createAllTemplatesProject($otherAdmin);

    TaskTemplate::create([
        'organization_id' => $otherAdmin->organization_id,
        'project_id' => $foreignProject->id,
        'title' => 'Template org lain',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('task-templates.all'));

    $response->assertInertia(fn ($page) => $page->has('templates', 0));
});
