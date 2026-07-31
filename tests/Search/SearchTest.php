<?php

/**
 * ==========================================================
 * MODUL       : SearchTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi search FULLTEXT (F-7) — description_plain membersihkan
 *               HTML dari index (F-79), permission filter (F-34), dan pesan jelas
 *               untuk kata < 3 huruf (B9, bukan LIKE fallback).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::search()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "strong" adalah pagar F-79 — kalau index masih dari description
 *               mentah (bukan description_plain), test ini akan gagal karena tag HTML
 *               ikut jadi kata kunci yang cocok.
 *               F-83/C4 — file ini SENGAJA di tests/Search/ (BUKAN tests/Feature/):
 *               pakai DatabaseMigrations (tanpa transaction), bukan RefreshDatabase,
 *               supaya FULLTEXT index InnoDB (butuh commit) melihat data test. Lihat
 *               tests/Pest.php dan phpunit.xml testsuite "Search".
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

function createSearchProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Search Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createSearchTask(Project $project, User $admin, array $overrides = []): Task
{
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Search task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
        ...$overrides,
    ]);
}

test('searching a word in the title finds the task', function () {
    $admin = User::factory()->admin()->create();
    $project = createSearchProject($admin);
    $task = createSearchTask($project, $admin, ['title' => 'Laporan Bulanan Keuangan']);

    $response = $this->actingAs($admin)->getJson(route('tasks.search', ['q' => 'laporan']));

    $response->assertOk();
    expect(collect($response->json('results'))->pluck('id'))->toContain($task->id);
});

test('searching a word inside a rich text description finds the task via description_plain (F-79)', function () {
    $admin = User::factory()->admin()->create();
    $project = createSearchProject($admin);
    $task = createSearchTask($project, $admin, [
        'title' => 'Task lain',
        'description' => '<p>Kerjakan <strong>anggaran</strong> tahunan.</p>',
    ]);

    $response = $this->actingAs($admin)->getJson(route('tasks.search', ['q' => 'anggaran']));

    $response->assertOk();
    expect(collect($response->json('results'))->pluck('id'))->toContain($task->id);
});

test('searching "strong" does not return a task that is merely bold-formatted (F-79)', function () {
    $admin = User::factory()->admin()->create();
    $project = createSearchProject($admin);
    createSearchTask($project, $admin, [
        'title' => 'Task cetak tebal',
        'description' => '<p>Kerjakan <strong>laporan</strong> ini.</p>',
    ]);

    $response = $this->actingAs($admin)->getJson(route('tasks.search', ['q' => 'strong']));

    $response->assertOk();
    expect($response->json('results'))->toBeEmpty();
});

test('a member cannot find a task from a project they are not a member of (F-34)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $otherProject = createSearchProject($admin);
    $secretTask = createSearchTask($otherProject, $admin, ['title' => 'Rahasia proyek lain']);

    $response = $this->actingAs($member)->getJson(route('tasks.search', ['q' => 'rahasia']));

    $response->assertOk();
    expect(collect($response->json('results'))->pluck('id'))->not->toContain($secretTask->id);
});

test('a member CAN find a task from a project they are a member of (F-34)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSearchProject($admin, [$member->id]);
    $task = createSearchTask($project, $admin, ['title' => 'Tugas kolaborasi tim']);

    $response = $this->actingAs($member)->getJson(route('tasks.search', ['q' => 'kolaborasi']));

    $response->assertOk();
    expect(collect($response->json('results'))->pluck('id'))->toContain($task->id);
});

test('a query shorter than 3 characters returns a clear message instead of results or an error (B9)', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->getJson(route('tasks.search', ['q' => 'ab']));

    $response->assertOk();
    expect($response->json('message'))->toBe('Kata pencarian minimal 3 huruf.')
        ->and($response->json('results'))->toBeEmpty();
});
