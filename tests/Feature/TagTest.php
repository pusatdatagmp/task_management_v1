<?php

/**
 * ==========================================================
 * MODUL       : TagTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi CRUD Tag (permintaan Boss 2026-08-26) — gate reuse
 *               settings.manage, unique nama per organisasi (F-5), hapus tag
 *               OTOMATIS melepas dari semua task pemakainya (keputusan Boss,
 *               cascadeOnDelete migration task_tag — BUKAN ditolak seperti
 *               TaskStatus::destroy()). Termasuk sync tags saat create/update
 *               Task DAN penolakan tag lintas organisasi (F-15, cegah IDOR).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TagController, TaskController::store()/update()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test cascade-delete adalah pagar keputusan Boss 2026-08-26 --
 *               kalau bolong, tag yang dihapus bisa nyangkut di task lama.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

function createTagTestProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Tag Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

test('admin (settings.manage) can create a tag', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('tags.store'), [
        'name' => 'Urgent',
        'color' => '#ff0000',
    ])->assertRedirect();

    $tag = Tag::where('organization_id', $admin->organization_id)->where('name', 'Urgent')->firstOrFail();
    expect($tag->color)->toBe('#ff0000');
});

test('member without settings.manage cannot create a tag', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->post(route('tags.store'), [
        'name' => 'Urgent',
        'color' => '#ff0000',
    ])->assertForbidden();

    expect(Tag::where('name', 'Urgent')->exists())->toBeFalse();
});

test('tag name must be unique within the same organization (F-5)', function () {
    $admin = User::factory()->admin()->create();
    Tag::create(['organization_id' => $admin->organization_id, 'name' => 'Bug', 'color' => '#111111']);

    $this->actingAs($admin)->post(route('tags.store'), [
        'name' => 'Bug',
        'color' => '#222222',
    ])->assertSessionHasErrors('name');
});

test('same tag name is allowed across different organizations (F-5)', function () {
    $adminOrgA = User::factory()->admin()->create();
    $adminOrgB = User::factory()->admin()->create();
    Tag::create(['organization_id' => $adminOrgA->organization_id, 'name' => 'Bug', 'color' => '#111111']);

    $this->actingAs($adminOrgB)->post(route('tags.store'), [
        'name' => 'Bug',
        'color' => '#222222',
    ])->assertRedirect();

    expect(Tag::where('organization_id', $adminOrgB->organization_id)->where('name', 'Bug')->exists())->toBeTrue();
});

test('admin can update a tag', function () {
    $admin = User::factory()->admin()->create();
    $tag = Tag::create(['organization_id' => $admin->organization_id, 'name' => 'Old', 'color' => '#111111']);

    $this->actingAs($admin)->put(route('tags.update', $tag), [
        'name' => 'New',
        'color' => '#222222',
    ])->assertRedirect();

    expect($tag->fresh())->name->toBe('New')->color->toBe('#222222');
});

test('deleting a tag automatically detaches it from every task that used it (keputusan Boss 2026-08-26)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTagTestProject($admin);
    $tag = Tag::create(['organization_id' => $admin->organization_id, 'name' => 'Urgent', 'color' => '#ff0000']);

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => TaskStatus::where('project_id', $project->id)->orderBy('position')->value('id'),
        'title' => 'Perlu tag',
        'task_type' => 'tentative',
        'priority' => 'normal',
        'points' => 1,
        'estimated_minutes' => 30,
        'due_date' => now()->addDay(),
        'created_by' => $admin->id,
    ]);
    $task->tags()->attach($tag->id);

    $this->actingAs($admin)->delete(route('tags.destroy', $tag))->assertRedirect();

    expect(Tag::find($tag->id))->toBeNull()
        ->and($task->fresh()->tags)->toHaveCount(0);
});

test('creating a task with tags syncs them via Task::tags()', function () {
    $admin = User::factory()->admin()->create();
    $project = createTagTestProject($admin);
    $tagA = Tag::create(['organization_id' => $admin->organization_id, 'name' => 'A', 'color' => '#111111']);
    $tagB = Tag::create(['organization_id' => $admin->organization_id, 'name' => 'B', 'color' => '#222222']);

    $this->actingAs($admin)->post(route('tasks.store', $project->id), [
        'title' => 'Task dengan tag',
        'task_type' => 'tentative',
        'estimated_minutes' => 30,
        'points' => 1,
        'due_date' => now()->addDay()->format('Y-m-d H:i:s'),
        'tags' => [$tagA->id, $tagB->id],
    ])->assertRedirect();

    $task = Task::where('title', 'Task dengan tag')->firstOrFail();
    expect($task->tags()->pluck('tags.id')->sort()->values()->all())->toBe([$tagA->id, $tagB->id]);
});

test('a tag belonging to another organization is rejected when creating a task (F-15, cegah IDOR)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTagTestProject($admin);
    $otherAdmin = User::factory()->admin()->create();
    $foreignTag = Tag::create(['organization_id' => $otherAdmin->organization_id, 'name' => 'Bukan Punyaku', 'color' => '#111111']);

    $this->actingAs($admin)->post(route('tasks.store', $project->id), [
        'title' => 'Task nakal',
        'task_type' => 'tentative',
        'estimated_minutes' => 30,
        'points' => 1,
        'due_date' => now()->addDay()->format('Y-m-d H:i:s'),
        'tags' => [$foreignTag->id],
    ])->assertSessionHasErrors('tags.0');

    expect(Task::where('title', 'Task nakal')->exists())->toBeFalse();
});

test('updating a task can replace its tags entirely (sync, not append)', function () {
    $admin = User::factory()->admin()->create();
    $project = createTagTestProject($admin);
    $tagA = Tag::create(['organization_id' => $admin->organization_id, 'name' => 'A', 'color' => '#111111']);
    $tagB = Tag::create(['organization_id' => $admin->organization_id, 'name' => 'B', 'color' => '#222222']);

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => TaskStatus::where('project_id', $project->id)->orderBy('position')->value('id'),
        'title' => 'Task edit tag',
        'task_type' => 'tentative',
        'priority' => 'normal',
        'points' => 1,
        'estimated_minutes' => 30,
        'due_date' => now()->addDay(),
        'created_by' => $admin->id,
    ]);
    $task->tags()->attach($tagA->id);

    $this->actingAs($admin)->put(route('tasks.update', [$project->id, $task->id]), [
        'title' => 'Task edit tag',
        'task_type' => 'tentative',
        'estimated_minutes' => 30,
        'points' => 1,
        'due_date' => now()->addDay()->format('Y-m-d H:i:s'),
        'tags' => [$tagB->id],
    ])->assertRedirect();

    expect($task->fresh()->tags()->pluck('tags.id')->all())->toBe([$tagB->id]);
});
