<?php

/**
 * ==========================================================
 * MODUL       : TaskTemplateTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi CRUD blueprint template recurring (F-46, v0.8 H4 Fase A) —
 *               default_assignees tervalidasi member project SAAT SIMPAN (F-86), edit
 *               tidak menyentuh instance yang sudah lahir (A6), gating permission
 *               task.manage (F-90).
 *               Revisi 2026-08-07 (permintaan Boss): dropdown `task_type`
 *               (daily/weekly/monthly) & `recurrence_config` DICABUT dari
 *               form/validasi -- jadwal SEPENUHNYA dari kolom Automation Engine
 *               (anchor_strategy dkk, AE-2b). Test lama yang menguji perilaku
 *               task_type/recurrence_config di level FORM (validasi Rule::in,
 *               required_if) DIHAPUS -- perilaku itu SENGAJA tidak ada lagi,
 *               bukan bug (F-78). Test AE-2b (anchor_strategy/interval/anchor_config)
 *               yang setara SUDAH ADA di tests/Feature/Automation/AutomationConfigFormTest.php,
 *               jadi cakupan validasi jadwal tetap terjaga di sana.
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

test('admin bisa buat template dengan interval custom, schedule_label mencerminkan konfigurasi (AE-2b)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTemplateTestProject($admin);

    $response = $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        'title' => 'Laporan Tiap 3 Hari',
        'estimated_minutes' => 30,
        'points' => 5,
        'default_assignees' => [],
        'anchor_strategy' => 'time_based', 'interval_value' => 3, 'interval_unit' => 'day',
    ]);

    $response->assertRedirect(route('task-templates.index', $project->id));
    $template = TaskTemplate::where('project_id', $project->id)->where('title', 'Laporan Tiap 3 Hari')->firstOrFail();
    expect($template->schedule_label)->toBe('Tiap 3 hari');
});

test('admin bisa buat template hari-tetap (calendar_anchored) dengan day_of_week', function () {
    $admin = User::factory()->admin()->create();
    $project = createTemplateTestProject($admin);

    $response = $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        'title' => 'Rapat Mingguan',
        'estimated_minutes' => 60,
        'points' => 10,
        'default_assignees' => [],
        'anchor_strategy' => 'calendar_anchored', 'anchor_day_type' => 'week', 'anchor_config' => ['day_of_week' => 3],
    ]);

    $response->assertRedirect(route('task-templates.index', $project->id));
    $template = TaskTemplate::where('project_id', $project->id)->where('title', 'Rapat Mingguan')->firstOrFail();
    expect($template->anchor_config)->toBe(['day_of_week' => 3])
        ->and($template->schedule_label)->toBe('Tiap Rabu');
});

test('non-member sebagai default_assignee saat simpan ditolak (F-86/A3)', function () {
    $admin = User::factory()->admin()->create();
    $outsider = User::factory()->create(['organization_id' => $admin->organization_id]); // bukan member project
    $project = createTemplateTestProject($admin);

    $response = $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        'title' => 'Template Invalid Assignee',
        'estimated_minutes' => 30,
        'points' => 5,
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
        'title' => 'x', 'estimated_minutes' => 30, 'points' => 5, 'default_assignees' => [],
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
        // Dead-tapi-aman (F-162 rollback) -- lihat komentar TaskTemplateController::store().
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
        'task_type' => 'Tiap hari',
        'estimated_minutes' => 30,
        'points' => 5,
        'due_date' => now()->addDay(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->put(route('task-templates.update', [$project->id, $template->id]), [
        'title' => 'Judul Baru',
        'estimated_minutes' => 999,
        'points' => 999,
        'default_assignees' => [],
        'anchor_strategy' => 'time_based', 'interval_value' => 1, 'interval_unit' => 'day',
    ]);

    $response->assertRedirect(route('task-templates.index', $project->id));

    $existingInstance->refresh();
    expect($existingInstance->title)->toBe('Judul Lama');
    expect($existingInstance->estimated_minutes)->toBe(30);
    expect($existingInstance->points)->toBe(5);
    expect($existingInstance->task_type)->toBe('Tiap hari');

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
