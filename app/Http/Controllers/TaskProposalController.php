<?php

/**
 * ==========================================================
 * MODUL       : TaskProposalController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-186 — alur pengajuan task oleh member (ajukan, riwayat sendiri)
 *               + antrean approve/reject admin. Pola SAMA DeadlineExtensionController
 *               (ajukan mixed-access, keputusan admin permission terpisah) — tapi
 *               entitas yang diajukan ADALAH Task itu sendiri, bukan baris di
 *               tabel lain, lihat header migrasi kolom proposal_status.
 * DIPANGGIL   : routes/web.php (create/store/myProposals — can:task.proposeOwn),
 *               routes/admin.php (index/approve/reject — can:task.approve)
 * MEMANGGIL   : Task, TaskStatus, Project, TaskObserver (notifikasi/log, otomatis
 *               lewat Eloquent event, BUKAN dipanggil manual di sini — F-22)
 * DATA MASUK  : Form ajukan (task-proposals/create.tsx), form approve/reject
 *               (task-proposals/index.tsx)
 * DATA KELUAR : Baris tasks (proposal_status berubah), activity_logs, notifications
 *               (semua via TaskObserver)
 * RISIKO      : SUMBER : method approve()/reject()/index() SENGAJA TIDAK pakai
 *               implicit route-model-binding `Task $task` — Task::booted() sekarang
 *               pasang PendingProposalScope yang MENYEMBUNYIKAN baris 'pending'
 *               dari query default (termasuk query binding otomatis Laravel), jadi
 *               binding implisit akan 404 duluan SEBELUM controller sempat jalan.
 *               $task di sini SELALU diresolve manual via
 *               Task::withoutGlobalScope(PendingProposalScope::class), int $task
 *               polos di signature method (BUKAN type-hint Task) supaya Laravel
 *               tidak diam-diam mencoba binding implisit di belakang layar.
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\TaskProposal\ApproveTaskProposalRequest;
use App\Http\Requests\TaskProposal\RejectTaskProposalRequest;
use App\Http\Requests\TaskProposal\StoreTaskProposalRequest;
use App\Models\Project;
use App\Models\Scopes\PendingProposalScope;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class TaskProposalController extends Controller
{
    /**
     * BUSINESS RULE: F-186 — dropdown project DIBATASI project yang user login
     * ikuti (User::projects(), pivot project_user), bukan seluruh project org.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('task-proposals/create', [
            'projects' => $request->user()->projects()->orderBy('name')->get(['projects.id', 'projects.name']),
            // Permintaan Boss (2026-09-04): katalog tag organisasi -- pola IDENTIK
            // TaskController::create(), TagPicker pilih dari sini (Tag sudah
            // auto-scope organization_id via BelongsToOrganization, F-15).
            'availableTags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    /**
     * BUSINESS RULE: F-186 — status task baru SELALU posisi terendah project
     * (pola sama TaskController::store()) DAN proposal_status='pending' (task
     * disembunyikan total sampai admin putuskan, lihat PendingProposalScope).
     * assignee DIKUNCI ke diri sendiri DI SERVER — form tidak pernah mengirim
     * daftar assignee (StoreTaskProposalRequest tidak punya field itu sama sekali).
     */
    public function store(StoreTaskProposalRequest $request): RedirectResponse
    {
        $user = $request->user();
        $project = Project::findOrFail($request->validated('project_id'));

        $statusId = TaskStatus::where('project_id', $project->id)->orderBy('position')->value('id');

        if (! $statusId) {
            throw ValidationException::withMessages([
                'project_id' => 'Project ini belum punya status task (F-19) — hubungi admin.',
            ]);
        }

        $task = Task::create([
            ...$request->safe()->only(['title', 'description', 'task_type', 'priority_quadrant', 'estimated_minutes', 'points', 'due_date']),
            'project_id' => $project->id,
            'task_status_id' => $statusId,
            'created_by' => $user->id,
            'proposal_status' => 'pending',
        ]);

        $task->assignees()->sync([$user->id]);
        // Permintaan Boss (2026-09-04): multi-tag saat mengajukan, pola IDENTIK
        // TaskController::store() (nol activity log per sync, murni kategorisasi
        // tampilan, lihat KONTRAK Task::tags()).
        $task->tags()->sync($request->validated('tags') ?? []);

        return to_route('task-proposals.my');
    }

    /**
     * BUSINESS RULE: F-186 — riwayat pengajuan MILIK SENDIRI (created_by = user
     * login), pending & rejected saja. Proposal yang SUDAH di-approve TIDAK
     * muncul lagi di sini (proposal_status balik ke NULL, sudah jadi task biasa
     * — lihat Tugas Saya, bukan "riwayat pengajuan" lagi). withTrashed() WAJIB —
     * proposal rejected SELALU soft-deleted (F-16) di reject() bawah.
     */
    public function myProposals(Request $request): Response
    {
        $proposals = Task::withoutGlobalScope(PendingProposalScope::class)
            ->withTrashed()
            ->where('created_by', $request->user()->id)
            ->whereIn('proposal_status', ['pending', 'rejected'])
            ->with(['project:id,name'])
            ->latest()
            ->get(['id', 'project_id', 'title', 'due_date', 'estimated_minutes', 'points', 'proposal_status', 'proposal_review_note', 'created_at']);

        return Inertia::render('task-proposals/my-proposals', [
            'proposals' => $proposals,
        ]);
    }

    /**
     * BUSINESS RULE: F-186 — antrean admin = PENDING saja (sama pola
     * DeadlineExtensionController::index() — yang sudah diputuskan tercatat di
     * riwayat pemohon, tidak perlu tampil lagi di sini).
     */
    public function index(): Response
    {
        $proposals = Task::withoutGlobalScope(PendingProposalScope::class)
            ->where('proposal_status', 'pending')
            ->with(['project:id,name', 'createdBy:id,name'])
            ->oldest()
            ->get(['id', 'project_id', 'title', 'description', 'task_type', 'priority_quadrant', 'due_date', 'estimated_minutes', 'points', 'created_by', 'created_at']);

        // WORKAROUND (pola SAMA TaskController::show(), F-82 A3): description
        // adalah HTML mentah dari Tiptap, paste bisa membawa <script>. Frontend
        // (task-proposals/index.tsx) render lewat dangerouslySetInnerHTML --
        // WAJIB disanitasi DI SINI sebelum dikirim, preset resmi Symfony
        // HtmlSanitizer::allowSafeElements() (whitelist W3C, bukan whitelist manual).
        $sanitizer = new HtmlSanitizer((new HtmlSanitizerConfig)->allowSafeElements());
        $proposals->each(function (Task $proposal) use ($sanitizer) {
            $proposal->description = $proposal->description ? $sanitizer->sanitize($proposal->description) : null;
        });

        return Inertia::render('task-proposals/index', [
            'proposals' => $proposals,
        ]);
    }

    /**
     * BUSINESS RULE (keputusan Boss 2026-09-04): due_date/estimated_minutes/points
     * WAJIB diisi ulang admin (ApproveTaskProposalRequest) — BUKAN reuse nilai
     * yang diajukan member apa adanya, cegah gaming KPI lewat estimasi sendiri
     * (Task::isOnTime(), F-109). proposal_status -> NULL (task jadi normal 100%,
     * bukan nilai enum 'approved' yang harus terus dijaga di semua query lain).
     */
    public function approve(ApproveTaskProposalRequest $request, int $task): RedirectResponse
    {
        $proposal = $this->findPending($task);

        $proposal->update([
            'due_date' => $request->validated('due_date'),
            'estimated_minutes' => $request->validated('estimated_minutes'),
            'points' => $request->validated('points'),
            'proposal_status' => null,
            'proposal_reviewed_by' => $request->user()->id,
            'proposal_reviewed_at' => now(),
        ]);

        return to_route('task-proposals.index');
    }

    /**
     * BUSINESS RULE: F-186/F-16 — reject = tandai 'rejected' + alasan (WAJIB,
     * RejectTaskProposalRequest) LALU soft-delete (keputusan Boss: bukan hapus
     * permanen — ada jejak audit). Dua langkah TERPISAH (update lalu delete)
     * SENGAJA — TaskObserver::updated() butuh proposal_status masih 'rejected'
     * yang BARU SAJA berubah untuk memicu notifikasi+log (F-22/F-51), baru
     * SETELAH itu delete() memicu log 'deleted' generik miliknya sendiri.
     */
    public function reject(RejectTaskProposalRequest $request, int $task): RedirectResponse
    {
        $proposal = $this->findPending($task);

        $proposal->update([
            'proposal_status' => 'rejected',
            'proposal_reviewed_by' => $request->user()->id,
            'proposal_reviewed_at' => now(),
            'proposal_review_note' => $request->validated('review_note'),
        ]);

        $proposal->delete();

        return to_route('task-proposals.index');
    }

    /**
     * KONTRAK: resolve manual (lihat RISIKO header — implicit binding 404 duluan
     * karena PendingProposalScope) + tolak kalau SUDAH diputuskan sebelumnya
     * (mencegah approve/reject dobel, pola sama DeadlineExtensionController::
     * guardStillPending()).
     */
    private function findPending(int $taskId): Task
    {
        $task = Task::withoutGlobalScope(PendingProposalScope::class)->findOrFail($taskId);

        if ($task->proposal_status !== 'pending') {
            throw ValidationException::withMessages([
                'proposal_status' => 'Pengajuan ini sudah diputuskan sebelumnya.',
            ]);
        }

        return $task;
    }
}
