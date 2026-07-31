<?php

/**
 * ==========================================================
 * MODUL       : TaskShowTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi halaman detail task (F-82) — permission (F-29/A4) dan
 *               SANITASI HTML (F-82 A3). Bukti browser (Hari-7) membuktikan render
 *               visual (bold tampil bold), tapi TIDAK membuktikan <script> disaring
 *               — itu butuh assertion terhadap PROPS Inertia, bukan mata manusia.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::show()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test <script> adalah pagar XSS SATU-SATUNYA yang otomatis — kalau
 *               ini gagal diam-diam (mis. karena someone mengganti sanitizer),
 *               tidak ada lapisan lain yang menangkapnya sebelum produksi.
 * PERUBAHAN   : F-78/F-90 — diperbarui (bukan ditambal): prop halaman 'isAdmin'
 *               dihapus dari TaskController::show() (RBAC §D3, digantikan
 *               auth.permissions yang di-share GLOBAL lewat HandleInertiaRequests).
 *               Assertion di sini menyesuaikan ke `auth.permissions`, cakupan
 *               SETARA (masih membuktikan admin vs member dibedakan).
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

function createShowProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Show Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

test('a member can view the detail page of a task assigned to them, with sanitized rich text', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createShowProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Task detail test',
        'description' => '<p>Kerjakan <strong>laporan</strong> ini <script>alert(1)</script>segera.</p>',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 5,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
    $task->assignees()->sync([$member->id]);

    $response = $this->actingAs($member)->get(route('tasks.show', [$project, $task]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('tasks/show')
        ->where('auth.permissions', [])
        ->where('task.description_html', fn (?string $html) => str_contains($html, '<strong>laporan</strong>')
            && ! str_contains($html, '<script>')
            && ! str_contains($html, 'alert(1)'))
    );
});

test('a member who is not a member of the project gets a 404, not a 403, on task detail (F-82 A4)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $otherProject = createShowProject($admin);
    $todo = TaskStatus::where('project_id', $otherProject->id)->where('position', 0)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $otherProject->id,
        'task_status_id' => $todo->id,
        'title' => 'Task rahasia project lain',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 5,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($member)->get(route('tasks.show', [$otherProject, $task]));

    $response->assertNotFound();
});

test('admin can view any task detail page and has task.manage in shared permissions', function () {
    $admin = User::factory()->admin()->create();
    $project = createShowProject($admin);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Task admin view',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'points' => 5,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get(route('tasks.show', [$project, $task]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('auth.permissions', fn (Collection $permissions) => $permissions->contains('task.manage')));
});
