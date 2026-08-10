<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeadlineExtensionController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TaskTemplateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;

// F-90/RBAC §D — blanket middleware 'admin' PENSIUN dari file ini (D4: dihapus
// total, lihat bootstrap/app.php). Tiap grup digerbangi permission KONKRET lewat
// middleware bawaan Laravel `can:xxx` (baca lewat Gate::before ->
// User::hasPermission(), lihat AppServiceProvider) — bukan "boleh semua kalau
// admin", supaya role CUSTOM (mis. "Supervisor" dengan cuma task.approve) bisa
// diberi akses granular tanpa jadi admin penuh.
//
// F-76: scopeBindings() tetap dipasang per grup yang punya {project}/{task}/dst
// bersarang di URL — {taskStatus}/{task} WAJIB anak dari {project} di URL yang
// sama, supaya /projects/1/statuses/99 tidak bisa mengedit status project lain.
Route::middleware(['auth', 'can:workschedule.manage'])->group(function () {
    Route::get('pengaturan/jam-kerja', [WorkScheduleController::class, 'index'])->name('work-schedules.index');
    Route::post('pengaturan/jam-kerja', [WorkScheduleController::class, 'store'])->name('work-schedules.store');

    // F-43 (HARDEN Fase D) — reuse permission workschedule.manage (setara, sama-sama
    // "kelola konfigurasi jendela kerja organisasi"), tidak perlu permission baru.
    Route::get('pengaturan/hari-libur', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('pengaturan/hari-libur', [HolidayController::class, 'store'])->name('holidays.store');
    Route::put('pengaturan/hari-libur/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
    Route::delete('pengaturan/hari-libur/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
});

// v0.8 H2/H3 (F-52/F-95) — dashboard tim, permission dashboard.view (admin saja,
// lihat RolePermissionSeeder). 'dashboard' = halaman (Inertia, H3), 'dashboard/summary'
// = JSON data mentah (H2, dipertahankan — endpoint terpisah, pola sama TaskController::search()).
// v1.2 H3 Fase A (F-121 aditif) — 'dashboard/command-center' = JSON widget baru
// (donut/progress/kategori/heatmap/top10/recent/workload), permission SAMA
// dashboard.view (nol permission baru — F-90, ini cuma agregasi tampilan lain
// dari resource yang sama).
Route::middleware(['auth', 'can:dashboard.view'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
    Route::get('dashboard/command-center', [DashboardController::class, 'commandCenter'])->name('dashboard.command-center');
    // v1.2 H4 (F-109/F-121): halaman Inertia baru, URI 'dashboard/overview' -- SENGAJA
    // beda dari 'dashboard' (dipakai test assertInertia component('dashboard'), F-121
    // tak boleh diregres) dan dari 'dashboard/command-center' (JSON, sudah dites 12
    // kasus). Nol permission baru -- gerbang SAMA dashboard.view (F-90).
    Route::get('dashboard/overview', [DashboardController::class, 'commandCenterPage'])->name('dashboard.overview');
});

// v1.2/v1.5 (F-134/F-135) — Leaderboard MANAGEMENT-ONLY. Permission BARU
// leaderboard.view, nol pemegang default (TERMASUK admin, lihat
// RolePermissionSeeder::catalog() 'default_admin' => false) -- Boss assign
// manual per role lewat UI Role Management yang SUDAH ADA. Skor PROVISIONAL
// sampai v1.5 (F-2).
Route::middleware(['auth', 'can:leaderboard.view'])->group(function () {
    Route::get('leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');
});

// v1.2 H7b (F-140/F-144/F-139) — "Semua Tugas": flat lintas SEMUA project, admin
// oversight. Permission project.viewAll (SAMA dipakai TaskController::index() untuk
// bedakan "lihat semua project" vs member biasa, F-90) — bukan permission baru.
// Flat, TIDAK butuh scopeBindings ({project}/{task} tidak ada di URL ini).
Route::middleware(['auth', 'can:project.viewAll'])->group(function () {
    Route::get('tasks', [TaskController::class, 'all'])->name('tasks.all');
});

// v1.2 H7b (F-140/F-144) — "Tugas Berulang": flat lintas SEMUA project, listing
// murni (CRUD tetap project-scoped, F-46 utuh). Permission task.manage SAMA
// dengan CRUD template biasa (routes/admin.php grup task-templates.* di bawah).
Route::middleware(['auth', 'can:task.manage'])->group(function () {
    Route::get('task-templates', [TaskTemplateController::class, 'allProjects'])->name('task-templates.all');
});

// v1.0 H4 (F-116) — log aktivitas GLOBAL lintas project, permission activity.view
// (admin default, assignable ke role lain lewat UI Role Management, F-90). BUKAN
// F-95 membership — ini data pengawasan, beda dari timeline per-task (TaskController::show()).
// READ-ONLY MUTLAK — index() satu-satunya method, tidak ada route lain sama sekali.
Route::middleware(['auth', 'can:activity.view'])->group(function () {
    Route::get('pengaturan/activity-log', [ActivityLogController::class, 'index'])->name('activity-logs.index');
});

// v1.2 DS-2 (F-142) — halaman "Setelan" org-level, permission settings.manage
// BARU (default_admin TRUE, beda dari leaderboard.view yang opt-in). POST
// (bukan PATCH) untuk updateBranding() -- form membawa file logo opsional,
// PHP tidak parse body multipart di method PATCH/PUT tanpa method-spoofing.
Route::middleware(['auth', 'can:settings.manage'])->group(function () {
    Route::get('pengaturan/setelan', [SettingsController::class, 'edit'])->name('settings.index');
    Route::post('pengaturan/setelan/branding', [SettingsController::class, 'updateBranding'])->name('settings.branding.update');
    // v1.2 DS-3 (F-143) -- tab Tema, permission SAMA settings.manage (reuse DS-2,
    // 1 halaman 1 gate walau 2 tab -- F-143 "Gate settings.manage (reuse DS-2)").
    Route::post('pengaturan/setelan/tema', [SettingsController::class, 'updateTheme'])->name('settings.theme.update');
    // v1.4 KPI-2 (F-166) -- tab KPI, permission SAMA settings.manage (reuse
    // DS-2/DS-3, pola konsisten: 1 halaman Setelan, 1 gate, N tab).
    Route::post('pengaturan/setelan/kpi', [SettingsController::class, 'updateKpi'])->name('settings.kpi.update');
});

// RBAC §C2/E1/E2 — CRUD user + kelola role, permission user.manage. 'create'
// WAJIB didaftarkan sebelum {user}/edit dan {role}/edit, pola sama F-76/'flags'.
Route::middleware(['auth', 'can:user.manage'])->group(function () {
    Route::get('pengaturan/users', [UserController::class, 'index'])->name('users.index');
    Route::get('pengaturan/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('pengaturan/users', [UserController::class, 'store'])->name('users.store');
    Route::get('pengaturan/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('pengaturan/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('pengaturan/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

    Route::get('pengaturan/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('pengaturan/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('pengaturan/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('pengaturan/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('pengaturan/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::patch('pengaturan/roles/{role}/set-default', [RoleController::class, 'setDefault'])->name('roles.set-default');
    Route::delete('pengaturan/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});

Route::middleware(['auth', 'can:project.manage'])->scopeBindings()->group(function () {
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    // Revisi 2026-08-07: 'projects/archive' (listing, GET) diletakkan SEBELUM
    // 'projects/{project}/edit' -- static path harus menang lebih dulu dari
    // wildcard supaya kata "archive" tidak pernah ditangkap sbg {project}.
    Route::get('projects/archive', [ProjectController::class, 'archived'])->name('projects.archived');
    Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::patch('projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
});

// D — TaskStatus CRUD per project. Seluruhnya permission status.manage (beda dari
// Project, tidak ada versi "index" yang dibuka untuk member).
Route::middleware(['auth', 'can:status.manage'])->scopeBindings()->group(function () {
    Route::get('projects/{project}/statuses', [TaskStatusController::class, 'index'])->name('task-statuses.index');
    Route::get('projects/{project}/statuses/create', [TaskStatusController::class, 'create'])->name('task-statuses.create');
    Route::post('projects/{project}/statuses', [TaskStatusController::class, 'store'])->name('task-statuses.store');
    // Hari-5 §B (F-74) — radio+checkbox SATU form untuk semua status project.
    // Segmen literal 'flags' WAJIB didaftarkan sebelum route {taskStatus} di
    // bawah, sama seperti 'create' di atas — kalau tidak, 'flags' akan ketangkap
    // sebagai nilai {taskStatus}.
    Route::patch('projects/{project}/statuses/flags', [TaskStatusController::class, 'updateFlags'])->name('task-statuses.update-flags');
    Route::get('projects/{project}/statuses/{taskStatus}/edit', [TaskStatusController::class, 'edit'])->name('task-statuses.edit');
    Route::put('projects/{project}/statuses/{taskStatus}', [TaskStatusController::class, 'update'])->name('task-statuses.update');
    Route::patch('projects/{project}/statuses/{taskStatus}/reorder', [TaskStatusController::class, 'reorder'])->name('task-statuses.reorder');
    Route::delete('projects/{project}/statuses/{taskStatus}', [TaskStatusController::class, 'destroy'])->name('task-statuses.destroy');
});

// Hari-4 §D — CRUD task, permission task.manage. index/updateStatus ada di
// routes/web.php (member juga boleh akses, lihat komentar di sana).
Route::middleware(['auth', 'can:task.manage'])->scopeBindings()->group(function () {
    Route::get('projects/{project}/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('projects/{project}/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('projects/{project}/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('projects/{project}/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
});

// v0.8 H4 (F-46) — CRUD blueprint recurring task per project, permission task.manage
// (sama dengan CRUD task biasa — F-46: template adalah cara lain membuat task,
// bukan resource terpisah izinnya). 'create' WAJIB sebelum {taskTemplate}/edit,
// pola sama F-76/'flags'/'create' di grup lain di file ini.
Route::middleware(['auth', 'can:task.manage'])->scopeBindings()->group(function () {
    Route::get('projects/{project}/templates', [TaskTemplateController::class, 'index'])->name('task-templates.index');
    Route::get('projects/{project}/templates/create', [TaskTemplateController::class, 'create'])->name('task-templates.create');
    Route::post('projects/{project}/templates', [TaskTemplateController::class, 'store'])->name('task-templates.store');
    Route::get('projects/{project}/templates/{taskTemplate}/edit', [TaskTemplateController::class, 'edit'])->name('task-templates.edit');
    Route::put('projects/{project}/templates/{taskTemplate}', [TaskTemplateController::class, 'update'])->name('task-templates.update');
    Route::patch('projects/{project}/templates/{taskTemplate}/toggle-active', [TaskTemplateController::class, 'toggleActive'])->name('task-templates.toggle-active');
});

// Hari-4 §E4 — approve/reject di status is_review, permission task.approve (F-28).
Route::middleware(['auth', 'can:task.approve'])->scopeBindings()->group(function () {
    Route::patch('projects/{project}/tasks/{task}/approve', [TaskController::class, 'approve'])->name('tasks.approve');
    Route::patch('projects/{project}/tasks/{task}/reject', [TaskController::class, 'reject'])->name('tasks.reject');
});

// v0.8 H5 (F-105) — hapus attachment ADMIN ONLY, member APPEND-ONLY (tidak ada
// route hapus untuk member sama sekali, bukan cuma digerbangi UI). Reuse
// permission task.manage (setara "kelola task ini", sama seperti CRUD task biasa).
Route::middleware(['auth', 'can:task.manage'])->scopeBindings()->group(function () {
    Route::delete('projects/{project}/tasks/{task}/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
});

// v0.8 H6 (F-50) — antrean & keputusan perpanjangan deadline, permission
// task.approve (sama dengan approve/reject task biasa, F-28-setara — BF §6
// matriks "Approve extension" admin only). Flat (bukan nested project/task) —
// extension resolusi lewat route model binding biasa, tidak butuh scopeBindings
// karena tidak ada {project}/{task} di URL ini.
Route::middleware(['auth', 'can:task.approve'])->group(function () {
    Route::get('pengaturan/perpanjangan', [DeadlineExtensionController::class, 'index'])->name('extensions.index');
    Route::patch('deadline-extensions/{deadlineExtension}/approve', [DeadlineExtensionController::class, 'approve'])->name('extensions.approve');
    Route::patch('deadline-extensions/{deadlineExtension}/reject', [DeadlineExtensionController::class, 'reject'])->name('extensions.reject');
});
