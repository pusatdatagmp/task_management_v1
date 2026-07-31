<?php

/**
 * ==========================================================
 * MODUL       : V12FoundationTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi migration+model aditif v1.2 H2 (integrasi mockup v1.7) —
 *               priority_quadrant (F-122/F-126), checklist task+template (F-123/F-127),
 *               projects.goal/due_date (F-125), meetings+meeting_user (F-124). Bukti
 *               ADITIF murni: kolom lama (priority enum, tasks.due_date) TETAP UTUH,
 *               organization_id ter-scope benar (F-5/F-15) di SEMUA tabel baru.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : Task, TaskChecklistItem, TaskTemplate, TaskTemplateChecklistItem,
 *               Project, Meeting
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Ini SATU-SATUNYA pagar hari ini yang membuktikan migration aditif
 *               tidak diam-diam merusak skema lama — kalau lolos padahal kolom lama
 *               hilang/berubah, F-121 (ADD-DON'T-DELETE) sudah dilanggar tanpa ketahuan.
 * ==========================================================
 */

use App\Models\Meeting;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateChecklistItem;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

function createFoundationProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'V1.2 Foundation Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    TaskStatus::seedDefaults($project);

    return $project;
}

function createFoundationTask(Project $project, User $admin): Task
{
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Foundation task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
}

test('tasks.priority enum lama tetap ada berdampingan dgn priority_quadrant baru (F-121/F-122/F-126)', function () {
    $admin = User::factory()->admin()->create();
    $project = createFoundationProject($admin);
    $task = createFoundationTask($project, $admin);

    // fresh() WAJIB — Model::create() tidak menarik ulang DEFAULT kolom yang tidak
    // dikirim (in-memory attribute tetap null sampai dibaca ulang dari DB).
    expect($task->fresh()->priority)->toBe('normal'); // default enum lama, tidak disentuh

    $task->update(['priority_quadrant' => 'p1']);

    expect($task->fresh()->priority)->toBe('normal')
        ->and($task->fresh()->priority_quadrant)->toBe('p1');
});

test('checklist item tersimpan & terbaca lewat relasi Task::checklistItems (F-123)', function () {
    $admin = User::factory()->admin()->create();
    $project = createFoundationProject($admin);
    $task = createFoundationTask($project, $admin);

    $item = TaskChecklistItem::create([
        'organization_id' => $admin->organization_id,
        'task_id' => $task->id,
        'text' => 'Cek dokumen pendukung',
        'position' => 0,
    ]);

    // fresh() WAJIB — sama alasannya dengan test priority di atas.
    expect($item->fresh()->is_done)->toBeFalse() // default
        ->and($task->checklistItems)->toHaveCount(1)
        ->and($task->checklistItems->first()->id)->toBe($item->id)
        ->and($item->task->id)->toBe($task->id);
});

test('checklist template tersimpan & terbaca lewat relasi TaskTemplate::checklistItems (F-127)', function () {
    $admin = User::factory()->admin()->create();
    $project = createFoundationProject($admin);

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Template harian '.uniqid(),
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 2,
        'recurrence_config' => ['day_of_week' => 1],
        'default_assignees' => [$admin->id],
    ]);

    $item = TaskTemplateChecklistItem::create([
        'organization_id' => $admin->organization_id,
        'task_template_id' => $template->id,
        'text' => 'Pastikan laporan terkirim',
        'position' => 0,
    ]);

    expect($template->checklistItems)->toHaveCount(1)
        ->and($template->checklistItems->first()->id)->toBe($item->id)
        ->and($item->taskTemplate->id)->toBe($template->id);
});

test('project goal & due_date tersimpan, is_archived lama tidak terganggu (F-125)', function () {
    $admin = User::factory()->admin()->create();

    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Project dengan goal '.uniqid(),
        'owner_id' => $admin->id,
        'goal' => 'Rilis beta stabil',
        'due_date' => '2026-08-15',
    ]);

    expect($project->fresh()->goal)->toBe('Rilis beta stabil')
        ->and($project->fresh()->due_date->format('Y-m-d'))->toBe('2026-08-15')
        ->and($project->fresh()->is_archived)->toBeFalse();
});

test('projects table TIDAK punya kolom status tersimpan (F-125 — diturunkan, bukan kolom)', function () {
    expect(Schema::hasColumn('projects', 'status'))->toBeFalse();
});

