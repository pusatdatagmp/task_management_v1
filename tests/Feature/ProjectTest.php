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
use Database\Seeders\RolePermissionSeeder;

test('admin creating a project auto-generates exactly 4 default statuses', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('projects.store'), [
        'name' => 'Website Revamp',
        'description' => 'Redesign landing page',
        'owner_ids' => [$admin->id],
        'members' => [],
    ]);

    $response->assertRedirect(route('projects.index'));

    $project = Project::where('name', 'Website Revamp')->firstOrFail();

    expect(TaskStatus::where('project_id', $project->id)->count())->toBe(4);

    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->first();
    expect($done)->not->toBeNull()
        ->and($done->name)->toBe('DONE');
});

test('store project dengan owner_ids lebih dari 1: SEMUA tersimpan di project_owners, urutan pilih jadi posisi, elemen pertama jadi owner_id "utama" (2026-08-08)', function () {
    $admin = User::factory()->admin()->create();
    $secondManager = User::factory()->admin()->create(['organization_id' => $admin->organization_id]);

    // Urutan SENGAJA $secondManager dulu -> dia yang harus jadi "utama" (owner_id).
    $response = $this->actingAs($admin)->post(route('projects.store'), [
        'name' => 'Multi Owner Project',
        'owner_ids' => [$secondManager->id, $admin->id],
        'members' => [],
    ]);

    $response->assertRedirect(route('projects.index'));

    $project = Project::where('name', 'Multi Owner Project')->firstOrFail();

    expect($project->owner_id)->toBe($secondManager->id)
        ->and($project->owners()->orderBy('project_owners.position')->pluck('users.id')->all())->toBe([$secondManager->id, $admin->id])
        ->and($project->owners()->wherePivot('position', 0)->pluck('users.id')->first())->toBe($secondManager->id)
        // SEMUA owner otomatis ikut jadi member juga (bukan cuma yang utama).
        ->and($project->members()->pluck('users.id')->all())->toEqualCanonicalizing([$secondManager->id, $admin->id]);
});

test('update project: ganti urutan owner_ids memindahkan "utama" (owner_id) ke elemen pertama BARU (2026-08-08)', function () {
    $admin = User::factory()->admin()->create();
    $secondManager = User::factory()->admin()->create(['organization_id' => $admin->organization_id]);

    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Reorder Owner Test',
        'owner_id' => $admin->id,
    ]);
    $project->owners()->sync([$admin->id => ['position' => 0]]);

    $response = $this->actingAs($admin)->put(route('projects.update', $project), [
        'name' => $project->name,
        'owner_ids' => [$secondManager->id, $admin->id],
        'members' => [],
    ]);

    $response->assertRedirect(route('projects.index'));
    expect($project->fresh()->owner_id)->toBe($secondManager->id);
});

test('project yang dibuat LANGSUNG lewat Eloquent (project_owners kosong) tetap bisa disimpan ulang tanpa kehilangan owner (fallback owner_id, 2026-08-08)', function () {
    // KONTRAK: banyak test/helper lain di codebase bikin Project::create(['owner_id'=>..])
    // langsung, TIDAK lewat store() yang mengisi project_owners. Form Edit untuk
    // project semacam ini WAJIB tetap menampilkan owner_id lama sebagai owner
    // ter-centang (fallback), bukan checklist kosong yang bikin validasi "min:1" gagal.
    $admin = User::factory()->admin()->create();
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Direct Eloquent Project',
        'owner_id' => $admin->id,
    ]);

    expect($project->owners)->toBeEmpty(); // pivot BENAR-BENAR kosong, kondisi awal test ini.

    $editResponse = $this->actingAs($admin)->get(route('projects.edit', $project));
    $editResponse->assertInertia(fn ($page) => $page->where('ownerIds', [$admin->id]));

    // Simpan ulang TANPA mengubah owner sama sekali -- harus lolos (fallback aktif).
    $updateResponse = $this->actingAs($admin)->put(route('projects.update', $project), [
        'name' => $project->name,
        'owner_ids' => [$admin->id],
        'members' => [],
    ]);
    $updateResponse->assertRedirect(route('projects.index'));
    expect($project->fresh()->owner_id)->toBe($admin->id);
});

