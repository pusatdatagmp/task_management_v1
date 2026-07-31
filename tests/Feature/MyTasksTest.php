<?php

/**
 * ==========================================================
 * MODUL       : MyTasksTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi halaman "Task Saya" (Hari-5 §D) — lintas project, HANYA
 *               task yang di-assign ke user login, dikelompokkan dengan benar,
 *               task selesai dikeluarkan (D6).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskController::myTasks()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau task orang lain ikut nongol di sini, member bisa salah
 *               kerjakan task yang bukan tanggung jawabnya.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Carbon\Carbon;

function createMyTasksProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'My Tasks Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    TaskStatus::seedDefaults($project);

    return $project;
}

test('only tasks assigned to the logged-in user appear on My Tasks', function () {
    // SUMBER: anchor ke Rabu minggu berjalan (relatif, bukan tanggal hardcode)
    // supaya due_date +3 hari SELALU jatuh di "this_week" apa pun hari sungguhan
    // saat test dijalankan — sama seperti test grouping di bawah.
    $this->travelTo(Carbon::now()->startOfWeek()->addDays(2)->setTime(9, 0));

    $admin = User::factory()->admin()->create();
    $project = createMyTasksProject($admin);
    $me = User::factory()->create(['organization_id' => $admin->organization_id]);
    $someoneElse = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project->members()->sync([$admin->id, $me->id, $someoneElse->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $myTask = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Task saya',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addDays(3),
        'created_by' => $admin->id,
    ]);
    $myTask->assignees()->sync([$me->id]);

    $othersTask = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Task orang lain',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addDays(3),
        'created_by' => $admin->id,
    ]);
    $othersTask->assignees()->sync([$someoneElse->id]);

    $response = $this->actingAs($me)->get(route('tasks.my'));

    $response->assertInertia(fn ($page) => $page
        ->component('tasks/my-tasks')
        ->has('groups.this_week', 1)
        ->where('groups.this_week.0.id', $myTask->id));
});

test('completed tasks are excluded from My Tasks (D6)', function () {
    $admin = User::factory()->admin()->create();
    $project = createMyTasksProject($admin);
    $me = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project->members()->sync([$admin->id, $me->id]);
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    $completedTask = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $done->id,
        'title' => 'Sudah selesai',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addDays(3),
        'created_by' => $admin->id,
    ]);
    $completedTask->assignees()->sync([$me->id]);

    $response = $this->actingAs($me)->get(route('tasks.my'));

    $response->assertInertia(fn ($page) => $page
        ->has('groups.overdue', 0)
        ->has('groups.today', 0)
        ->has('groups.this_week', 0)
        ->has('groups.later', 0));
});

test('tasks are grouped correctly: overdue, today, this week, later', function () {
    // SUMBER: "minggu ini" bergantung HARI SAAT TEST DIJALANKAN — kalau real
    // clock kebetulan Sabtu/Minggu, offset tetap (mis. +3 hari) bisa jatuh di
    // luar endOfWeek() dan test jadi flaky (pelajaran Hari-2: seeder subDays(5)
    // tanpa anchor hari kerja). travelTo() ke Rabu MINGGU INI (dihitung relatif
    // dari now() asli, bukan tanggal hardcode) supaya semua offset di bawah
    // punya ruang aman di kedua sisi, apa pun hari sungguhan saat test jalan.
    $anchor = Carbon::now()->startOfWeek()->addDays(2)->setTime(9, 0);
    $this->travelTo($anchor);

    $admin = User::factory()->admin()->create();
    $project = createMyTasksProject($admin);
    $me = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project->members()->sync([$admin->id, $me->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $make = function (string $title, $dueDate) use ($project, $admin, $todo, $me) {
        $task = Task::create([
            'organization_id' => $admin->organization_id,
            'project_id' => $project->id,
            'task_status_id' => $todo->id,
            'title' => $title,
            'task_type' => 'tentative',
            'estimated_minutes' => 60,
            'due_date' => $dueDate,
            'created_by' => $admin->id,
        ]);
        $task->assignees()->sync([$me->id]);

        return $task;
    };

    $overdueTask = $make('Terlambat', now()->subDays(2));
    $todayTask = $make('Hari ini', now()->addHours(2));
    $weekTask = $make('Minggu ini', now()->addDays(3));
    $laterTask = $make('Nanti', now()->addWeeks(3));

    $response = $this->actingAs($me)->get(route('tasks.my'));

    $response->assertInertia(fn ($page) => $page
        ->has('groups.overdue', 1)->where('groups.overdue.0.id', $overdueTask->id)
        ->has('groups.today', 1)->where('groups.today.0.id', $todayTask->id)
        ->has('groups.this_week', 1)->where('groups.this_week.0.id', $weekTask->id)
        ->has('groups.later', 1)->where('groups.later.0.id', $laterTask->id));
});
