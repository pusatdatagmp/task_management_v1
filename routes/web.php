<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DeadlineExtensionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskChecklistItemController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// F-76: scopeBindings() -> {task} di URL WAJIB anak dari {project} di URL yang
// sama (Laravel otomatis pakai relasi Project::tasks() untuk itu). URL
// /projects/1/tasks/99 kalau task 99 sebenarnya milik project 2 -> 404 dari
// routing itu sendiri, BUKAN dari pengecekan manual di controller yang gampang
// lupa dipasang di controller baru (pola sama F-67 — daftar manual selalu ketinggalan).
Route::middleware(['auth'])->scopeBindings()->group(function () {
    // v0.8 H3 (F-52/F-95): route 'dashboard' PINDAH ke routes/admin.php, digerbangi
    // can:dashboard.view — halaman ini sekarang berisi angka tim (bukan placeholder
    // kosong lagi), member TIDAK boleh akses (F-29/F-95). Cari 'dashboard.summary'.

    // 03-BUSINESS-FLOW §6: index BUKAN admin-only — admin lihat semua project,
    // member hanya yang di-assign (filter di ProjectController::index()). Create/
    // edit/update/archive tetap admin-only, lihat routes/admin.php.
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');

    // Hari-5 §D: My Tasks — flat lintas project, TIDAK nested di bawah {project}.
    Route::get('my-tasks', [TaskController::class, 'myTasks'])->name('tasks.my');

    // Hari-6 §B: search FULLTEXT (F-7) — flat lintas project sama seperti my-tasks,
    // dipakai search box di header. JSON (bukan Inertia::render) karena dropdown
    // live-search butuh fetch async tanpa navigasi halaman.
    Route::get('search', [TaskController::class, 'search'])->name('tasks.search');

    // Hari-6 §C4: bell dropdown notifikasi. 'read-all' WAJIB didaftarkan sebelum
    // '{notification}/read' (pola sama F-76/status-flags) — kalau tidak, 'read-all'
    // akan ketangkap sebagai nilai {notification}.
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Hari-4 §D/E: index task BUKAN admin-only (member lihat task project-nya),
    // updateStatus BUKAN admin-only (E2: member ubah status task sendiri).
    // Create/edit/delete/approve/reject tetap admin-only, lihat routes/admin.php.
    Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::patch('projects/{project}/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');

    // v1.0 H1 (F-109): Board = tampilan alternatif List View, gating IDENTIK
    // TaskController::show() (404 untuk member project lain, bukan 403).
    Route::get('projects/{project}/board', [BoardController::class, 'index'])->name('tasks.board');

    // v0.8 H5 (F-49/F-95): upload/download attachment output — mixed access
    // (assignee ATAU admin, dicek DI CONTROLLER, bukan permission RBAC baru untuk
    // member). Hapus (admin-only, F-105) di routes/admin.php lewat can:task.manage.
    Route::post('projects/{project}/tasks/{task}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('projects/{project}/tasks/{task}/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');

    // v0.8 H6 (F-50/F-95): ajukan perpanjangan deadline — mixed access (assignee
    // ATAU admin, dicek DI CONTROLLER). Flat lintas project (task_id di BODY form,
    // pola sama my-tasks) — TIDAK ada {project}/{task} di URL untuk scopeBindings.
    // Approve/reject (admin-only, can:task.approve) di routes/admin.php.
    Route::post('deadline-extensions', [DeadlineExtensionController::class, 'store'])->name('extensions.store');
    Route::get('my-extensions', [DeadlineExtensionController::class, 'myExtensions'])->name('extensions.my');

    // v1.0 H3 (F-113/F-114/F-115): komentar per task — mixed access (project
    // member ATAU admin buat komentar; edit/hapus HANYA penulis, dicek DI
    // CONTROLLER). Task::comments() relation dipakai scopeBindings ({comment}).
    Route::post('projects/{project}/tasks/{task}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::put('projects/{project}/tasks/{task}/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('projects/{project}/tasks/{task}/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // v1.2 H5 (F-123/F-127): checklist item per task — mixed access (task.manage
    // ATAU assignee, dicek DI CONTROLLER, pola sama komentar di atas). 'toggle'
    // (centang) TERPISAH dari update (ubah teks, task.manage-only di controller).
    Route::post('projects/{project}/tasks/{task}/checklist-items', [TaskChecklistItemController::class, 'store'])->name('checklist-items.store');
    Route::put('projects/{project}/tasks/{task}/checklist-items/{checklistItem}', [TaskChecklistItemController::class, 'update'])->name('checklist-items.update');
    Route::patch('projects/{project}/tasks/{task}/checklist-items/{checklistItem}/toggle', [TaskChecklistItemController::class, 'toggleDone'])->name('checklist-items.toggle');
    Route::delete('projects/{project}/tasks/{task}/checklist-items/{checklistItem}', [TaskChecklistItemController::class, 'destroy'])->name('checklist-items.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';

// Hari-7 §A1 (F-82): show HARUS didaftarkan SETELAH admin.php. Alasan: pattern
// GET projects/{project}/tasks/{task} (1 segmen dinamis) akan menangkap literal
// 'tasks/create' (admin.php) sebagai {task}='create' kalau didaftarkan LEBIH DULU
// — implicit route model binding gagal cari Task id/slug 'create' -> 404, bukan
// fallback ke route create yang benar. Pola identik F-76 ('flags' sebelum
// {taskStatus}, 'read-all' sebelum {notification}) — segmen literal wajib
// menang atas segmen dinamis yang bisa menangkapnya.
Route::middleware(['auth'])->scopeBindings()->group(function () {
    Route::get('projects/{project}/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
});