test('member biasa (tanpa project.manage) ditolak sebagai owner_id project baru (revisi 2026-08-07)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($admin)->post(route('projects.store'), [
        'name' => 'Owner Invalid',
        'owner_ids' => [$member->id],
        'members' => [],
    ]);

    $response->assertSessionHasErrors('owner_ids.0');
    expect(Project::where('name', 'Owner Invalid')->exists())->toBeFalse();
});

test('ProjectController::create() HANYA kirim owners ber-permission project.manage, bukan semua users (revisi 2026-08-07)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($admin)->get(route('projects.create'));

    $response->assertInertia(fn ($page) => $page->component('projects/create')
        ->where('owners', fn ($owners) => collect($owners)->pluck('id')->contains($admin->id)
            && ! collect($owners)->pluck('id')->contains($member->id))
        ->where('users', fn ($users) => collect($users)->pluck('id')->contains($member->id)));
});

test('dropdown Owner di form Project Baru TIDAK menawarkan member nonaktif walau punya project.manage (bug fix 2026-08-08)', function () {
    $admin = User::factory()->admin()->create();
    $inactiveManager = User::factory()->admin()->create(['organization_id' => $admin->organization_id, 'is_active' => false]);

    $response = $this->actingAs($admin)->get(route('projects.create'));

    $response->assertInertia(fn ($page) => $page->where('owners', fn ($owners) => collect($owners)->pluck('id')->contains($admin->id)
        && ! collect($owners)->pluck('id')->contains($inactiveManager->id)));
});

test('edit project: owner LAMA yang sekarang nonaktif tetap muncul di dropdown (nol kehilangan value tersimpan), tapi manager nonaktif LAIN tidak bisa dipilih baru (bug fix 2026-08-08)', function () {
    $admin = User::factory()->admin()->create();
    $inactiveOwner = User::factory()->admin()->create(['organization_id' => $admin->organization_id, 'is_active' => false]);
    $otherInactiveManager = User::factory()->admin()->create(['organization_id' => $admin->organization_id, 'is_active' => false]);

    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Owner Nonaktif Test',
        'owner_id' => $inactiveOwner->id,
    ]);

    $response = $this->actingAs($admin)->get(route('projects.edit', $project));

    $response->assertInertia(fn ($page) => $page->where('owners', fn ($owners) => collect($owners)->pluck('id')->contains($inactiveOwner->id)
        && ! collect($owners)->pluck('id')->contains($otherInactiveManager->id)));
});

test('checklist member di form Edit Project HANYA user is_active=true, tapi memberIds tetap lengkap termasuk yang nonaktif (bug fix 2026-08-08)', function () {
    // KONTRAK KRITIS: `users` (sumber checkbox) BOLEH difilter is_active, tapi
    // `memberIds` (state awal form, projects/edit.tsx) WAJIB tetap kirim SEMUA
    // member existing termasuk yang nonaktif -- kalau memberIds ikut difilter,
    // frontend akan mulai dengan array yang sudah kehilangan mereka, dan
    // member nonaktif itu BENAR-BENAR akan ke-unassign diam-diam saat disimpan.
    $admin = User::factory()->admin()->create();
    $activeMember = User::factory()->create(['organization_id' => $admin->organization_id, 'is_active' => true]);
    $inactiveMember = User::factory()->create(['organization_id' => $admin->organization_id, 'is_active' => false]);

    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Edit Member Nonaktif Test',
        'owner_id' => $admin->id,
    ]);
    $project->members()->sync([$admin->id, $activeMember->id, $inactiveMember->id]);

    $response = $this->actingAs($admin)->get(route('projects.edit', $project));

    $response->assertInertia(fn ($page) => $page
        ->where('users', fn ($users) => collect($users)->pluck('id')->contains($activeMember->id)
            && ! collect($users)->pluck('id')->contains($inactiveMember->id))
        ->where('memberIds', fn ($memberIds) => collect($memberIds)->contains($activeMember->id)
            && collect($memberIds)->contains($inactiveMember->id)));
});

