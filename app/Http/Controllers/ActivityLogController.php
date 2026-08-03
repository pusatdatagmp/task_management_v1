<?php

/**
 * ==========================================================
 * MODUL       : ActivityLogController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Halaman log aktivitas GLOBAL, lintas project (v1.0 H4, F-116) —
 *               READ-ONLY MUTLAK, tidak ada method lain selain index(). Permission
 *               `activity.view` (bukan F-95 membership — ini data pengawasan
 *               lintas-proyek, beda dari timeline per-task di TaskController::show()).
 * DIPANGGIL   : routes/admin.php (gated can:activity.view)
 * MEMANGGIL   : ActivityLog, ActivityLogPresenter (label manusiawi, F-106)
 * DATA MASUK  : query string filter (user_id/event/from/to)
 * DATA KELUAR : Inertia page 'activity-logs/index' (paginated) + `summary`
 *               (4 kartu ringkas, permintaan Boss — lihat buildSummary())
 * RISIKO      : SUMBER F-85 — with(['user','subject'=>morphWith(...)]) WAJIB
 *               dipasang SEBELUM paginate(), dan ActivityLogPresenter dibuat SEKALI
 *               dari collection yang SUDAH di-load (bukan per baris) — kalau salah
 *               satu bagian ini dilepas, N+1 balik muncul diam-diam saat data
 *               membesar (ribuan baris, sesuai peringatan prompt). buildSummary()
 *               menambah 4 query TETAP (nol N+1 baru, F-85 tetap aman — konstan
 *               terlepas jumlah baris, cuma agregat COUNT/GROUP BY).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\DeadlineExtension;
use App\Models\User;
use App\Support\ActivityLogPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'event' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        // SUMBER: closure filter TUNGGAL dipakai listing DAN buildSummary() --
        // 4 kartu ringkas WAJIB cerminan angka yang SAMA dengan hasil filter
        // aktif (bukan agregat organisasi penuh yang menyesatkan saat admin
        // sedang menyaring per user/event/tanggal).
        $scopeFilters = function ($q) use ($filters) {
            if (! empty($filters['user_id'])) {
                $q->where('user_id', $filters['user_id']);
            }

            if (! empty($filters['event'])) {
                $q->where('event', $filters['event']);
            }

            if (! empty($filters['from'])) {
                $q->whereDate('created_at', '>=', $filters['from']);
            }

            if (! empty($filters['to'])) {
                $q->whereDate('created_at', '<=', $filters['to']);
            }
        };

        $query = ActivityLog::query()
            ->with('user:id,name')
            // F-85: morphWith -> Attachment/DeadlineExtension ikut membawa relasi
            // 'task' (untuk judul task di kalimat) dalam SATU query tambahan per
            // tipe subject yang benar-benar muncul di halaman ini, bukan per baris.
            ->with(['subject' => function ($morphTo) {
                $morphTo->morphWith([
                    Attachment::class => ['task:id,title'],
                    DeadlineExtension::class => ['task:id,title'],
                ]);
            }])
            ->tap($scopeFilters)
            ->latest('created_at');

        $logs = $query->paginate(10)->withQueryString();

        // F-106: SATU presenter untuk seluruh halaman (batch lookup status/user
        // di properties, lihat ActivityLogPresenter RISIKO) -> label manusiawi.
        $presenter = new ActivityLogPresenter(collect($logs->items()));

        $logs->getCollection()->transform(fn (ActivityLog $log) => [
            'id' => $log->id,
            'actor' => $log->user?->name ?? 'Sistem',
            'event' => $log->event,
            'event_label' => ActivityLogPresenter::eventLabel($log->event),
            'message' => $presenter->describe($log),
            'created_at' => $log->created_at,
        ]);

        return Inertia::render('activity-logs/index', [
            'logs' => $logs,
            'summary' => $this->buildSummary($scopeFilters),
            'filters' => [
                'user_id' => $filters['user_id'] ?? null,
                'event' => $filters['event'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // SUMBER: daftar event yang PERNAH tercatat di organisasi ini (bukan
            // katalog statis) -> dropdown filter selalu cocok data nyata. Query
            // TERPISAH dari daftar utama (bukan N+1 -- satu query tetap, tidak
            // tumbuh dengan jumlah baris log).
            'eventTypes' => ActivityLog::query()
                ->select('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event')
                ->map(fn (string $event) => ['value' => $event, 'label' => ActivityLogPresenter::eventLabel($event)])
                ->values(),
        ]);
    }

    /**
     * KONTRAK: 4 kartu ringkas di atas tabel (permintaan Boss) — SEMUA angka
     * REUSE closure filter $scopeFilters yang SAMA dipakai listing di index()
     * (nol filter duplikat), supaya kartu selalu cerminan hasil yang SEDANG
     * ditampilkan, bukan agregat organisasi penuh. 4 query TETAP (COUNT/GROUP
     * BY, bukan agregat per baris) — F-85 tetap konstan.
     *
     * @return array{total:int, today:int, top_user:?string, top_user_count:int, top_event:?string, top_event_count:int}
     */
    private function buildSummary(\Closure $scopeFilters): array
    {
        $total = ActivityLog::query()->tap($scopeFilters)->count();

        // SUMBER: "hari ini" WIB (F-69) -- created_at sudah disimpan WIB di DB,
        // now()->toDateString() cukup, nol konversi timezone tambahan.
        $today = ActivityLog::query()->tap($scopeFilters)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $topUserRow = ActivityLog::query()->tap($scopeFilters)
            ->whereNotNull('user_id')
            ->select('user_id')
            ->selectRaw('count(*) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->with('user:id,name')
            ->first();

        $topEventRow = ActivityLog::query()->tap($scopeFilters)
            ->select('event')
            ->selectRaw('count(*) as total')
            ->groupBy('event')
            ->orderByDesc('total')
            ->first();

        return [
            'total' => $total,
            'today' => $today,
            'top_user' => $topUserRow?->user?->name,
            'top_user_count' => $topUserRow?->total ?? 0,
            'top_event' => $topEventRow ? ActivityLogPresenter::eventLabel($topEventRow->event) : null,
            'top_event_count' => $topEventRow?->total ?? 0,
        ];
    }
}
