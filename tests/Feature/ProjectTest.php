<?php

/**
 * ==========================================================
 * MODUL       : ProjectTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi CRUD Project Hari-3 — auto-generate 4 status default
 *               (C3), filter admin-lihat-semua vs member-hanya-assigned (§6),
 *               archive bukan delete (F-16), dan sync member ter-log (F-71).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : ProjectController, Project, TaskStatus, ActivityLog
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "4 status default" adalah gerbang C3 — project tanpa status
 *               adalah project rusak, task tidak akan pernah bisa dibuat Hari-4.
 * ==========================================================
 */

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

test('admin creating a project auto-generates exactly 4 default statuses', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('projects.store'), [
        'name' => 'Website Revamp',
        'description' => 'Redesign landing page',
        'owner_id' => $admin->id,
        'members' => [],
    ]);

    $response->assertRedirect(route('projects.index'));

    $project = Project::where('name', 'Website Revamp')->firstOrFail();

    expect(TaskStatus::where('project_id', $project->id)->count())->toBe(4);

    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->first();
    expect($done)->not->toBeNull()
        ->and($done->name)->toBe('DONE');
});

test('member cannot create a project', function () {
    $member = User::factory()->create();

    $response = $this->actingAs($member)->post(route('projects.store'), [
        'name' => 'Should Fail',
        'owner_id' => $member->id,
    ]);

    $response->assertForbidden();
    expect(Project::where('name', 'Should Fail')->exists())->toBeFalse();
});

test('member only sees projects they are assigned to, admin sees all', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $otherMember = User::factory()->create(['organization_id' => $admin->organization_id]);

    $assigned = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Assigned Project',
        'owner_id' => $admin->id,
    ]);
    $assigned->members()->attach($member->id);

    $notAssigned = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Not Assigned',
        'owner_id' => $admin->id,
    ]);
    $notAssigned->members()->attach($otherMember->id);

    $memberResponse = $this->actingAs($member)->get(route('projects.index'));
    $memberResponse->assertInertia(fn ($page) => $page->component('projects/index')->has('projects', 1));

    $adminResponse = $this->actingAs($admin)->get(route('projects.index'));
    $adminResponse->assertInertia(fn ($page) => $page->component('projects/index')->has('projects', 2));
});

test('archiving a project sets is_archived=true, never deletes (F-16)', function () {
    $admin = User::factory()->admin()->create();
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'To Archive',
        'owner_id' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->patch(route('projects.archive', $project));

    $response->assertRedirect(route('projects.index'));
    expect($project->fresh()->is_archived)->toBeTrue()
        ->and(Project::find($project->id))->not->toBeNull();
});

test('syncing project members is logged via ProjectUserObserver (F-71)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Sync Log Test',
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$member->id]);

    // SUMBER: cek isi JSON di PHP (bukan query JSON path DB) supaya tidak bergantung
    // pada dukungan json1 di driver sqlite (test) vs mysql (produksi).
    $log = ActivityLog::where('subject_type', Project::class)
        ->where('subject_id', $project->id)
        ->where('event', 'assigned')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['new']['user_id'] ?? null)->toBe($member->id);
});

test('unassigning a member with an active (is_work_state) task is rejected (F-87)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'F-87 Guard Test',
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);
    $project->members()->attach([$admin->id, $member->id]);

    $workState = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $workState->id,
        'title' => 'Sedang dikerjakan',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
    $task->assignees()->attach($member->id);

    // Kirim members TANPA $member -> mencoba men-drop dia dari project.
    $response = $this->actingAs($admin)->put(route('projects.update', $project), [
        'name' => $project->name,
        'description' => $project->description,
        'owner_id' => $admin->id,
        'members' => [],
    ]);

    $response->assertSessionHasErrors('members');
    expect($project->members()->pluck('users.id'))->toContain($member->id);
});

test('unassigning a member without active tasks succeeds (F-87)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'F-87 Guard Test Lolos',
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);
    $project->members()->attach([$admin->id, $member->id]);

    $response = $this->actingAs($admin)->put(route('projects.update', $project), [
        'name' => $project->name,
        'description' => $project->description,
        'owner_id' => $admin->id,
        'members' => [],
    ]);

    $response->assertRedirect(route('projects.index'));
    expect($project->members()->pluck('users.id'))->not->toContain($member->id);
});