test('checklist member di form Project Baru HANYA user is_active=true (revisi 2026-08-07)', function () {
    $admin = User::factory()->admin()->create();
    $activeMember = User::factory()->create(['organization_id' => $admin->organization_id, 'is_active' => true]);
    $inactiveMember = User::factory()->create(['organization_id' => $admin->organization_id, 'is_active' => false]);

    $response = $this->actingAs($admin)->get(route('projects.create'));

    $response->assertInertia(fn ($page) => $page->where('users', fn ($users) => collect($users)->pluck('id')->contains($activeMember->id)
        && ! collect($users)->pluck('id')->contains($inactiveMember->id)));
});

test('edit project: owner LAMA tetap lolos walau permission-nya sudah dicabut, owner BARU tetap wajib project.manage (revisi 2026-08-07)', function () {
    $admin = User::factory()->admin()->create();
    $formerManager = User::factory()->create(['organization_id' => $admin->organization_id]);
    $roleId = RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization)['admin']->id;
    $formerManager->update(['role_id' => $roleId]);

    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Owner Lama Test',
        'owner_id' => $formerManager->id,
    ]);

    // Cabut permission project.manage dari $formerManager (turunkan ke role member biasa).
    $memberRoleId = RolePermissionSeeder::seedSystemRolesForOrganization($admin->organization)['member']->id;
    $formerManager->update(['role_id' => $memberRoleId]);

    // owner TIDAK berubah (tetap $formerManager) -- WAJIB lolos walau dia sekarang member biasa.
    $unchanged = $this->actingAs($admin)->put(route('projects.update', $project), [
        'name' => 'Owner Lama Test (edited)',
        'owner_ids' => [$formerManager->id],
        'members' => [],
    ]);
    $unchanged->assertRedirect(route('projects.index'));
    expect($project->fresh()->name)->toBe('Owner Lama Test (edited)');

    // owner DIGANTI ke member biasa lain -- WAJIB ditolak.
    $otherMember = User::factory()->create(['organization_id' => $admin->organization_id]);
    $changed = $this->actingAs($admin)->put(route('projects.update', $project), [
        'name' => $project->name,
        'owner_ids' => [$otherMember->id],
        'members' => [],
    ]);
    $changed->assertSessionHasErrors('owner_ids.0');
});

test('member cannot create a project', function () {
    $member = User::factory()->create();

    $response = $this->actingAs($member)->post(route('projects.store'), [
        'name' => 'Should Fail',
        'owner_ids' => [$member->id],
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

test('project index HANYA tampilkan project aktif, project diarsipkan pindah ke projects.archived (revisi 2026-08-07)', function () {
    $admin = User::factory()->admin()->create();
    $active = Project::create(['organization_id' => $admin->organization_id, 'name' => 'Aktif', 'owner_id' => $admin->id]);
    $archived = Project::create(['organization_id' => $admin->organization_id, 'name' => 'Arsip', 'owner_id' => $admin->id, 'is_archived' => true]);

    $indexResponse = $this->actingAs($admin)->get(route('projects.index'));
    $indexResponse->assertInertia(fn ($page) => $page->component('projects/index')
        ->has('projects', 1)
        ->where('projects.0.id', $active->id));

    $archivedResponse = $this->actingAs($admin)->get(route('projects.archived'));
    $archivedResponse->assertInertia(fn ($page) => $page->component('projects/archive')
        ->has('projects', 1)
        ->where('projects.0.id', $archived->id));
});

test('member (tanpa project.manage) tidak bisa akses halaman arsip project (F-90)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($member)->get(route('projects.archived'))->assertForbidden();
});

test('restore project set is_archived=false, project muncul lagi di daftar aktif (revisi 2026-08-07)', function () {
    $admin = User::factory()->admin()->create();
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Mau Dipulihkan',
        'owner_id' => $admin->id,
        'is_archived' => true,
    ]);

    $response = $this->actingAs($admin)->patch(route('projects.restore', $project));

    $response->assertRedirect(route('projects.archived'));
    expect($project->fresh()->is_archived)->toBeFalse();

    $indexResponse = $this->actingAs($admin)->get(route('projects.index'));
    $indexResponse->assertInertia(fn ($page) => $page->has('projects', 1)->where('projects.0.id', $project->id));
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
        'owner_ids' => [$admin->id],
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
        'owner_ids' => [$admin->id],
        'members' => [],
    ]);

    $response->assertRedirect(route('projects.index'));
    expect($project->members()->pluck('users.id'))->not->toContain($member->id);
});
