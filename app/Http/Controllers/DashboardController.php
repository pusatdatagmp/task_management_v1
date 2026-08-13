<?php

/**
 * ==========================================================
 * MODUL       : DashboardController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Dashboard tim admin/owner (02-DATA-MODEL §5, F-52). `index()` =
 *               halaman Inertia (v0.8 H3), `summary()` = JSON data mentah (v0.8
 *               H2, dipertahankan — pola sama TaskController::search()). Keduanya
 *               berbagi SATU jalur pengambilan data (loadRows()) supaya angka yang
 *               dirender halaman selalu identik dengan yang dikembalikan JSON —
 *               nol rumus dihitung ulang di sini, semua dari DashboardService.
 *               v1.2 H3 Fase A (F-121 aditif): `commandCenter()` MENAMBAH widget
 *               agregasi (donut prioritas, distribusi progress, kategori tugas,
 *               heatmap kalender F-131, top-10, recent activity, workload top-5) —
 *               TIDAK mengubah/menghapus index()/summary()/loadRows() lama.
 *               Addendum Fase A (blueprint §7.1): `summary_cards` — 6 kartu ringkas
 *               (beban harian X/Y, To Do, In Progress, Review, Selesai, Overdue),
 *               SELURUHNYA reuse DashboardService/progressDistribution() yang sudah
 *               ada. Kartu "Selesai" ditambah 2026-08-08 (permintaan Boss).
 *               v1.2 H4 (F-109/F-121): `commandCenterPage()` MERENDER halaman Inertia
 *               "Command Center" dari array yang SAMA dengan commandCenter() JSON
 *               (diekstrak ke commandCenterPayload(), F-85 nol query dobel) —
 *               dashboard 3-angka lama (route `dashboard`, index()) TETAP hidup
 *               tak berubah, halaman baru cuma menambah TAMPILAN gabungan.
 *               v1.2 DS-4 (F-109/§12.5): filter PER-WIDGET periode+user di
 *               commandCenterPayload() — {widget}_from/{widget}_to/{widget}_user_id
 *               untuk donut/progress/categories/top_tasks (due_date) & activity
 *               (created_at); heatmap_user_id & workload_user_id (user-only, lihat
 *               KONTRAK masing-masing method kenapa keduanya TIDAK dapat periode).
 *               v1.2 DS-4b (F-148/§12.5): filter *_user_id di-cast (int) sebelum
 *               dikirim ke frontend — kontrak API jujur ke TS `number | null`
 *               (sebelumnya lolos validate() tapi balik sbg string). Widget baru
 *               `status_projects` — top-5 proyek tak diarsip, COUNTS per FLAG F-44
 *               (statusProjects(), F-85 nol N+1) — SENGAJA counts, BUKAN derivasi
 *               status-label proyek (F-125, di luar scope, tugas halaman Proyek).
 * DIPANGGIL   : routes/admin.php (gated can:dashboard.view)
 * MEMANGGIL   : DashboardService, User, Task, Project, ActivityLog, ActivityLogPresenter
 * DATA MASUK  : query string ?date=Y-m-d (opsional, default hari ini WIB) +
 *               ?user_id= (opsional, filter satu user — F-52/A6). commandCenter()/
 *               commandCenterPage() tambahan ?month=Y-m (opsional, default bulan
 *               berjalan — grid heatmap F-131) + filter per-widget DS-4 (lihat atas).
 * DATA KELUAR : index() -> Inertia props (date, selectedUserId, users[], rows[]).
 *               summary() -> JSON setara, dipertahankan dari H2.
 *               commandCenter() -> JSON widget command-center (lihat DoD H3 Fase A).
 *               commandCenterPage() -> Inertia props SAMA + `team` (Beban Tim, F-52).
 * RISIKO      : SUMBER F-95 — permission dashboard.view HANYA admin (seeder),
 *               member NOL permission, digerbangi middleware can:dashboard.view
 *               di route, BUKAN dicek manual di sini (F-90). F-4: commandCenter()
 *               TIDAK BOLEH mengeluarkan rupiah/skor-kinerja — prio_score di top_tasks
 *               HANYA bobot urutan Eisenhower (F-122), bukan skor kinerja.
 *               Revisi 2026-08-06 (permintaan Boss): Command Center (commandCenter()/
 *               commandCenterPage() SAJA — index()/summary()/loadRows() dashboard
 *               3-angka lama TIDAK disentuh, tetap tampil semua user) DIBATASI ke
 *               data viewer SENDIRI kalau dia TIDAK punya project.viewAll (izin
 *               yang SUDAH ADA, pola pembeda SAMA dipakai Semua Tugas/Search F-34/
 *               F-140 — admin selalu punya, role custom lain default TIDAK).
 *               $restrictToSelf FORCE seluruh filter *_user_id (abaikan apa pun
 *               yang dikirim query string — SERVER guard, bukan HINT UI, supaya
 *               viewer terbatas tidak bisa intip user lain lewat manipulasi URL)
 *               + kosongkan status_projects (widget per-proyek, nol makna "punya
 *               siapa" untuk viewer terbatas, keputusan Boss). commandCenterPayload()
 *               kirim balik 'restricted_to_self' supaya frontend tahu kapan
 *               sembunyikan widget/dropdown yang tidak relevan.
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\DeadlineExtension;
use App\Models\Holiday;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\ActivityLogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $service): Response
    {
        [$date, $rows] = $this->loadRows($request, $service);

        // Roster LENGKAP (tidak ikut filter user_id) -- dipakai dropdown filter
        // A6, supaya user yang sedang di-filter tetap muncul sebagai pilihan
        // untuk KEMBALI ke "semua user".
        $allUsers = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('dashboard', [
            'date' => $date->toDateString(),
            'selectedUserId' => $request->filled('user_id') ? $request->integer('user_id') : null,
            'users' => $allUsers,
            'rows' => $rows,
        ]);
    }

    public function summary(Request $request, DashboardService $service): JsonResponse
    {
        [$date, $rows] = $this->loadRows($request, $service);

        return response()->json([
            'date' => $date->toDateString(),
            'users' => $rows,
        ]);
    }

    /**
     * KONTRAK: v1.2 H3 Fase A — JSON agregasi untuk widget "Command Center"
     * (BLUEPRINT-UIUX-v1.7 §7.1). SETIAP sub-widget REUSE service/presenter yang
     * SUDAH ADA (F-109/F-121) — NOL rumus KPI baru ditulis di sini, method ini
     * cuma menyusun/menyaring hasilnya jadi bentuk tampilan. Dashboard 3-angka lama
     * (index()/summary()/loadRows()) TIDAK disentuh — endpoint TERPISAH (F-121).
     */
    public function commandCenter(Request $request, DashboardService $service): JsonResponse
    {
        // Revisi 2026-08-06: viewer TANPA project.viewAll dibatasi ke data
        // sendiri (lihat RISIKO header modul).
        $restrictToSelf = ! $request->user()->can('project.viewAll');

        return response()->json($this->commandCenterPayload($request, $service, $restrictToSelf));
    }

    /**
     * KONTRAK: v1.2 H4 — halaman Inertia "Command Center" (BLUEPRINT-UIUX-v1.7
     * §7.1), gated `dashboard.view` sama seperti index()/commandCenter(). REUSE
     * commandCenterPayload() (F-109 — SATU sumber array yang sama dikirim endpoint
     * JSON `dashboard.command-center`, bukan disusun ulang) + loadRows() (SATU
     * sumber yang sama dipakai index() 3-angka lama, F-52) untuk section
     * "Beban Tim". F-121: dashboard 3-angka lama TETAP hidup sebagai halaman
     * mandiri di route `dashboard` (tidak dihapus) — halaman ini cuma menambah
     * TAMPILAN gabungan, section "Beban Tim" di sini read-only hari ini (tanpa
     * filter tanggal/user — kontrol penuh tetap di halaman lama).
     */
    public function commandCenterPage(Request $request, DashboardService $service): Response
    {
        // Revisi 2026-08-06: viewer TANPA project.viewAll dibatasi ke data
        // sendiri -- berlaku juga ke section "Beban Tim" (loadRows() di sini),
        // TAPI index()/summary() (dashboard 3-angka lama, route terpisah) TIDAK
        // memanggil dengan flag ini sama sekali, jadi TETAP tampil semua user
        // (keputusan Boss: restriksi cuma di Command Center).
        $restrictToSelf = ! $request->user()->can('project.viewAll');
        [$teamDate, $teamRows] = $this->loadRows($request, $service, $restrictToSelf);

        return Inertia::render('command-center', [
            ...$this->commandCenterPayload($request, $service, $restrictToSelf),
            'team' => [
                'date' => $teamDate->toDateString(),
                // SUMBER (permintaan Boss): dikirim balik SEMATA supaya modal
                // "Detail & filter" bisa render <input>/<select> TERKONTROL --
                // ?user_id= di sini REUSE param yang SAMA dibaca loadRows() di
                // atas, nol filter/param baru di backend. Viewer terbatas: SELALU
                // dirinya sendiri, abaikan ?user_id= yang dikirim (server guard).
                'selected_user_id' => $restrictToSelf ? $request->user()->id : ($request->filled('user_id') ? $request->integer('user_id') : null),
                'rows' => $teamRows,
            ],
        ]);
    }

    /**
     * KONTRAK: array mentah widget command-center — DIPANGGIL DUA jalur
     * (commandCenter() JSON DAN commandCenterPage() Inertia), SATU sumber susunan
     * (F-109/F-121) supaya angka yang dirender halaman selalu identik dengan yang
     * dikembalikan endpoint JSON. Isi/urutan widget TIDAK berubah dari sebelum
     * refactor ini — cuma dipindah dari inline response()->json() ke method
     * terpisah supaya bisa dipakai ulang.
     *
     * v1.2 DS-4 (F-109/§12.5): filter per-widget periode+user — SETIAP widget
     * punya pasangan query param sendiri (mis. `donut_from`/`donut_to`/
     * `donut_user_id`), independen dari widget lain (klik filter di satu kartu
     * TIDAK mereset kartu lain). Filter MENYEMPIT WHERE query yang sudah ada
     * (due_date untuk widget berbasis task, created_at untuk activity, assignee/
     * actor untuk user) — NOL rumus KPI baru ditulis. 2 pengecualian SENGAJA
     * (dikonfirmasi Boss, LANGKAH 0):
     * - heatmap: HANYA filter user (bukan periode) -- F-131/F-141 melarang
     *   kontrol tanggal kedua di atas navigasi bulan yang sudah ada.
     * - workload_top5: "periode" = pindah anchor tanggal (reuse `?date=` YANG
     *   SUDAH ADA, bukan param baru) -- F-118 itu proyeksi maju dari SATU
     *   tanggal, bukan agregat rentang; filter user = sempitkan roster $users.
     *
     * Revisi 2026-08-06: $restrictToSelf (viewer TANPA project.viewAll) FORCE
     * setiap filter *_user_id ke id viewer sendiri -- SERVER guard (bukan HINT
     * UI), abaikan apa pun yang dikirim query string. status_projects kosong
     * (widget per-proyek, nol makna "punya siapa" untuk viewer terbatas).
     *
     * @return array<string, mixed>
     */
    private function commandCenterPayload(Request $request, DashboardService $service, bool $restrictToSelf = false): array
    {
        $filterRules = [
            'donut_from' => ['nullable', 'date'], 'donut_to' => ['nullable', 'date'], 'donut_user_id' => ['nullable', 'integer'],
            'progress_from' => ['nullable', 'date'], 'progress_to' => ['nullable', 'date'], 'progress_user_id' => ['nullable', 'integer'],
            // Revisi 2026-08-07 (permintaan Boss): widget "Kategori Tugas Berulang"
            // jadi daftar per-template dgn "jumlah" ALL-TIME (bukan agregat periode)
            // -- categories_from/categories_to dicabut, HANYA sisa filter user.
            'categories_user_id' => ['nullable', 'integer'],
            'top_tasks_from' => ['nullable', 'date'], 'top_tasks_to' => ['nullable', 'date'], 'top_tasks_user_id' => ['nullable', 'integer'],
            'activity_from' => ['nullable', 'date'], 'activity_to' => ['nullable', 'date'], 'activity_user_id' => ['nullable', 'integer'],
            'heatmap_user_id' => ['nullable', 'integer'],
            'workload_user_id' => ['nullable', 'integer'],
            'workload_date' => ['nullable', 'date'],
        ];

        // GUARD: Request::validate() cuma balikin key yang HADIR di input --
        // key opsional yang tak dikirim sama sekali hilang total dari array,
        // bukan null. array_fill_keys(...null) dulu supaya frontend SELALU
        // dapat 16 key (buat controlled <input>/<select> React, F-109) baru
        // ditimpa hasil validate() yang benar-benar terisi.
        $filters = array_merge(array_fill_keys(array_keys($filterRules), null), $request->validate($filterRules));

        // F-148: aturan 'integer' di atas HANYA memvalidasi format, TIDAK
        // meng-cast tipe -- tanpa baris ini filter *_user_id balik ke frontend
        // sbg STRING (dari query string mentah), padahal TS `command-center.tsx`
        // mendeklarasikan `number | null`. Cast eksplisit supaya kontrak API jujur.
        foreach (['donut_user_id', 'progress_user_id', 'categories_user_id', 'top_tasks_user_id', 'activity_user_id', 'heatmap_user_id', 'workload_user_id'] as $userIdKey) {
            if ($filters[$userIdKey] !== null) {
                $filters[$userIdKey] = (int) $filters[$userIdKey];
            }
        }

        $date = $request->query('date')
            ? Carbon::parse((string) $request->query('date'))
            : Carbon::now();
        $today = Carbon::now()->startOfDay();

        // GUARD F-109: workload_date TERPISAH dari ?date= (dipakai loadRows()
        // untuk section "Beban Tim" di commandCenterPage(), request OBJECT YANG
        // SAMA). Kalau widget ini reuse ?date= langsung, selector periode
        // Workload Top-5 diam-diam ikut menggeser tabel Beban Tim yang SENGAJA
        // read-only tanpa filter tanggal (lihat komentar commandCenterPage()) --
        // param sendiri mencegah kopling tak terlihat itu. Fallback ke $date
        // kalau tak diisi -> perilaku IDENTIK sebelum DS-4 saat filter baru ini
        // tak dipakai sama sekali.
        $workloadDate = ($filters['workload_date'] ?? null) ? Carbon::parse((string) $filters['workload_date']) : $date;

        $viewerId = $request->user()->id;

        // Revisi 2026-08-06: roster TIM PENUH HANYA kalau viewer punya
        // project.viewAll -- viewer terbatas cuma dapat DIRINYA SENDIRI di sini,
        // jadi SELURUH turunan roster ini (heatmap/workload/filter_users) di
        // bawah otomatis ikut sempit, nol logic tambahan per widget.
        $users = User::where('is_active', true)
            ->when($restrictToSelf, fn ($q) => $q->whereKey($viewerId))
            ->orderBy('name')
            ->get();

        // Revisi 2026-08-06: viewer terbatas -> SETIAP filter *_user_id
        // DIPAKSA ke dirinya sendiri, TERLEPAS apa pun yang dikirim query
        // string (SERVER guard -- lihat RISIKO header modul, bukan HINT UI).
        $donutUserId = $restrictToSelf ? $viewerId : ($filters['donut_user_id'] ?? null);
        $progressUserId = $restrictToSelf ? $viewerId : ($filters['progress_user_id'] ?? null);
        $categoriesUserId = $restrictToSelf ? $viewerId : ($filters['categories_user_id'] ?? null);
        $topTasksUserId = $restrictToSelf ? $viewerId : ($filters['top_tasks_user_id'] ?? null);
        $activityUserId = $restrictToSelf ? $viewerId : ($filters['activity_user_id'] ?? null);

        // Addendum 5-kartu: progressDistribution() dipanggil SEKALI, dipakai DUA
        // kali (key lama 'progress_distribution' DAN summary_cards di bawah) --
        // supaya nol query dobel (F-85). TIDAK ikut filter WIDGET (§12.5 cuma
        // menyebut 7 widget: donut/progress/kategori/kalender/workload/recent/
        // top-10 -- 5 kartu ringkas TETAP statis, sesuai keputusan Boss
        // 2026-07-29) -- TAPI restriksi 2026-08-06 beda sumbu (batas keamanan,
        // bukan pilihan filter admin), jadi TETAP kena $restrictToSelf.
        $progressDistribution = $this->progressDistribution(null, null, $restrictToSelf ? $viewerId : null);

        // Revisi 2026-08-06: viewer terbatas -> filter heatmap/workload_user_id
        // JUGA diabaikan (bukan cuma dibiarkan menyempitkan $users yang sudah
        // 1 orang jadi KOSONG kalau id yang dikirim bukan dirinya sendiri --
        // itu masih AMAN/nol bocor data, tapi hasilnya "heatmap kosong" padahal
        // seharusnya "heatmap dirinya sendiri"). $restrictToSelf eksplisit
        // menonaktifkan filter ini, konsisten dengan *_user_id lain di atas.
        $heatmapUsers = (! $restrictToSelf && ($filters['heatmap_user_id'] ?? null))
            ? $users->where('id', $filters['heatmap_user_id'])->values()
            : $users;
        $workloadUsers = (! $restrictToSelf && ($filters['workload_user_id'] ?? null))
            ? $users->where('id', $filters['workload_user_id'])->values()
            : $users;

        return [
            'date' => $date->toDateString(),
            'restricted_to_self' => $restrictToSelf,
            'donut_priority' => $this->donutPriority($filters['donut_from'] ?? null, $filters['donut_to'] ?? null, $donutUserId),
            'progress_distribution' => $this->progressDistribution($filters['progress_from'] ?? null, $filters['progress_to'] ?? null, $progressUserId),
            'task_categories' => $this->taskCategories($restrictToSelf, $categoriesUserId),
            'heatmap' => $this->heatmap($service, $heatmapUsers, $request->query('month'), $today),
            'top_tasks' => $this->topTasks($filters['top_tasks_from'] ?? null, $filters['top_tasks_to'] ?? null, $topTasksUserId),
            'recent_activity' => $this->recentActivity($filters['activity_from'] ?? null, $filters['activity_to'] ?? null, $activityUserId),
            // A8/F-96/F-118: workload top-5 REUSE forUsers() -- SATU sumber sama
            // dengan dashboard 3-angka lama, tinggal disortir & dipotong 5.
            // v1.2 DS-4: "periode" widget ini = $date (anchor tunggal, dari ?date=
            // YANG SUDAH ADA sebelum DS-4 -- BUKAN param baru) -- F-118 proyeksi
            // maju dari satu tanggal, bukan agregat rentang (beda dari donut/dst).
            // "user" = $workloadUsers yang sudah disempit di atas.
            'workload_top5' => collect($service->forUsers($workloadUsers, $workloadDate))
                ->map(fn (array $row, int $userId) => ['id' => $userId, 'name' => $workloadUsers->find($userId)?->name, 'beban' => $row['beban']])
                ->sortByDesc('beban')
                ->take(5)
                ->values(),
            'summary_cards' => $this->summaryCards($service, $users, $today, $progressDistribution, $restrictToSelf ? $viewerId : null),
            // v1.2 DS-4 §12.5: widget "Status Project" -- COUNTS per proyek (BUKAN
            // derivasi status-label F-125, itu urusan halaman Proyek nanti).
            // Revisi 2026-08-06: widget ini per-PROYEK, nol makna "punya siapa" --
            // kosong untuk viewer terbatas (keputusan Boss), bukan di-scope.
            'status_projects' => $restrictToSelf ? [] : $this->statusProjects(),
            // F-109: filter aktif dikirim balik supaya frontend bisa render
            // selector ter-isi (pola SAMA ActivityLogController::index() -- state
            // datang dari URL lewat backend, bukan disimpan di localStorage FE).
            'filters' => $filters,
            // F-85: opsi dropdown user filter -- REUSE $users yang SUDAH di-load
            // di atas (nol query tambahan), bukan query User::all() baru.
            'filter_users' => $users->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
        ];
    }

    /**
     * KONTRAK: A2 — jumlah task per priority_quadrant (F-122), termasuk task yang
     * belum diberi quadrant (bucket 'none'). Nol filter status -- donut menunjukkan
     * SELURUH task organisasi (OrganizationScope F-15 otomatis), bukan cuma yang aktif.
     *
     * v1.2 DS-4 (F-109): $from/$to MENYEMPIT ke due_date (widget ini perencanaan,
     * bukan laporan historis -- selaras heatmap/top_tasks yang juga acuan tenggat).
     * $userId MENYEMPIT ke assignee. Ketiganya opsional (null = nol filter, perilaku
     * IDENTIK sebelum DS-4). Nol rumus baru -- cuma WHERE tambahan di query lama.
     *
     * @return array<string, int>
     */
    private function donutPriority(?string $from, ?string $to, ?int $userId): array
    {
        $counts = ['p1' => 0, 'p2' => 0, 'p3' => 0, 'p4' => 0, 'none' => 0];

        Task::query()
            ->select('priority_quadrant')
            ->selectRaw('count(*) as total')
            ->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))
            ->when($userId, fn ($q) => $q->whereHas('assignees', fn ($a) => $a->whereKey($userId)))
            ->groupBy('priority_quadrant')
            ->get()
            ->each(function ($row) use (&$counts) {
                $counts[$row->priority_quadrant ?? 'none'] = (int) $row->total;
            });

        return $counts;
    }

    /**
     * KONTRAK: A3 — jumlah task per bucket status, BERBASIS FLAG (F-44), BUKAN
     * nama status. Urutan cek WAJIB completed > review > work_state > todo (satu
     * task hanya masuk SATU bucket -- kalau tanpa urutan, status dengan >1 flag true
     * bisa dihitung dobel). 4 query TETAP, tidak tumbuh dengan jumlah task (F-85).
     *
     * v1.2 DS-4 (F-109): $from/$to/$userId sama seperti donutPriority() -- null
     * saat dipanggil untuk summary_cards (TIDAK ikut filter, §12.5), terisi saat
     * dipanggil untuk widget progress_distribution.
     *
     * @return array<string, int>
     */
    private function progressDistribution(?string $from, ?string $to, ?int $userId): array
    {
        $scope = fn ($q) => $q
            ->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))
            ->when($userId, fn ($q) => $q->whereHas('assignees', fn ($a) => $a->whereKey($userId)));

        return [
            'selesai' => Task::whereHas('taskStatus', fn ($q) => $q->where('is_completed', true))->tap($scope)->count(),
            'review' => Task::whereHas('taskStatus', fn ($q) => $q->where('is_completed', false)->where('is_review', true))->tap($scope)->count(),
            'progress' => Task::whereHas('taskStatus', fn ($q) => $q->where('is_completed', false)->where('is_review', false)->where('is_work_state', true))->tap($scope)->count(),
            'todo' => Task::whereHas('taskStatus', fn ($q) => $q->where('is_completed', false)->where('is_review', false)->where('is_work_state', false))->tap($scope)->count(),
        ];
    }

    /**
     * KONTRAK: addendum 5 kartu ringkas (blueprint §7.1, kelalaian Fase A yang
     * ditutup Boss 2026-07-25). SETIAP angka REUSE sumber F-118/F-44 yang SUDAH
     * ADA — nol rumus KPI baru (F-109/F-4). Anchor SELALU $today (hari ini
     * absolut, SAMA dengan batas netral heatmap F-131) — BUKAN $date (query
     * param ?date= dashboard 3-angka lama), supaya kartu tidak ikut bergeser
     * kalau admin sedang melihat tanggal lain di dashboard lama.
     *
     * @param  array<string, int>  $progressDistribution  hasil progressDistribution(), REUSE dari commandCenter() (F-85, nol query dobel).
     * @param  int|null  $userId  revisi 2026-08-06 -- viewer terbatas (project.viewAll) diteruskan ke overdueCount(); null = agregat org penuh (perilaku lama).
     * @return array{beban_harian: array{used_minutes:int, capacity_minutes:int}, todo:int, in_progress:int, review:int, selesai:int, overdue:int}
     */
    private function summaryCards(DashboardService $service, Collection $users, Carbon $today, array $progressDistribution, ?int $userId = null): array
    {
        // F-118 SATU SUMBER: dailyLoadTotals() -- method publik yang sama dipakai
        // heatmap (A5) -- dipanggil ULANG di sini (bukan reuse hasil heatmap)
        // karena heatmap menyaring tanggal berdasar ?month= (bisa bulan lain),
        // sedang kartu ini WAJIB selalu "hari ini" apa pun bulan yang sedang dilihat.
        $usedMinutes = array_sum($service->dailyLoadTotals($users, collect([$today]), $today));

        // Kapasitas: method publik yang sama dipakai forUsers() (F-40 versioned
        // WorkSchedule) -- SATU sumber kapasitas, bukan dihitung ulang.
        $capacityMinutes = array_sum($service->kapasitas($users, $today));

        return [
            'beban_harian' => [
                'used_minutes' => $usedMinutes,
                'capacity_minutes' => $capacityMinutes,
            ],
            'todo' => $progressDistribution['todo'],
            'in_progress' => $progressDistribution['progress'],
            'review' => $progressDistribution['review'],
            // 2026-08-08 (permintaan Boss): kartu "Tugas Selesai" -- angkanya
            // SUDAH dihitung progressDistribution() ('selesai', baris 373), cuma
            // belum pernah ikut disodorkan ke summary_cards. Nol query baru.
            'selesai' => $progressDistribution['selesai'],
            // F-44: pola IDENTIK TaskController::search() filter 'overdue' -- due_date
            // < sekarang DAN belum completed. REUSE definisi, bukan definisi baru.
            'overdue' => $this->overdueCount($userId),
        ];
    }

    /**
     * KONTRAK: jumlah task belum-selesai dengan due_date sudah lewat -- pola
     * IDENTIK TaskController::search() filter 'overdue' (F-44 flag, bukan nama
     * status) dan BoardController::due_status.
     *
     * @param  int|null  $userId  revisi 2026-08-06 -- terisi utk viewer terbatas (menyempit ke assignee), null = agregat org penuh (perilaku lama).
     */
    private function overdueCount(?int $userId = null): int
    {
        return Task::where('due_date', '<', Carbon::now())
            ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false))
            ->when($userId, fn ($q) => $q->whereHas('assignees', fn ($a) => $a->whereKey($userId)))
            ->count();
    }

    /**
     * KONTRAK: v1.2 DS-4 §12.5 widget "Status Project" -- top-5 proyek TAK
     * DIARSIP, kolom Task/Todo/Progress/Review/Selesai/Overdue = COUNTS per
     * FLAG F-44 (pola IDENTIK progressDistribution()/overdueCount(), BUKAN
     * rumus baru) + Deadline (projects.due_date, F-125). SENGAJA COUNTS,
     * BUKAN status-label derived -- derivasi label proyek (F-125) itu tugas
     * halaman Proyek nanti, di luar scope widget ini.
     *
     * F-85: withCount() menyusun SETIAP kolom sbg subquery tunggal di SATU
     * SELECT (bukan query per-project) -- N+1 KONSTAN walau proyek bertambah.
     *
     * "Top-5" diurut task_total DESC (proyek paling sibuk duluan) -- default
     * backend, lalu 5 baris ini yang sama di-SORT ULANG di frontend per kolom
     * (klik header), bukan fetch ulang top-5 lain per kriteria.
     *
     * BUG FIX (ditemukan audit Boss 2026-08-07): kolom `review_count` TIDAK
     * ADA sebelumnya -- task berstatus Review (is_review=true) tidak masuk
     * todo/progress/selesai manapun (semua 3 query eksplisit `is_review=false`
     * atau tidak relevan), padahal tetap ke-count di `task_total`. Akibatnya
     * todo+progress+selesai < task_total setiap kali ada task sedang di-review
     * (dibuktikan data nyata: project "mbaleni" total=5, sum lama=4). Widget
     * sebelah ("Distribusi Progress", progressDistribution() di atas) SUDAH
     * benar py 4 kategori sejak awal -- widget ini yang kelupaan satu.
     *
     * @return array<int, array<string, mixed>>
     */
    private function statusProjects(): array
    {
        return Project::where('is_archived', false)
            ->withCount([
                'tasks as task_total',
                'tasks as todo_count' => fn ($q) => $q->whereHas('taskStatus', fn ($s) => $s->where('is_completed', false)->where('is_review', false)->where('is_work_state', false)),
                'tasks as progress_count' => fn ($q) => $q->whereHas('taskStatus', fn ($s) => $s->where('is_completed', false)->where('is_review', false)->where('is_work_state', true)),
                'tasks as review_count' => fn ($q) => $q->whereHas('taskStatus', fn ($s) => $s->where('is_completed', false)->where('is_review', true)),
                'tasks as selesai_count' => fn ($q) => $q->whereHas('taskStatus', fn ($s) => $s->where('is_completed', true)),
                'tasks as overdue_count' => fn ($q) => $q->where('due_date', '<', Carbon::now())->whereHas('taskStatus', fn ($s) => $s->where('is_completed', false)),
            ])
            ->orderByDesc('task_total')
            ->limit(5)
            ->get(['id', 'name', 'due_date'])
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'task_total' => $p->task_total,
                'todo' => $p->todo_count,
                'progress' => $p->progress_count,
                'review' => $p->review_count,
                'selesai' => $p->selesai_count,
                'overdue' => $p->overdue_count,
                'due_date' => $p->due_date,
            ])
            ->all();
    }

    /**
     * KONTRAK: A4 — widget "Kategori Tugas Berulang". Revisi 2026-08-07
     * (permintaan Boss, 3 iterasi hari ini):
     *   1) semula breakdown SEMUA task per task_type -> dipersempit ke task
     *      hasil generate template saja (task_template_id terisi).
     *   2) "kok masih daily/weekly/monthly?" -- ternyata Boss mau bentuk
     *      berbeda TOTAL: bukan grouped-by-label, tapi DAFTAR PER TEMPLATE
     *      (nama, ringkasan jadwal, jumlah task yang PERNAH lahir ALL-TIME
     *      dari template itu -- dikonfirmasi Boss, TIDAK ikut filter periode).
     *   3) "template saya ada 2, kok cuma 1 yang tampil?" -- semula HANYA
     *      template yang punya >=1 task cocok yang muncul (kategori kosong =
     *      tak tampil, warisan pola grouping lama). Boss klarifikasi: SEMUA
     *      template AKTIF wajib tampil, walau jumlah 0 (belum pernah
     *      di-generate `automation:run`).
     * Makanya param $from/$to DICABUT (beda dari widget periode lain) --
     * "jumlah" di sini sengaja bukan angka ter-filter tanggal.
     *
     * GUARD privasi (konsisten pola $restrictToSelf di seluruh file ini):
     * "SEMUA template aktif tampil" BUKAN berarti viewer terbatas (tanpa
     * project.viewAll) boleh intip nama SELURUH template organisasi walau
     * tak py task di situ -- itu bocor struktur kerja tim ke orang yang
     * seharusnya cuma lihat "data sendiri". Jadi filter tampil/sembunyi
     * (bukan jumlah) TETAP dijaga guard $restrictToSelf: viewer penuh lihat
     * SEMUA template aktif (termasuk jumlah 0), viewer terbatas HANYA
     * template yang py minimal 1 task assigned ke dirinya.
     *
     * 2 query TETAP (F-85): (1) template aktif + count task via withCount
     * (SATU query, subquery correlated -- bukan N+1), (2) tidak ada query
     * kedua sungguhan, withCount cukup.
     *
     * @return array<int, array{id: int, title: string, schedule_label: string, total: int}>
     */
    private function taskCategories(bool $restrictToSelf, ?int $userId): array
    {
        return TaskTemplate::query()
            ->where('is_active', true)
            ->withCount(['tasks as total' => fn ($q) => $q->when($userId, fn ($q2) => $q2->whereHas('assignees', fn ($a) => $a->whereKey($userId)))])
            // GUARD: schedule_label (accessor) BUTUH anchor_strategy/interval_value/
            // interval_unit/anchor_config -- select parsial yang tidak menyertakan
            // kolom itu bikin accessor baca NULL diam-diam (F-4, ditemukan dari test
            // gagal: label jatuh ke cabang fallback yang salah tanpa error apa pun).
            ->get(['id', 'title', 'anchor_strategy', 'interval_value', 'interval_unit', 'anchor_config'])
            ->filter(fn (TaskTemplate $template) => ! $restrictToSelf || $template->total > 0)
            ->map(fn (TaskTemplate $template) => [
                'id' => $template->id,
                'title' => $template->title,
                'schedule_label' => $template->schedule_label,
                'total' => (int) $template->total,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * KONTRAK: A5 — grid heatmap SATU BULAN (F-131). Hari LEWAT (< $today) SELALU
     * netral (beban/level null) -- TIDAK PERNAH dihitung, bukan cuma disembunyikan
     * di tampilan (menjaga batas beban-vs-realisasi F-94). Hanya tanggal >= $today
     * yang dilempar ke DashboardService::dailyLoadTotals() (F-118, satu sumber).
     * Threshold F-128 per-user (aman<210/tengah<420/overload>=420) DIKALI jumlah
     * user aktif untuk ambang AGREGAT (heatmap ini beban SELURUH tim per hari).
     *
     * v1.2 DS-4 (F-109/F-131): filter "user" heatmap TIDAK nambah parameter di
     * sini -- caller (commandCenterPayload()) sudah menyempitkan $users SEBELUM
     * dipanggil (mis. heatmap_user_id -> 1 user saja). Threshold F-128 otomatis
     * ikut skala turun karena dikali active_user_count = $users->count() yang
     * sudah kecil. SENGAJA TANPA selector periode (beda dari widget lain) --
     * navigasi bulan prev/next SUDAH jadi satu-satunya kontrol waktu; kontrol
     * kedua akan tabrakan & berisiko melanggar F-131/F-141 (hari-lewat wajib
     * netral apa pun filternya).
     *
     * Permintaan Boss: modal "detail acara/peristiwa" saat tanggal diklik --
     * `holiday`/`meetings` dihitung untuk SEMUA hari (termasuk yang LEWAT, beda
     * dari beban/level) karena ini FAKTA kalender (nama libur, jadwal meeting),
     * BUKAN angka KPI yang tunduk F-131 "hari lewat netral". `type` cuma flag
     * ikon (BUKAN sumber baru F-44 -- turunan holiday/meetings yang SAMA dikirim
     * di bawah, nol logic ganda). 2 query TETAP (holidays + meetings sebulan,
     * F-85 -- konstan, bukan per-hari/per-baris).
     *
     * Permintaan Boss (2026-08-10): hari Minggu OTOMATIS ikon 'libur' + label
     * "Hari Minggu" -- DIHITUNG saat render (Carbon::isSunday()), BUKAN baris
     * Holiday di DB (F-38-style, nol scheduler). MURNI tampilan -- beban/level
     * TETAP 100% dari WorkSchedule.days_of_week org, dua konsep sengaja terpisah.
     *

     * @return array{month: string, days: array<int, array{date:string, beban:?int, level:?string, type:?string, holiday:?string, meetings:array<int,array<string,mixed>>}>, active_user_count:int}
     */
    private function heatmap(DashboardService $service, Collection $users, ?string $monthParam, Carbon $today): array
    {
        $month = $monthParam
            ? Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth()
            : $today->copy()->startOfMonth();

        $firstDay = $month->copy()->startOfMonth();
        $lastDay = $month->copy()->endOfMonth();

        $futureDates = collect();
        $cursor = $firstDay->copy();
        while ($cursor->lessThanOrEqualTo($lastDay)) {
            if ($cursor->greaterThanOrEqualTo($today)) {
                $futureDates->push($cursor->copy());
            }
            $cursor->addDay();
        }

        $loads = $service->dailyLoadTotals($users, $futureDates, $today);
        $activeUserCount = $users->count();
        $tengahFloor = 210 * $activeUserCount;
        $overloadFloor = 420 * $activeUserCount;

        // SUMBER: nama libur PER TANGGAL, 1 query utuh sebulan (OrganizationScope
        // F-15 otomatis) -- dipakai ikon 'libur' (F-43) DAN isi modal detail.
        $holidays = Holiday::query()
            ->whereBetween('date', [$firstDay->toDateString(), $lastDay->toDateString()])
            ->get(['date', 'name'])
            ->keyBy(fn (Holiday $h) => $h->date->toDateString());

        // SUMBER: meeting PER TANGGAL (berdasar start_at), 1 query utuh sebulan +
        // eager load creator/project/participants (F-85, nol N+1 walau meeting
        // bertambah). Fitur Meeting (F-124) baru model H2 -- CRUD/UI-nya belum
        // dibangun (H6 di roadmap), jadi array ini WAJAR kosong sampai H6 selesai.
        $meetingsByDate = Meeting::query()
            ->whereBetween('start_at', [$firstDay->copy()->startOfDay(), $lastDay->copy()->endOfDay()])
            ->with(['creator:id,name', 'project:id,name', 'participants:id,name'])
            ->get()
            ->groupBy(fn (Meeting $m) => $m->start_at->toDateString());

        $days = [];
        $cursor = $firstDay->copy();
        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $key = $cursor->toDateString();
            // Permintaan Boss (2026-08-10): Minggu OTOMATIS ikon 'libur' -- TANPA
            // baris Holiday manual di DB (F-38-style: dihitung saat ditanya, bukan
            // di-materialize/di-scheduler tiap minggu ke masa depan). SENGAJA
            // MURNI TAMPILAN ikon+label modal detail -- beban/level (workloadSpread()/
            // dailyLoadTotals() di atas) TIDAK disentuh, tetap 100% tunduk
            // WorkSchedule.days_of_week org (kalau admin justru MENCENTANG Minggu
            // sebagai hari kerja, beban tetap dihitung hari itu apa adanya --
            // dua konsep sengaja dipisah, keputusan Boss). Holiday manual (mis.
            // nama libur nasional yang KEBETULAN jatuh hari Minggu) TETAP MENANG
            // -- ?? cuma isi kalau belum ada nama dari DB.
            $holidayName = $holidays->get($key)?->name ?? ($cursor->isSunday() ? 'Hari Minggu' : null);
            $meetings = ($meetingsByDate->get($key) ?? collect())
                ->map(fn (Meeting $m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'description' => $m->description,
                    'start_at' => $m->start_at,
                    'end_at' => $m->end_at,
                    'project' => $m->project?->name,
                    'creator' => $m->creator?->name,
                    'participants' => $m->participants->pluck('name'),
                ])
                ->values()
                ->all();
            // SUMBER: 'libur' menang atas 'meeting' kalau dua-duanya ada tanggal
            // SAMA (ikon tunggal per sel, pola SAMA sketsa ikon Boss) -- modal
            // detail TETAP tampilkan KEDUANYA (holiday+meetings), cuma ikon sel
            // kalender yang harus pilih satu.
            $type = $holidayName ? 'libur' : (count($meetings) > 0 ? 'meeting' : null);

            if ($cursor->lessThan($today)) {
                // F-131: hari lewat NETRAL -- beban/level TIDAK diisi sama sekali.
                $days[] = ['date' => $key, 'beban' => null, 'level' => null, 'type' => $type, 'holiday' => $holidayName, 'meetings' => $meetings];
            } else {
                $beban = $loads[$key] ?? 0;
                $level = match (true) {
                    $beban >= $overloadFloor => 'overload',
                    $beban >= $tengahFloor => 'tengah',
                    default => 'aman',
                };
                $days[] = ['date' => $key, 'beban' => $beban, 'level' => $level, 'type' => $type, 'holiday' => $holidayName, 'meetings' => $meetings];
            }

            $cursor->addDay();
        }

        return [
            'month' => $firstDay->format('Y-m'),
            'days' => $days,
            'active_user_count' => $activeUserCount,
        ];
    }

    /**
     * KONTRAK: A7 — top-10 task belum selesai (F-44: is_completed=false), urut
     * `prio_score` (bobot Eisenhower p1=4..p4=1, F-122/F-4 -- BUKAN skor kinerja,
     * cuma bobot sortir) lalu due_date. Bobot dihitung via SQL CASE (F-77 -- hindari
     * urutan alfabetis yang salah kalau di-order string biasa).
     *
     * v1.2 DS-4 (F-109): $from/$to/$userId sama seperti donutPriority().
     *
     * Permintaan Boss: tabel Top-10 di frontend BUTUH kolom Kategori (task_type)
     * & Status (nama+warna task_status AKTUAL, F-44 -- bukan bucket hardcode)
     * supaya bisa disortir per kolom. `task_status_id` WAJIB ikut di select()
     * eksplisit ini (bukan otomatis) -- FK relasi taskStatus() tidak ikut kebawa
     * kalau kolomnya sendiri tidak diminta.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topTasks(?string $from, ?string $to, ?int $userId): array
    {
        $weightCase = "CASE priority_quadrant WHEN 'p1' THEN 4 WHEN 'p2' THEN 3 WHEN 'p3' THEN 2 WHEN 'p4' THEN 1 ELSE 0 END";

        return Task::query()
            ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false))
            ->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))
            ->when($userId, fn ($q) => $q->whereHas('assignees', fn ($a) => $a->whereKey($userId)))
            ->orderByRaw("{$weightCase} DESC")
            ->orderBy('due_date')
            ->with(['assignees:id,name', 'project:id,name', 'taskStatus:id,name,color'])
            ->limit(10)
            ->get(['id', 'title', 'priority_quadrant', 'due_date', 'project_id', 'task_type', 'task_status_id'])
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'priority_quadrant' => $task->priority_quadrant,
                'prio_score' => match ($task->priority_quadrant) {
                    'p1' => 4, 'p2' => 3, 'p3' => 2, 'p4' => 1, default => 0,
                },
                'due_date' => $task->due_date,
                'project' => $task->project?->name,
                // SUMBER (permintaan Boss): project_id numerik -- DIPAKAI FE bangun
                // route('tasks.show', [project_id, id]), pola SAMA tasks/all.tsx &
                // tasks/index.tsx (tasks.show SELALU butuh project_id di param).
                'project_id' => $task->project_id,
                'assignees' => $task->assignees->pluck('name'),
                'task_type' => $task->task_type,
                'status' => [
                    'name' => $task->taskStatus->name,
                    'color' => $task->taskStatus->color,
                ],
            ])
            ->all();
    }

    /**
     * KONTRAK: A6 — 10 event terbaru, label manusiawi via ActivityLogPresenter
     * (F-106, REUSE SATU sumber label yang sama dengan ActivityLogController) --
     * pola with()/morphWith() disalin literal dari sana supaya N+1 tetap konstan (F-85).
     * GUARD: tie-break `id` DESC WAJIB di samping `created_at` DESC -- banyak baris
     * bisa punya created_at IDENTIK (event beruntun dalam detik yang sama, atau
     * jam dibekukan travelTo() di test), dan `latest('created_at')` sendirian
     * membuat urutan 10 teratas TIDAK DETERMINISTIK antar panggilan (isi top-10
     * bisa ganti-ganti, ikut menggeser jumlah query batch di ActivityLogPresenter).
     *
     * v1.2 DS-4 (F-109): $from/$to MENYEMPIT ke created_at (kapan EVENT terjadi
     * -- beda dari widget task lain yang pakai due_date, activity log memang
     * bicara "kapan", bukan "kapan jatuh tempo"). $userId = PELAKU (`user_id`
     * kolom activity_logs, index komposit `(organization_id, user_id, created_at)`
     * sudah ada -- pola SAMA ActivityLogController::index()).
     *
     * @return array<int, array{id:int, message:string, created_at:mixed}>
     */
    private function recentActivity(?string $from, ?string $to, ?int $userId): array
    {
        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->with(['subject' => function ($morphTo) {
                $morphTo->morphWith([
                    Attachment::class => ['task:id,title'],
                    DeadlineExtension::class => ['task:id,title'],
                ]);
            }])
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $presenter = new ActivityLogPresenter($logs);

        return $logs->map(fn (ActivityLog $log) => [
            'id' => $log->id,
            'message' => $presenter->describe($log),
            'created_at' => $log->created_at,
        ])->all();
    }

    /**
     * KONTRAK: SATU jalur baca dashboard dipakai index() (halaman) DAN summary()
     * (JSON) -- tanggal + filter user_id diparse SEKALI di sini, dilempar apa
     * adanya ke DashboardService::forUsers() (rumus TIDAK disentuh, H2 sudah
     * teruji 162 test, lihat CLAUDE.md §4 "JANGAN ubah DashboardService").
     *
     * Revisi 2026-08-06: $restrictToSelf HANYA dikirim true oleh
     * commandCenterPage() (viewer Command Center tanpa project.viewAll) --
     * index()/summary() (dashboard 3-angka lama) SELALU panggil dengan default
     * false, jadi perilakunya PERSIS SAMA seperti sebelum revisi ini (keputusan
     * Boss: restriksi cuma di Command Center).
     *
     * @return array{0: Carbon, 1: array<int, array<string, mixed>>}
     */
    private function loadRows(Request $request, DashboardService $service, bool $restrictToSelf = false): array
    {
        // GUARD: tanggal dari query string divalidasi lewat Carbon::parse yang
        // ketat terhadap format acak (format tak dikenal -> exception, bukan
        // tanggal ngawur diam-diam). Default SEKARANG (hari ini, WIB — F-69).
        $date = $request->query('date')
            ? Carbon::parse((string) $request->query('date'))
            : Carbon::now();

        // OrganizationScope (F-15) otomatis membatasi ke organisasi admin yang
        // login. is_active=true — user nonaktif (F-16, diblokir tanpa dihapus)
        // tidak relevan ditampilkan di dashboard kerja hari ini. F-52/A6: filter
        // ?user_id= OPSIONAL, mempersempit ke satu user tanpa mengubah rumus.
        // Revisi 2026-08-06: $restrictToSelf FORCE ke user login sendiri,
        // ABAIKAN ?user_id= apa pun yang dikirim (SERVER guard).
        $users = User::where('is_active', true)
            ->when($restrictToSelf, fn ($q) => $q->whereKey($request->user()->id))
            ->when(! $restrictToSelf && $request->filled('user_id'), fn ($q) => $q->whereKey($request->integer('user_id')))
            ->orderBy('name')
            ->get();

        $dashboard = $service->forUsers($users, $date);

        $rows = $users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            ...$dashboard[$user->id],
        ])->values()->all();

        return [$date, $rows];
    }
}
