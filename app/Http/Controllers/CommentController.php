<?php

/**
 * ==========================================================
 * MODUL       : CommentController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : CRUD komentar per task (v1.0 H3, F-113/F-114/F-115) — diskusi
 *               terbuka untuk siapa pun yang bisa lihat task ini (project member
 *               ATAU admin, F-95), edit/hapus HANYA penulis sendiri.
 * DIPANGGIL   : routes/web.php (mixed access, gating DI CONTROLLER)
 * MEMANGGIL   : Comment, Project (members() untuk filter mention, C1)
 * DATA MASUK  : Form komentar (tasks/show.tsx), route model binding
 *               project/task/comment (F-76 scopeBindings)
 * DATA KELUAR : Baris comments, notifications (via CommentObserver, F-114)
 * RISIKO      : SUMBER F-113 — TIDAK ADA logActivity() di controller ini maupun
 *               observernya. C1 — extractMentionedUserIds() SATU-SATUNYA tempat
 *               token @[Nama](id) diterjemahkan jadi user_id, dan SATU-SATUNYA
 *               tempat non-member project DIBUANG DIAM-DIAM (bukan error validasi
 *               — mention ke orang luar project tidak masuk akal, bukan salah input).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    /**
     * BUSINESS RULE: F-95 — pola sama TaskController::show() (project.viewAll
     * ATAU member project ini), 404 untuk yang bukan member (task-nya "tidak ada"
     * baginya, konsisten dengan halaman detail tempat komentar ini ditampilkan).
     */
    public function store(StoreCommentRequest $request, Project $project, Task $task): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('project.viewAll') || $project->members()->whereKey($user->id)->exists(), 404);

        $body = $request->validated('body');

        Comment::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => $body,
            'mentioned_user_ids' => $this->extractMentionedUserIds($body, $project),
        ]);

        return back();
    }

    /**
     * BUSINESS RULE: F-115 — HANYA penulis. mentioned_user_ids dihitung ULANG
     * dari body baru; CommentObserver::updated() yang menentukan siapa BENAR-BENAR
     * baru disebut (diff lama-vs-baru, C4), bukan di sini.
     */
    public function update(UpdateCommentRequest $request, Project $project, Task $task, Comment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 403, 'Kamu hanya bisa mengubah komentar sendiri.');

        $body = $request->validated('body');

        $comment->update([
            'body' => $body,
            'mentioned_user_ids' => $this->extractMentionedUserIds($body, $project),
        ]);

        return back();
    }

    /**
     * BUSINESS RULE: F-115 — HANYA penulis, SOFT DELETE (trait SoftDeletes di
     * model, delete() di sini otomatis mengisi deleted_at, bukan hapus baris).
     */
    public function destroy(Project $project, Task $task, Comment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === request()->user()->id, 403, 'Kamu hanya bisa menghapus komentar sendiri.');

        $comment->delete();

        return back();
    }

    /**
     * KONTRAK: cari token @[Nama](id) di $body, kembalikan user_id UNIK yang
     * BENAR-BENAR member project ini (C1 — non-member DIBUANG diam-diam, bukan
     * ditolak sebagai error, karena mention orang luar bukan kesalahan INPUT,
     * cuma tidak berlaku). Nama di dalam [...] tidak divalidasi/dipakai —
     * cuma untuk dibaca manusia di body mentah, sumber kebenaran adalah id.
     *
     * @return array<int, int>
     */
    private function extractMentionedUserIds(string $body, Project $project): array
    {
        preg_match_all('/@\[[^\]]+\]\((\d+)\)/', $body, $matches);

        $candidateIds = array_unique(array_map('intval', $matches[1] ?? []));

        if (empty($candidateIds)) {
            return [];
        }

        return $project->members()->whereIn('users.id', $candidateIds)->pluck('users.id')->values()->all();
    }
}
