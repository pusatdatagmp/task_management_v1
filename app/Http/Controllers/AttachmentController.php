<?php

/**
 * ==========================================================
 * MODUL       : AttachmentController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Upload/download/hapus attachment `output` (F-49) — infrastruktur
 *               storage privat + gating (F-95/F-104/F-105/F-107). Evidence
 *               (`type=evidence`) di-wire lewat DeadlineExtensionController
 *               (v0.8 H6) yang memakai Attachment::storeUploadedFile() yang sama.
 * DIPANGGIL   : routes/web.php (store, download — mixed admin+assignee),
 *               routes/admin.php (destroy — admin-only, can:task.manage)
 * MEMANGGIL   : Attachment, Task (attachments() relation), Storage disk 'local'
 * DATA MASUK  : Form upload (tasks/show.tsx), route model binding project/task/attachment
 *               (F-76 scopeBindings — attachment WAJIB anak task WAJIB anak project)
 * DATA KELUAR : File fisik di storage/app/private/attachments (di luar public root),
 *               baris attachments, activity_logs (attachment_uploaded/deleted)
 * RISIKO      : SUMBER : A1 — file HANYA bisa diambil lewat download() di sini (cek
 *               permission dulu), BUKAN URL langsung. Laravel auto-register route
 *               'storage/{path}' (config 'serve'=>true) TAPI disk 'local' tidak
 *               set 'visibility'=>'public' -> default 'private' -> route itu WAJIB
 *               signed URL yang tidak pernah kita generate untuk attachment (lihat
 *               vendor/laravel/framework .../Filesystem/ServeFile.php) — jalur itu
 *               TIDAK dipakai fitur ini sama sekali, jangan panggil Storage::url()/
 *               temporaryUrl() untuk attachment atau proteksi ini bocor.
 *               A3 — path fisik pakai UUID (Str::uuid()), BUKAN nama asli user,
 *               supaya tidak ada input user yang pernah jadi bagian path filesystem.
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * BUSINESS RULE: F-95 — assignee ATAU admin (task.manage, proxy sama seperti
     * TaskTransitionService::changeStatus()), BUKAN permission RBAC baru untuk member.
     * F-104 — freeze begitu task disetujui: is_completed DAN approved_at (dua flag,
     * bukan nama status F-44) sudah terisi lewat TaskTransitionService::approve()
     * dalam SATU update() atomik, jadi cukup dicek berbarengan di sini.
     */
    public function store(StoreAttachmentRequest $request, Project $project, Task $task): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->can('task.manage') || $task->assignees()->whereKey($user->id)->exists(),
            403,
            'Kamu tidak di-assign ke task ini.'
        );

        $task->loadMissing('taskStatus');
        abort_if(
            $task->taskStatus->is_completed && $task->approved_at !== null,
            403,
            'Task sudah disetujui — lampiran output dibekukan (F-104).'
        );

        Attachment::storeUploadedFile($request->file('file'), [
            'task_id' => $task->id,
            'type' => 'output',
            'uploaded_by' => $user->id,
        ]);

        return back();
    }

    /**
     * BUSINESS RULE: F-95 — pola sama TaskController::show(): project.viewAll ATAU
     * member project ini (assignee task otomatis anggota project — StoreTaskRequest
     * mewajibkan assignees berasal dari project_user). Non-member -> 404 (bukan 403),
     * jangan bocorkan keberadaan attachment (F-95/A4 di tasks/show).
     */
    public function download(Project $project, Task $task, Attachment $attachment): StreamedResponse
    {
        $user = request()->user();

        abort_unless(
            $user->can('project.viewAll') || $project->members()->whereKey($user->id)->exists(),
            404
        );

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }

    /**
     * BUSINESS RULE: F-105 — admin only, dijaga middleware can:task.manage di
     * routes/admin.php (bukan cek ulang di sini — satu sumber kebenaran gate).
     * F-107 — TERKUNCI PERMANEN begitu task disetujui, bahkan untuk admin: bukti
     * kerja adalah catatan sejarah dasar quality_rating/scoring v1.5/payroll v2.0,
     * tidak boleh lenyap siapa pun (pola sama F-39/F-104 — beku selamanya).
     * Salah-upload PASCA-approve → arsip/tandai di UI, BUKAN hapus (keputusan Boss).
     * Hard delete (bukan soft delete, F-16 hanya wajib untuk users/projects/tasks) —
     * file fisik ikut dihapus dari storage supaya tidak ada sampah yatim.
     */
    public function destroy(Project $project, Task $task, Attachment $attachment): RedirectResponse
    {
        $task->loadMissing('taskStatus');
        abort_if(
            $task->taskStatus->is_completed && $task->approved_at !== null,
            403,
            'Lampiran task yang sudah disetujui terkunci permanen.'
        );

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return back();
    }
}
