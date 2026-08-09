<?php

/**
 * ==========================================================
 * MODUL       : TaskController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : CRUD task per project (F-29 admin only untuk create/edit/delete) +
 *               endpoint transisi status (dipakai admin & member, F-45/F-28) +
 *               halaman detail task (F-82, Hari-7 §A — satu-satunya tempat member
 *               membaca description rich text).
 * DIPANGGIL   : routes/web.php (index, updateStatus, show — bukan admin-only),
 *               routes/admin.php (create/store/edit/update/destroy/approve/reject)
 * MEMANGGIL   : Task, TaskStatus, TaskTransitionService, Symfony\...\HtmlSanitizer (F-82 A3)
 * DATA MASUK  : Form Task CRUD, form approve (quality_rating), aksi ubah status
 * DATA KELUAR : Inertia pages 'tasks/*'
 * RISIKO      : SUMBER (revisi 2026-08-06 item 1): index()/all()/myTasks() WAJIB
 *               bungkus query dengan self::withChecklistCounts() SEBELUM paginate()/
 *               get() — progressPercent() (Task model) baca alias checklist_items_count/
 *               checklist_done_items_count dari situ, lupa pasang = N+1 per baris (F-85).
 *               SUMBER : F-76 — {project}/{task} di URL di-scope oleh
 *               ->scopeBindings() di route group (routes/web.php & routes/admin.php),
 *               BUKAN pengecekan manual per-method di sini lagi. Task yang bukan anak
 *               project di URL otomatis 404 dari routing (Project::tasks() relation).
 *               JANGAN tambahkan lagi abort_unless($task->project_id===...) —
 *               dua sumber kebenaran, salah satunya pasti membusuk (lihat Hari-4).
 *               show() WAJIB sanitasi description sebelum dikirim ke frontend —
 *               HTML mentah dari Tiptap bisa berisi <script> hasil paste (F-82 A3).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\Task\ApproveTaskRequest;
use App\Http\Requests\Task\FilterAllTasksRequest;
use App\Http\Requests\Task\FilterTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Requests\Task\UpdateTaskStatusRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\LiveTaskCounter;
use App\Services\TaskTransitionService;
use App\Support\ActivityLogPresenter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class TaskController extends Controller
{
    /**
     * BUSINESS RULE: Hari-5 §C — filter/sort/paginate WAJIB SERVER-SIDE (C5, skala
     * ~5rb task/tahun — kirim semua ke frontend lalu filter di React tidak scale).
     * List di-FLATTEN (bukan nested parent/children seperti Hari-4) — subtask
     * ditandai lewat kolom `parent` di tiap baris, BUKAN indentasi visual, karena
     * indentasi tidak survive di bawah sort/filter/pagination sembarangan (anak
     * bisa kena filter sementara induknya tidak, atau keduanya beda halaman).
     */
    public function index(Project $project, FilterTaskRequest $request): Response
    {
        $user = $request->user();

        // F-90: project.viewAll (bukan isAdmin()) — "lihat semua project" (BF §6).
        abort_unless($user->can('project.viewAll') || $project->members()->whereKey($user->id)->exists(), 403);

        $filters = $request->validated();

        $query = self::withChecklistCounts(
            $project->tasks()->with(['taskStatus', 'assignees:id,name', 'parent:id,title'])
        );

        if (! empty($filters['status'])) {
            $query->whereIn('task_status_id', $filters['status']);
        }

        if (! empty($filters['assignee'])) {
            $query->whereHas('assignees', fn ($q) => $q->whereIn('users.id', $filters['assignee']));
        }

        if (! empty($filters['priority'])) {
            $query->whereIn('priority', $filters['priority']);
        }

        // BUSINESS RULE F-44: "terlambat" dicek lewat flag is_completed, BUKAN nama
        // status — project manapun, status apapun namanya, tetap benar.
        match ($filters['due'] ?? 'all') {
            'today' => $query->whereDate('due_date', Carbon::today()),
            'this_week' => $query->whereBetween('due_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
            'overdue' => $query->where('due_date', '<', Carbon::now())
                ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false)),
            default => null,
        };

        $sort = $filters['sort'] ?? 'due_date';
        $direction = $filters['direction'] ?? 'asc';

        if ($sort === 'priority') {
            // SUMBER: enum priority (low/normal/high/urgent) urut ALFABET tidak
            // merepresentasikan urgensi. FIELD() memaksa urutan eksplisit sesuai
            // arti bisnisnya (rendah -> mendesak).
            $query->orderByRaw("FIELD(priority, 'low','normal','high','urgent') ".($direction === 'desc' ? 'desc' : 'asc'));
        } else {
            $query->orderBy($sort, $direction);
        }

        $tasks = $query->paginate(25)->withQueryString();

        // F-94/B3: indikator counter live di List View — dot (is_work_state) SELALU
        // tampil, tapi angka menit HANYA ada kalau $user sendiri yang punya segmen
        // terbuka di task itu (B5 — bukan gabungan semua assignee). Di-batch SEKALI
        // untuk seluruh halaman (F-85), bukan query per baris.
        $liveCounters = (new LiveTaskCounter)->forTasks($tasks->getCollection(), $user);
        $tasks->getCollection()->each(function (Task $task) use ($liveCounters) {
            $task->live_counter = $liveCounters[$task->id] ?? null;
            // Revisi 2026-08-06 item 1: progress_percent (F-123 basis) — checklist_items_count/
            // checklist_done_items_count SUDAH dimuat withChecklistCounts() di atas (F-85).
            $task->progress_percent = $task->progressPercent();
        });

        return Inertia::render('tasks/index', [
            // 2026-08-08 (permintaan Boss): description ikut dikirim -- halaman ini
            // SEKARANG juga berfungsi sebagai detail project (tombol "Detail" di
            // projects/index.tsx), bukan cuma daftar task.
            'project' => $project->only(['id', 'name', 'description']),
            'tasks' => $tasks,
            'statuses' => $project->taskStatuses,
            'members' => $project->members()->select('users.id', 'users.name')->orderBy('users.name')->get(),
            'filters' => [
                'status' => $filters['status'] ?? [],
                'assignee' => $filters['assignee'] ?? [],
                'priority' => $filters['priority'] ?? [],
                'due' => $filters['due'] ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * BUSINESS RULE: F-82 — satu-satunya tempat member membaca description rich
     * text (F-30). List View tidak menampilkannya (kolom), dan member tidak boleh
     * edit (F-29) — tanpa halaman ini description dibayar tapi tak pernah terbaca.
     *
     * SUMBER: member yang BUKAN anggota project ini -> 404 (BUKAN 403), supaya
     * keberadaan task/project tidak bocor ke orang yang tidak berkepentingan
     * (beda dari index() yang pakai 403 — instruksi eksplisit Hari-7 §A4).
     * {project}/{task} sudah di-scope lewat ->scopeBindings() di route group
     * (F-76) — task yang bukan anak project di URL otomatis 404 dari routing.
     *
     * WORKAROUND F-82 A3: description berisi HTML mentah dari Tiptap (paste bisa
     * membawa <script>). Disanitasi DI SINI (server, sekali) pakai preset resmi
     * Symfony HtmlSanitizer::allowSafeElements() — whitelist tag/atribut "aman"
     * W3C, membuang script/style/iframe/event-handler apa pun namanya. Dipilih
     * preset resmi (bukan whitelist tag manual) supaya tidak diam-diam membuang
     * elemen sah yang dihasilkan Tiptap StarterKit lewat markdown-shortcut
     * (heading "# ", blockquote "> ", code block ``` ) walau toolbar cuma
     * expose Bold/Italic/List (rich-text-editor.tsx) — StarterKit tetap
     * mem-parsingnya kalau user ketik manual.
     */
    public function show(Project $project, Task $task): Response
    {
        $user = request()->user();

        // F-90: project.viewAll (bukan isAdmin()).
        abort_unless($user->can('project.viewAll') || $project->members()->whereKey($user->id)->exists(), 404);

        $task->load([
            'taskStatus',
            'assignees:id,name',
            'parent:id,title,task_status_id',
            'parent.taskStatus:id,name,color',
            'children' => fn ($q) => $q->with('taskStatus:id,name,color')->orderBy('title'),
            'checklistItems', // F-123: gate F-127 dicek server di TaskTransitionService, ini cuma render.
            // v0.8 H5 (F-49): attachment output, terbaru dulu. uploadedBy dibatasi
            // id/name (F-85 — jangan tarik seluruh kolom user termasuk hash password).
            'attachments' => fn ($q) => $q->where('type', 'output')->with('uploadedBy:id,name')->latest(),
            // v1.0 H3 (F-113/F-115): withTrashed() SENGAJA — komentar terhapus
            // TETAP tampil sebagai placeholder "[Komentar dihapus]" di alur thread
            // (keputusan Boss H3), bukan hilang total. Urut lama->baru (thread wajar).
            'comments' => fn ($q) => $q->withTrashed()->with('user:id,name')->oldest(),
        ]);

        // v1.0 H4 (F-95/F-106): riwayat task INI SAJA (subject_type+subject_id
        // Task, bukan attachment/extension yang subject-nya beda tabel). Gating
        // SUDAH ditegakkan di abort_unless() atas — B2: bukan activity.view
        // (itu KHUSUS log global), siapa pun yang boleh lihat halaman ini boleh
        // lihat riwayatnya. setRelation('subject', $task) manual (BUKAN eager
        // load 'subject' lewat with()) -- subject-nya SUDAH PASTI $task yang
        // sama persis, query ulang cuma buang-buang round trip (F-85).
        $activityLogs = ActivityLog::where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->with('user:id,name')
            ->latest('created_at')
            ->get()
            ->each(fn (ActivityLog $log) => $log->setRelation('subject', $task));

        $activityPresenter = new ActivityLogPresenter($activityLogs);

        $sanitizer = new HtmlSanitizer((new HtmlSanitizerConfig)->allowSafeElements());

        // F-90/D3: TIDAK ADA lagi 'isAdmin' di props — frontend baca
        // auth.permissions (shared global, HandleInertiaRequests) langsung.
        // F-94/B1/B6: counter live — gating akses via check membership di atas
        // (sama seperti seluruh halaman ini), gating DATA via LiveTaskCounter
        // (null kalau $user tidak sedang punya segmen terbuka di task ini, B5).
        return Inertia::render('tasks/show', [
            'project' => $project->only(['id', 'name']),
            'statuses' => $project->taskStatuses,
            // v1.0 H3: daftar member project untuk autocomplete @mention (C1 — cuma
            // member yang bisa disebut, daftar ini SEKALIGUS jadi whitelist tampilan).
            'projectMembers' => $project->members()->select('users.id', 'users.name')->orderBy('users.name')->get(),
            'task' => [
                ...$task->only([
                    'id', 'title', 'task_type', 'priority', 'priority_quadrant', 'due_date', 'points',
                    'estimated_minutes', 'actual_minutes', 'quality_rating', 'rejection_count',
                    'approved_at', // v0.8 H5: frontend pakai ini untuk kunci upload output (F-104).
                    'original_due_date', // v0.8 H6 (F-47): NULL kalau belum pernah diperpanjang.
                ]),
                'description_html' => $task->description ? $sanitizer->sanitize($task->description) : null,
                'task_status' => $task->taskStatus,
                // H7/F-132/F-138: state 5-nilai task-wide, dipakai frontend untuk
                // badge "Jeda" (F-138f) — TERPISAH dari live_counter (per-user).
                'work_state' => $task->computeWorkState(),
                'assignees' => $task->assignees,
                'parent' => $task->parent,
                'children' => $task->children,
                // F-123/B4: progress (done/total) dari DATA yang sama yang dipakai
                // gate F-127 di server — bukan hitung ganda di frontend.
                'checklist_items' => $task->checklistItems->map(fn ($item) => [
                    'id' => $item->id,
                    'text' => $item->text,
                    'is_done' => $item->is_done,
                ]),
                // Revisi 2026-08-06 item 1: checklistItems SUDAH eager-loaded di atas
                // (baris 172-ish load()) -- progressPercent() pakai koleksi itu langsung,
                // nol query tambahan (lihat KONTRAK Task::progressPercent()).
                'progress_percent' => $task->progressPercent(),
                // Revisi 2026-08-06 item 4: content_type/url/body ikut dikirim --
                // frontend render berbeda per mode (file=link download, link=buka
                // tab baru, text=tampil apa adanya).
                'attachments' => $task->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'content_type' => $a->content_type,
                    'file_name' => $a->file_name,
                    'file_size' => $a->file_size,
                    'url' => $a->url,
                    'body' => $a->body,
                    'uploaded_by' => $a->uploadedBy,
                    'created_at' => $a->created_at,
                ]),
                // v1.0 H3 (F-113/F-115): is_deleted -> frontend render placeholder
                // "[Komentar dihapus]", body TIDAK dikirim sama sekali untuk baris
                // terhapus (bukan cuma disembunyikan CSS — bahkan admin tidak lihat
                // isi asli lewat UI, keputusan H3 LANGKAH 0).
                'comments' => $task->comments->map(fn ($c) => [
                    'id' => $c->id,
                    'body' => $c->trashed() ? null : $c->body,
                    'user' => $c->user->only(['id', 'name']),
                    'created_at' => $c->created_at,
                    'is_edited' => ! $c->trashed() && $c->created_at->ne($c->updated_at),
                    'is_deleted' => $c->trashed(),
                    'is_mine' => $c->user_id === $user->id,
                ]),
                // v1.0 H4 (F-106): pesan SUDAH label manusiawi (ActivityLogPresenter),
                // frontend tinggal render string-nya, tidak ada logika terjemahan di JS.
                'activity_logs' => $activityLogs->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'message' => $activityPresenter->describe($log),
                    'created_at' => $log->created_at,
                ]),
                'live_counter' => (new LiveTaskCounter)->forTask($task, $user),
            ],
        ]);
    }

    /**
     * BUSINESS RULE: Hari-5 §D — "Task Saya", lintas project, HANYA task yang
     * di-assign ke user login. Dikelompokkan Terlambat/Hari ini/Minggu ini/Nanti
     * (D2), urut due_date terdekat dulu di dalam & lintas kelompok. Task yang
     * SUDAH is_completed sengaja dikeluarkan — ini halaman kerja aktif member,
     * bukan riwayat (D6 juga melarang angka dashboard di sini, konsisten dengan
     * semangat "cuma yang perlu dikerjakan").
     */
    public function myTasks(Request $request): Response
    {
        $user = $request->user();

        $tasks = self::withChecklistCounts(
            Task::whereHas('assignees', fn ($q) => $q->whereKey($user->id))
                ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false))
                ->with(['taskStatus', 'assignees:id,name', 'project:id,name', 'project.taskStatuses'])
                ->orderBy('due_date')
        )->get();

        // F-94/B2: counter live per baris — di-batch SEKALI atas seluruh task
        // sebelum dikelompokkan (F-85), bukan per grup/per baris.
        $liveCounters = (new LiveTaskCounter)->forTasks($tasks, $user);
        $tasks->each(function (Task $task) use ($liveCounters) {
            $task->live_counter = $liveCounters[$task->id] ?? null;
            $task->progress_percent = $task->progressPercent(); // revisi 2026-08-06 item 1
        });

        $now = Carbon::now();
        $todayEnd = Carbon::today()->endOfDay();
        $weekEnd = Carbon::now()->endOfWeek();

        return Inertia::render('tasks/my-tasks', [
            'groups' => [
                'overdue' => $tasks->filter(fn (Task $t) => $t->due_date->lt($now))->values(),
                'today' => $tasks->filter(fn (Task $t) => $t->due_date->gte($now) && $t->due_date->lte($todayEnd))->values(),
                'this_week' => $tasks->filter(fn (Task $t) => $t->due_date->gt($todayEnd) && $t->due_date->lte($weekEnd))->values(),
                'later' => $tasks->filter(fn (Task $t) => $t->due_date->gt($weekEnd))->values(),
            ],
        ]);
    }

    /**
     * BUSINESS RULE: v1.2 H7b (F-140/F-144/F-139) — "Semua Tugas": List lintas SEMUA
     * project (admin oversight, permission project.viewAll di route). BEDA dari
     * myTasks() (cuma task assignee sendiri) dan index() (1 project). Filter/sort
     * prioritas pakai priority_quadrant (F-139), BUKAN enum priority lama.
     *
     * SUMBER Kanban: task_statuses PER-PROJECT (beda project bisa beda status) —
     * toggle Board cuma valid kalau `project_id` sudah dipilih (keputusan Boss,
     * lihat CATATAN sesi), makanya endpoint ini TIDAK mengirim kolom board sama
     * sekali. Frontend arahkan ke route('tasks.board', project_id) yang SUDAH ADA
     * (F-109, nol kode board baru) begitu project_id filter aktif.
     */
    public function all(FilterAllTasksRequest $request): Response
    {
        $user = $request->user();
        $filters = $request->validated();

        // SUMBER: 'project.taskStatuses' (BUKAN cuma 'project:id,name') supaya
        // TaskStatusCell per baris (F-45/F-28) bisa bangun dropdown status project
        // MASING-MASING task — pola sama myTasks(), status TIDAK seragam lintas project.
        $query = self::withChecklistCounts(
            Task::query()->with(['taskStatus', 'assignees:id,name', 'project:id,name', 'project.taskStatuses', 'parent:id,title'])
        );

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        // F-44: status difilter lewat FLAG (is_work_state/is_review/is_completed),
        // bukan task_status_id mentah — id status TIDAK seragam lintas project.
        if (! empty($filters['status_flag'])) {
            $query->whereHas('taskStatus', function ($statusQuery) use ($filters) {
                $statusQuery->where(function ($group) use ($filters) {
                    foreach ($filters['status_flag'] as $flag) {
                        $group->orWhere(function ($branch) use ($flag) {
                            match ($flag) {
                                'completed' => $branch->where('is_completed', true),
                                'review' => $branch->where('is_review', true),
                                'in_progress' => $branch->where('is_work_state', true),
                                default => $branch->where('is_work_state', false)
                                    ->where('is_review', false)
                                    ->where('is_completed', false),
                            };
                        });
                    }
                });
            });
        }

        if (! empty($filters['assignee'])) {
            $query->whereHas('assignees', fn ($q) => $q->whereIn('users.id', $filters['assignee']));
        }

        if (! empty($filters['task_type'])) {
            $query->whereIn('task_type', $filters['task_type']);
        }

        if (! empty($filters['priority_quadrant'])) {
            $query->whereIn('priority_quadrant', $filters['priority_quadrant']);
        }

        match ($filters['due'] ?? 'all') {
            'today' => $query->whereDate('due_date', Carbon::today()),
            'this_week' => $query->whereBetween('due_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
            'overdue' => $query->where('due_date', '<', Carbon::now())
                ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false)),
            default => null,
        };

        $sort = $filters['sort'] ?? 'due_date';
        $direction = $filters['direction'] ?? 'asc';

        if ($sort === 'priority_quadrant') {
            // F-139: p1 (bobot 4, paling mendesak) -> p4, sama pola FIELD() dengan
            // sort priority enum lama, cuma daftarnya diganti quadrant.
            $query->orderByRaw("FIELD(priority_quadrant, 'p1','p2','p3','p4') ".($direction === 'desc' ? 'desc' : 'asc'));
        } elseif ($sort === 'project') {
            // 2026-08-08 (permintaan Boss): sort by nama project -- subquery
            // korelasi (bukan join), tasks.project_id -> 1 project, nol ambiguitas,
            // nol risiko baris dobel dari paginate() (beda dari join tabel N-ke-M).
            $query->orderBy(Project::select('name')->whereColumn('id', 'tasks.project_id'), $direction);
        } elseif ($sort === 'assignee') {
            // 2026-08-08 (keputusan Boss): task bisa >1 assignee (F-63b) -- sort
            // berdasar assignee PERTAMA alfabetis per task (subquery korelasi via
            // task_user, LIMIT 1). Task tanpa assignee -> NULL, MySQL taruh di
            // akhir utk ASC (default), di awal utk DESC -- perilaku native, tidak
            // dipaksa lain supaya konsisten sort NULL kolom lain (mis. priority_quadrant).
            $query->orderBy(
                User::select('name')
                    ->join('task_user', 'task_user.user_id', '=', 'users.id')
                    ->whereColumn('task_user.task_id', 'tasks.id')
                    ->orderBy('name')
                    ->limit(1),
                $direction
            );
        } else {
            // 'title'/'due_date'/'points'/'created_at' -- kolom langsung, orderBy() biasa.
            $query->orderBy($sort, $direction);
        }

        $tasks = $query->paginate(25)->withQueryString();

        $liveCounters = (new LiveTaskCounter)->forTasks($tasks->getCollection(), $user);
        $tasks->getCollection()->each(function (Task $task) use ($liveCounters) {
            $task->live_counter = $liveCounters[$task->id] ?? null;
            $task->progress_percent = $task->progressPercent(); // revisi 2026-08-06 item 1
        });

        return Inertia::render('tasks/all', [
            'tasks' => $tasks,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            // BUG FIX (2026-08-08, permintaan Boss): dropdown filter assignee di
            // "Semua Tugas" SEBELUM ini menampilkan SEMUA user tanpa filter
            // is_active -- member yang sudah dinonaktifkan (toggleActive(), F-16)
            // masih muncul sebagai opsi. Baris task lama milik mereka TETAP ada
            // (F-16, dilarang hard delete), tapi dropdown filter ini sekarang
            // cuma tawarkan member AKTIF.
            'members' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'project_id' => $filters['project_id'] ?? null,
                'status_flag' => $filters['status_flag'] ?? [],
                'assignee' => $filters['assignee'] ?? [],
                'task_type' => $filters['task_type'] ?? [],
                'priority_quadrant' => $filters['priority_quadrant'] ?? [],
                'due' => $filters['due'] ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * BUSINESS RULE: F-7 — MATCH AGAINST di (title, description_plain), BUKAN
     * description mentah (F-79 — description berisi HTML rich text, description_plain
     * sudah bersih via TaskObserver::saving()). F-34 — WAJIB difilter permission:
     * member cuma menemukan task dari project yang dia ikuti, admin lintas project
     * bebas (organization_id sudah tersaring lewat OrganizationScope).
     *
     * MAGIC NUMBER: 3 huruf minimum — bawaan MySQL ft_min_word_len (B9). Kata
     * lebih pendek TIDAK di-fallback ke LIKE %...% (full table scan, tidak scale
     * di 5rb+ task/tahun) — cukup pesan jelas ke user.
     *
     * F-83/C3 (Hari-7): cabang sqlite/LIKE yang dulu di sini SUDAH DIHAPUS.
     * Alasannya sudah tidak berlaku — test search sekarang jalan di MySQL asli
     * (tests/Search/SearchTest.php, phpunit.xml testsuite "Search"), jadi jalur
     * MATCH AGAINST teruji betulan, bukan LIKE yang cuma teruji di sqlite dan
     * TIDAK PERNAH tersentuh di produksi (F-7 — produksi selalu MySQL). Satu
     * jalur kode = satu perilaku diuji = perilaku yang benar-benar jalan.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 3) {
            return response()->json([
                'message' => 'Kata pencarian minimal 3 huruf.',
                'results' => [],
            ]);
        }

        $user = $request->user();

        $query = Task::query()->with(['project:id,name', 'taskStatus:id,name,color']);

        // GUARD: boolean-mode MySQL punya operator sendiri (+ - > < ( ) ~ * "),
        // kalau bocor dari input user bisa mengubah arti query atau meledak
        // sebagai syntax error. Dibuang semua sebelum dikirim ke AGAINST().
        $sanitized = preg_replace('/[+\-><()~*"]/', ' ', $q);
        $query->whereRaw('MATCH(title, description_plain) AGAINST (? IN BOOLEAN MODE)', [$sanitized.'*']);

        // F-90: project.viewAll (bukan isAdmin()) — F-34 filter permission search.
        if (! $user->can('project.viewAll')) {
            $query->whereHas('project.members', fn ($m) => $m->whereKey($user->id));
        }

        $tasks = $query->limit(20)->get();

        return response()->json([
            'message' => null,
            'results' => $tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'project_id' => $task->project_id,
                'title' => $task->title,
                'project_name' => $task->project->name,
                'status_name' => $task->taskStatus->name,
                'status_color' => $task->taskStatus->color,
                'snippet' => Str::limit($task->description_plain ?? '', 150),
            ]),
        ]);
    }

    public function create(Project $project): Response
    {
        return Inertia::render('tasks/create', [
            'project' => $project->only(['id', 'name']),
            'members' => $project->members()->select('users.id', 'users.name')->orderBy('users.name')->get(),
        ]);
    }

    /**
     * BUSINESS RULE: F-45/D7 — status task baru = status dengan position TERKECIL
     * di project ini (bukan hardcode 'TODO', F-44).
     */
    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project) {
            $statusId = TaskStatus::where('project_id', $project->id)->orderBy('position')->value('id');

            $task = Task::create([
                ...$request->safe()->except(['assignees', 'checklist_items']),
                'project_id' => $project->id,
                'task_status_id' => $statusId,
                'created_by' => Auth::id(),
            ]);

            // F-51: sync() (bukan query manual ke task_user) supaya TaskUserObserver
            // menangkap event assigned untuk tiap assignee.
            $task->assignees()->sync($request->validated('assignees') ?? []);

            // Revisi 2026-08-06 item 5: checklist ("subtask" ringan, F-123) diisi
            // LANGSUNG saat create -- pola IDENTIK TaskTemplateController::syncChecklistItems()
            // (F-109, nol logic baru). Task BARU dijamin belum punya checklist apa pun,
            // jadi aman create langsung tanpa hapus-dulu (beda dari update() template
            // yang perlu hapus-lalu-buat-ulang).
            foreach (array_values($request->validated('checklist_items') ?? []) as $position => $text) {
                $task->checklistItems()->create([
                    'organization_id' => $project->organization_id,
                    'text' => $text,
                    'position' => $position,
                ]);
            }
        });

        return to_route('tasks.index', $project);
    }

    public function edit(Project $project, Task $task): Response
    {
        $task->load('assignees:id,name');

        return Inertia::render('tasks/edit', [
            'project' => $project->only(['id', 'name']),
            'task' => $task,
            'assigneeIds' => $task->assignees->pluck('id'),
            'members' => $project->members()->select('users.id', 'users.name')->orderBy('users.name')->get(),
        ]);
    }

    /**
     * BUG FIX (2026-08-08, permintaan Boss): task_type task hasil recurring
     * (task_template_id terisi) DIKUNCI -- tidak pernah ikut ditulis di sini,
     * berapa pun nilainya di request (lihat UpdateTaskRequest RISIKO). Sebelum
     * fix ini, task_type='daily'/'weekly'/'monthly' bikin SELURUH form edit
     * gagal validasi, jadi field lain (judul dkk) ikut tidak tersimpan.
     */
    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        DB::transaction(function () use ($request, $task) {
            $excluded = ['assignees'];
            if ($task->task_template_id) {
                $excluded[] = 'task_type';
            }

            $task->update($request->safe()->except($excluded));
            $task->assignees()->sync($request->validated('assignees') ?? []);
        });

        return to_route('tasks.index', $project);
    }

    /**
     * BUSINESS RULE: F-16 — soft delete, data KPI tidak boleh hilang. D6 — hapus
     * parent WAJIB ikut soft-delete semua subtask-nya (konfirmasi jumlah di frontend
     * SEBELUM submit, memakai children yang sudah nested di halaman index).
     */
    public function destroy(Project $project, Task $task): RedirectResponse
    {
        DB::transaction(function () use ($task) {
            $task->children()->each(fn (Task $child) => $child->delete());
            $task->delete();
        });

        return to_route('tasks.index', $project);
    }

    /**
     * BUSINESS RULE: E1/E2 — transisi status "biasa". Admin & member (member cuma
     * task yang di-assign ke dia) — validasi F-45 + guard is_review sepenuhnya di
     * TaskTransitionService supaya satu tempat saja yang menegakkan aturan ini.
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Project $project, Task $task, TaskTransitionService $service): RedirectResponse
    {
        $targetStatus = TaskStatus::findOrFail($request->validated('task_status_id'));
        $service->changeStatus($task, $targetStatus, $request->user());

        return back();
    }

    /**
     * BUSINESS RULE: E4/F-28 — approve, admin only (dijaga middleware 'admin' di
     * routes/admin.php + ApproveTaskRequest::authorize()).
     */
    public function approve(ApproveTaskRequest $request, Project $project, Task $task, TaskTransitionService $service): RedirectResponse
    {
        $service->approve($task, $request->user(), $request->validated('quality_rating'));

        return to_route('tasks.index', $project);
    }

    /**
     * BUSINESS RULE: F-35 trigger #8 — alasan WAJIB diisi, dipakai TaskObserver
     * susun notifikasi "ditolak + alasan" ke assignee. Bukan kolom DB (lihat
     * Task::$rejectionReasonTransient), jadi validasi inline di sini cukup.
     */
    public function reject(Request $request, Project $project, Task $task, TaskTransitionService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('task.approve'), 403); // F-90/F-28
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $service->reject($task, $validated['reason']);

        return to_route('tasks.index', $project);
    }

    /**
     * BUSINESS RULE (H7/F-132/F-138): 4 tombol detail task, assignee-only (F-95,
     * ditegakkan DI SERVICE — lihat TaskTransitionService::assertAssignee()).
     * Nol FormRequest terpisah -- tidak ada body yang perlu divalidasi, seluruh
     * aturan (status/segmen) ada di service. `back()` (bukan to_route) supaya
     * user tetap di halaman detail task yang sama setelah klik tombol.
     */
    public function start(Request $request, Project $project, Task $task, TaskTransitionService $service): RedirectResponse
    {
        $service->start($task, $request->user());

        return back();
    }

    public function hold(Request $request, Project $project, Task $task, TaskTransitionService $service): RedirectResponse
    {
        $service->hold($task, $request->user());

        return back();
    }

    public function resumeWork(Request $request, Project $project, Task $task, TaskTransitionService $service): RedirectResponse
    {
        $service->resume($task, $request->user());

        return back();
    }

    public function submit(Request $request, Project $project, Task $task, TaskTransitionService $service): RedirectResponse
    {
        $service->submit($task, $request->user());

        return back();
    }

    /**
     * KONTRAK: alias withCount() checklist (F-123) DIPAKAI Task::progressPercent()
     * (revisi 2026-08-06 item 1, F-85 — nol N+1 di listing banyak task). Method
     * static dipanggil SAMA PERSIS dari index()/all()/myTasks() supaya alias
     * checklist_items_count/checklist_done_items_count TIDAK bisa drift antar
     * listing (satu definisi, F-109).
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    private static function withChecklistCounts($query)
    {
        return $query->withCount([
            'checklistItems as checklist_items_count',
            'checklistItems as checklist_done_items_count' => fn ($q) => $q->where('is_done', true),
        ]);
    }
}
