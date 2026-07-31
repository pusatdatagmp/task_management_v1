<?php

/**
 * ==========================================================
 * MODUL       : TaskTemplateTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi CRUD blueprint template recurring (F-46, v0.8 H4 Fase A) —
 *               task_type dibatasi ke daily/weekly/monthly (A2), default_assignees
 *               tervalidasi member project SAAT SIMPAN (F-86), edit tidak menyentuh
 *               instance yang sudah lahir (A6), gating permission task.manage (F-90).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskTemplateController
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test A6 adalah pagar F-46 (template != task) — kalau update()
 *               diam-diam cascading ke instance lama, riwayat KPI instance itu
 *               berubah retroaktif padahal task-nya sendiri tidak pernah diedit.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;

function createTemplateTestProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Template CRUD Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

test('admin bisa buat template daily', function () {
    $admin = User::factory()->admin()->create();
    $project = createTemplateTestProject($admin);

    $response = $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        'title' => 'Laporan Harian',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'recurrence_config' => [],
        'default_assignees' => [],
    ]);

    $response->assertRedirect(route('task-templates.index', $project->id));
    expect(TaskTemplate::where('project_id', $project->id)->where('title', 'Laporan Harian')->exists())->toBeTrue();
});

test('admin bisa buat template weekly dengan day_of_week', function () {
    $admin = User::factory()->admin()->create();
    $project = createTemplateTestProject($admin);

    $response = $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        'title' => 'Rapat Mingguan',
        'task_type' => 'weekly',
        'estimated_minutes' => 60,
        'points' => 10,
        'recurrence_config' => ['day_of_week' => 3],
        'default_assignees' => [],
    ]);

    $response->assertRedirect(route('task-templates.index', $project->id));
    $template = TaskTemplate::where('project_id', $project->id)->where('title', 'Rapat Mingguan')->firstOrFail();
    expect($template->recurrence_config)->toBe(['day_of_week' => 3]);
});

test('weekly tanpa day_of_week ditolak (A4)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTemplateTestProject($admin);

    $response = $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        'title' => 'Rapat Mingguan Invalid',
        'task_type' => 'weekly',
        'estimated_minutes' => 60,
        'points' => 10,
        'recurrence_config' => [],
        'default_assignees' => [],
    ]);

    $response->assertSessionHasErrors('recurrence_config.day_of_week');
});

test('tentative/project ditolak sebagai task_type template (F-46/A2)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTemplateTestProject($admin);

    $response = $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        'title' => 'Task Biasa',
        'task_type' => 'tentative',
        'estimated_minutes' => 30,
        'points' => 5,
        'recurrence_config' => [],
        'default_assignees' => [],
    ]);

    $response->assertSessionHasErrors('task_type');
    expect(TaskTemplate::where('project_id', $project->id)->where('title', 'Task Biasa')->exists())->toBeFalse();
});

test('non-member sebagai default_assignee saat simpan ditolak (F-86/A3)', function () {
    $admin = User::factory()->admin()->create();
    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]); // bukan member project
    $project = createTemplateTestProject($admin);

    $response = $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        'title' => 'Template Invalid Assignee',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'recurrence_config' => [],
        'default_assignees' => [$outsider->id],
    ]);

    $response->assertSessionHasErrors('default_assignees.0');
    expect(TaskTemplate::where('project_id', $project->id)->where('title', 'Template Invalid Assignee')->exists())->toBeFalse();
});

test('member (tanpa permission task.manage) tidak bisa akses CRUD template (F-90)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createTemplateTestProject($admin, [$member->id]);

    $this->actingAs($member)->get(route('task-templates.index', $project->id))->assertForbidden();
    $this->actingAs($member)->post(route('task-templates.store', $project->id), [
        'title' => 'x', 'task_type' => 'daily', 'estimated_minutes' => 30, 'points' => 5,
        'recurrence_config' => [], 'default_assignees' => [],
    ])->assertForbidden();
});

test('edit template TIDAK mengubah instance yang sudah tergenerate (A6/F-46)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTemplateTestProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Judul Lama',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
    ]);

    // Simulasikan instance yang sudah lahir dari template ini SEBELUM diedit.
    $existingInstance = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_template_id' => $template->id,
        'task_status_id' => $todo->id,
        'title' => $template->title,
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'due_date' => now()->addDay(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->put(route('task-templates.update', [$project->id, $template->id]), [
        'title' => 'Judul Baru',
        'task_type' => 'daily',
        'estimated_minutes' => 999,
        'points' => 999,
        'recurrence_config' => [],
        'default_assignees' => [],
    ]);

    $response->assertRedirect(route('task-templates.index', $project->id));

    $existingInstance->refresh();
    expect($existingInstance->title)->toBe('Judul Lama');
    expect($existingInstance->estimated_minutes)->toBe(30);
    expect($existingInstance->points)->toBe(5);

    expect($template->fresh()->title)->toBe('Judul Baru');
});

test('toggle-active membalik is_active tanpa menyentuh instance (A5)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTemplateTestProject($admin);

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Template Toggle',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
    ]);

    $this->actingAs($admin)->patch(route('task-templates.toggle-active', [$project->id, $template->id]))->assertRedirect();
    expect($template->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch(route('task-templates.toggle-active', [$project->id, $template->id]))->assertRedirect();
    expect($template->fresh()->is_active)->toBeTrue();
});