test('meeting dibuat admin, invite peserta lewat meeting_user, project opsional (F-124)', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);

    $meeting = Meeting::create([
        'organization_id' => $admin->organization_id,
        'project_id' => null, // F-124: rapat lintas-proyek, sengaja tanpa project
        'title' => 'Sinkronisasi mingguan',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHour(),
        'created_by' => $admin->id,
    ]);

    $meeting->participants()->attach([$memberA->id, $memberB->id]);

    expect($meeting->project_id)->toBeNull()
        ->and($meeting->creator->id)->toBe($admin->id)
        ->and($meeting->fresh()->participants)->toHaveCount(2)
        ->and($meeting->fresh()->participants->pluck('id')->all())
        ->toEqualCanonicalizing([$memberA->id, $memberB->id]);
});

test('meeting BISA terikat ke project (F-124 — opsional, bukan wajib kosong)', function () {
    $admin = User::factory()->admin()->create();
    $project = createFoundationProject($admin);

    $meeting = Meeting::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Kickoff proyek',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHour(),
        'created_by' => $admin->id,
    ]);

    expect($meeting->fresh()->project->id)->toBe($project->id);
});

test('organization_id ter-scope otomatis di semua tabel baru (F-5/F-15)', function () {
    // KEDUA admin dibuat SEBELUM actingAs apa pun. WORKAROUND: RolePermissionSeeder::
    // seedSystemRolesForOrganization() (dipanggil UserFactory::admin()/configure())
    // pakai Role::firstOrCreate() — Role JUGA pakai BelongsToOrganization, jadi
    // query firstOrCreate-nya kena OrganizationScope juga. Kalau ada user org LAIN
    // sedang actingAs saat factory ini jalan, scope memaksa organization_id=org-aktif
    // ke SELECT firstOrCreate padahal kondisi eksplisitnya organization_id=org-baru —
    // kombinasi mustahil, SELECT selalu nihil, dua pemanggil (configure()+admin())
    // sama-sama INSERT baris yang sama -> duplicate key. Ini bug laten pre-existing
    // di Role/OrganizationScope (bukan hasil migration hari ini), DILAPORKAN ke
    // Jarvis, BUKAN diperbaiki di sini (di luar scope H2). Test ini menghindarinya
    // dengan membuat semua user SEBELUM ada sesi login mana pun.
    $adminOrgA = User::factory()->admin()->create();
    $adminOrgB = User::factory()->admin()->create(['organization_id' => Organization::factory()->create()->id]);

    $projectA = createFoundationProject($adminOrgA);
    $taskA = createFoundationTask($projectA, $adminOrgA);

    // actingAs WAJIB SEBELUM create() — BelongsToOrganization::bootBelongsToOrganization()
    // hanya auto-isi organization_id kalau Auth::hasUser() true saat event `creating`.
    $this->actingAs($adminOrgA);

    $checklistItem = TaskChecklistItem::create([
        'task_id' => $taskA->id,
        'text' => 'Item org A',
        'position' => 0,
    ]);
    $meeting = Meeting::create([
        'title' => 'Meeting org A',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHour(),
        'created_by' => $adminOrgA->id,
    ]);

    expect($checklistItem->organization_id)->toBe($adminOrgA->organization_id)
        ->and($meeting->organization_id)->toBe($adminOrgA->organization_id);

    // Organisasi lain TIDAK BOLEH melihat baris organisasi A (OrganizationScope, F-15).
    $this->actingAs($adminOrgB);

    expect(TaskChecklistItem::whereKey($checklistItem->id)->exists())->toBeFalse()
        ->and(Meeting::whereKey($meeting->id)->exists())->toBeFalse();
});

test('migration aditif TIDAK mengubah tipe/keberadaan kolom lama (F-121 — grep skema)', function () {
    expect(Schema::hasColumn('tasks', 'priority'))->toBeTrue()
        ->and(Schema::hasColumn('tasks', 'due_date'))->toBeTrue()
        ->and(Schema::hasColumn('tasks', 'priority_quadrant'))->toBeTrue()
        ->and(Schema::hasColumn('projects', 'is_archived'))->toBeTrue()
        ->and(Schema::hasColumn('projects', 'goal'))->toBeTrue()
        ->and(Schema::hasColumn('projects', 'due_date'))->toBeTrue();

    $dueDateType = Schema::getColumnType('tasks', 'due_date');
    expect($dueDateType)->toBe('datetime'); // F-31/F-47 — tidak boleh berubah jadi date-only
});
