<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// F-184: otorisasi channel komentar realtime per-task -- ATURAN INI WAJIB SAMA
// PERSIS dengan TaskController::show() (project.viewAll ATAU member proyek),
// bukan aturan baru. Task::find() (BUKAN findOrFail()) sengaja -- kalau task_id
// tidak ada/beda organisasi (OrganizationScope aktif normal di sini karena
// callback ini jalan di request HTTP terautentikasi biasa, bukan queued job),
// kembalikan false (gagal diam-diam), bukan lempar 404/500 di endpoint auth.
Broadcast::channel('task.{taskId}', function (User $user, int $taskId) {
    $task = Task::with('project')->find($taskId);

    if (! $task) {
        return false;
    }

    return $user->can('project.viewAll') || $task->project->members()->whereKey($user->id)->exists();
});
