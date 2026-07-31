// ==========================================================
// MODUL       : command-center
// KLASIFIKASI : UI
// TUJUAN      : Halaman dashboard "Command Center" (BLUEPRINT-UIUX-v1.7 §7.1) —
//               MERENDER props yang SUDAH dihitung backend (DashboardController::
//               commandCenterPage(), F-109). NOL rumus KPI dihitung ulang di sini —
//               satu-satunya aritmetika lokal adalah PRESENTASI murni (format
//               menit->jam via lib/command-center-format, proporsi lingkaran donut
//               dari count yang sudah final, layout grid heatmap) — bukan
//               menurunkan angka beban/idle/skor baru (kalau angka tampak salah,
//               itu bug di DashboardController/Service, BUKAN ditambal di sini).
//               v1.2 DS-4 (F-109/§12.5): filter PER-WIDGET (periode+user) ditambah
//               di sini -- SEMATA menyusun query string, server yang MENYEMPIT
//               query (lihat DashboardController). Preset tanggal (hari ini/minggu
//               ini/bulan ini/rentang custom) dihitung client-side MURNI presentasi
//               (bukan angka KPI), dikirim sebagai from/to ke server.
//               v1.2 DS-4b (§12.5): widget "Status Project" — tabel top-5 proyek
//               dari status_projects (COUNTS, backend sudah urut task_total DESC).
//               SORT KLIK-HEADER murni client-side (re-urut 5 baris yang SAMA,
//               bukan fetch ulang top-5 lain per kolom) — nol angka baru dihitung.
// DIPANGGIL   : DashboardController::commandCenterPage() (route 'dashboard/overview',
//               can:dashboard.view)
// MEMANGGIL   : formatLiveMinutes/classifyWorkload (REUSE F-52, sama persis
//               pages/dashboard.tsx — section "Beban Tim"), formatJamPair/shiftMonth
//               (lib/command-center-format, F-131)
// DATA MASUK  : seluruh field commandCenterPayload() (summary_cards, donut_priority,
//               progress_distribution, task_categories, heatmap, top_tasks,
//               recent_activity, workload_top5, status_projects, filters,
//               filter_users) + team {date,rows} (F-52, loadRows())
// DATA KELUAR : router.get (navigasi bulan heatmap + filter per-widget, SEMUA
//               query tercermin di URL, pola sama activity-logs/index.tsx)
// RISIKO      : SUMBER F-4 — halaman ini CERMIN beban & aktivitas, BUKAN penilaian.
//               JANGAN PERNAH tambah rupiah/skor/reward di sini. F-121 — dashboard
//               3-angka lama TETAP hidup mandiri di route 'dashboard' (link "Detail
//               & filter" di bawah), section "Beban Tim" di sini sengaja READ-ONLY
//               (tanpa filter tanggal/user) supaya tidak menduplikasi kontrol yang
//               sudah ada di halaman lama — F-109: filter Workload Top-5 SENGAJA
//               pakai param `workload_date` TERPISAH dari `?date=` (dipakai section
//               Beban Tim ini) supaya tidak diam-diam ikut menggeser tabelnya.
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatLiveMinutes } from '@/hooks/use-live-counter';
import { classifyWorkload } from '@/lib/dashboard-status';
import { formatJamPair, shiftMonth } from '@/lib/command-center-format';
import { PRIORITY_QUADRANT_COLOR } from '@/lib/priority-quadrant';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ChevronLeft, ChevronRight, Clock, Eye, ListTodo, PlayCircle, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface SummaryCards {
    beban_harian: { used_minutes: number; capacity_minutes: number };
    todo: number;
    in_progress: number;
    review: number;
    overdue: number;
}

interface HeatmapDay {
    date: string;
    beban: number | null;
    level: 'aman' | 'tengah' | 'overload' | null;
}

interface TopTask {
    id: number;
    title: string;
    priority_quadrant: 'p1' | 'p2' | 'p3' | 'p4' | null;
    prio_score: number;
    due_date: string;
    project: string | null;
    assignees: string[];
}

interface WorkloadRow {
    id: number;
    name: string | null;
    beban: number;
}

interface ActivityRow {
    id: number;
    message: string;
    created_at: string;
}

interface AnomalyRow {
    task_id: number;
    title: string;
    estimated_minutes: number;
    actual_minutes: number;
}

interface StatusProjectRow {
    id: number;
    name: string;
    task_total: number;
    todo: number;
    progress: number;
    selesai: number;
    overdue: number;
    due_date: string | null;
}

interface TeamRow {
    id: number;
    name: string;
    kapasitas: number;
    aktif: number;
    beban: number;
    backlog: number;
    idle_plan: number;
    idle_real: number;
    anomalies: AnomalyRow[];
}

// F-109/§12.5: SATU sumber bentuk filter, dikirim balik oleh backend (SELALU 19
// key terisi, null kalau tak difilter) supaya <input>/<select> di bawah selalu
// controlled (nol undefined->controlled warning React).
interface Filters {
    donut_from: string | null;
    donut_to: string | null;
    donut_user_id: number | null;
    progress_from: string | null;
    progress_to: string | null;
    progress_user_id: number | null;
    categories_from: string | null;
    categories_to: string | null;
    categories_user_id: number | null;
    top_tasks_from: string | null;
    top_tasks_to: string | null;
    top_tasks_user_id: number | null;
    activity_from: string | null;
    activity_to: string | null;
    activity_user_id: number | null;
    heatmap_user_id: number | null;
    workload_user_id: number | null;
    workload_date: string | null;
}

interface FilterUser {
    id: number;
    name: string;
}

interface CommandCenterProps {
    date: string;
    summary_cards: SummaryCards;
    donut_priority: Record<'p1' | 'p2' | 'p3' | 'p4' | 'none', number>;
    progress_distribution: { selesai: number; review: number; progress: number; todo: number };
    task_categories: { task_type: string; total: number }[];
    heatmap: { month: string; days: HeatmapDay[]; active_user_count: number };
    top_tasks: TopTask[];
    recent_activity: ActivityRow[];
    workload_top5: WorkloadRow[];
    status_projects: StatusProjectRow[];
    team: { date: string; rows: TeamRow[] };
    filters: Filters;
    filter_users: FilterUser[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard/overview' }];

// SUMBER: F-137 dedup -- warna p1-p4 REUSE lib/priority-quadrant.ts (SATU sumber
// dengan badge task create/edit/show/board, nilai hex IDENTIK ke yang sebelumnya
// didefinisikan sendiri di sini, jadi nol perubahan visual). 'none' (NULL quadrant,
// khusus tampilan dashboard) TETAP lokal -- lib tidak & tidak perlu punya bucket ini.
const PRIORITY_COLOR: Record<'p1' | 'p2' | 'p3' | 'p4' | 'none', string> = {
    ...PRIORITY_QUADRANT_COLOR,
    none: '#cbd5e1',
};
const PRIORITY_LABEL: Record<'p1' | 'p2' | 'p3' | 'p4' | 'none', string> = {
    p1: 'P1 — Penting & Mendesak',
    p2: 'P2 — Penting, Tdk Mendesak',
    p3: 'P3 — Tdk Penting, Mendesak',
    p4: 'P4 — Tdk Penting & Tdk Mendesak',
    none: 'Belum ditandai',
};

const HEATMAP_LEVEL_CLASS: Record<'aman' | 'tengah' | 'overload', string> = {
    aman: 'bg-green-100 text-green-900 dark:bg-green-950 dark:text-green-200',
    tengah: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    overload: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
};

const STATUS_BADGE: Record<string, { label: string; className: string }> = {
    overload: { label: 'Overload', className: 'border-transparent bg-red-600 text-white hover:bg-red-600' },
    'idle-tinggi': { label: 'Idle tinggi', className: 'border-transparent bg-amber-500 text-white hover:bg-amber-500' },
    normal: { label: '', className: '' },
};

// SUMBER: proporsi lingkaran donut MURNI presentasi -- count per quadrant SUDAH
// final dari donut_priority (backend), di sini cuma dibagi 360 derajat (F-109:
// bukan menurunkan angka KPI baru, angkanya sendiri tidak berubah).
function buildDonutGradient(counts: Record<'p1' | 'p2' | 'p3' | 'p4' | 'none', number>): { gradient: string; total: number } {
    const order: ('p1' | 'p2' | 'p3' | 'p4' | 'none')[] = ['p1', 'p2', 'p3', 'p4', 'none'];
    const total = order.reduce((sum, key) => sum + counts[key], 0);

    if (total === 0) {
        return { gradient: 'conic-gradient(#e5e7eb 0deg 360deg)', total: 0 };
    }

    let cursor = 0;
    const stops = order
        .filter((key) => counts[key] > 0)
        .map((key) => {
            const start = cursor;
            cursor += (counts[key] / total) * 360;

            return `${PRIORITY_COLOR[key]} ${start}deg ${cursor}deg`;
        });

    return { gradient: `conic-gradient(${stops.join(', ')})`, total };
}

// F-109/§12.5: preset tanggal MURNI client-side (bukan angka KPI) -- dipakai
// selector per-widget ("Hari ini/Minggu ini/Bulan ini") DAN tombol global
// ("Last Week/Last Month"). Senin = awal minggu (konsisten grid heatmap A6).
function toISODate(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
function startOfWeek(d: Date): Date {
    const copy = new Date(d);
    const day = (copy.getDay() + 6) % 7; // Senin = 0
    copy.setDate(copy.getDate() - day);
    return copy;
}
function presetToday(): { from: string; to: string } {
    const s = toISODate(new Date());
    return { from: s, to: s };
}
function presetThisWeek(): { from: string; to: string } {
    const monday = startOfWeek(new Date());
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    return { from: toISODate(monday), to: toISODate(sunday) };
}
function presetThisMonth(): { from: string; to: string } {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth(), 1);
    const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    return { from: toISODate(first), to: toISODate(last) };
}
function presetLastWeek(): { from: string; to: string } {
    const thisMonday = startOfWeek(new Date());
    const lastMonday = new Date(thisMonday);
    lastMonday.setDate(thisMonday.getDate() - 7);
    const lastSunday = new Date(lastMonday);
    lastSunday.setDate(lastMonday.getDate() + 6);
    return { from: toISODate(lastMonday), to: toISODate(lastSunday) };
}
function presetLastMonth(): { from: string; to: string } {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const last = new Date(now.getFullYear(), now.getMonth(), 0);
    return { from: toISODate(first), to: toISODate(last) };
}

// F-109/§12.5: selector periode+user PER WIDGET (donut/progress/kategori/top-10/
// recent). `prefix` = awalan query param (mis. "donut" -> donut_from/donut_to/
// donut_user_id, cocok dgn DashboardController::commandCenterPayload()).
function RangeUserFilter({
    from,
    to,
    userId,
    users,
    onChange,
}: {
    from: string | null;
    to: string | null;
    userId: number | null;
    users: FilterUser[];
    onChange: (patch: { from?: string | null; to?: string | null; user_id?: number | null }) => void;
}) {
    const active = from !== null || to !== null || userId !== null;

    return (
        <div className="flex flex-wrap items-center gap-1.5 text-xs">
            <Button type="button" variant="outline" size="sm" className="h-6 px-2 text-xs" onClick={() => onChange(presetToday())}>
                Hari ini
            </Button>
            <Button type="button" variant="outline" size="sm" className="h-6 px-2 text-xs" onClick={() => onChange(presetThisWeek())}>
                Minggu ini
            </Button>
            <Button type="button" variant="outline" size="sm" className="h-6 px-2 text-xs" onClick={() => onChange(presetThisMonth())}>
                Bulan ini
            </Button>
            <input
                type="date"
                aria-label="Dari tanggal"
                className="h-6 rounded-md border border-input bg-background px-1.5 text-xs"
                value={from ?? ''}
                onChange={(e) => onChange({ from: e.target.value || null })}
            />
            <span className="text-muted-foreground">–</span>
            <input
                type="date"
                aria-label="Sampai tanggal"
                className="h-6 rounded-md border border-input bg-background px-1.5 text-xs"
                value={to ?? ''}
                onChange={(e) => onChange({ to: e.target.value || null })}
            />
            <select
                aria-label="Filter user"
                className="h-6 rounded-md border border-input bg-background px-1.5 text-xs"
                value={userId ?? ''}
                onChange={(e) => onChange({ user_id: e.target.value ? Number(e.target.value) : null })}
            >
                <option value="">Semua user</option>
                {users.map((u) => (
                    <option key={u.id} value={u.id}>
                        {u.name}
                    </option>
                ))}
            </select>
            {active && (
                <button
                    type="button"
                    aria-label="Reset filter widget ini"
                    className="text-muted-foreground hover:text-foreground"
                    onClick={() => onChange({ from: null, to: null, user_id: null })}
                >
                    <X className="h-3.5 w-3.5" />
                </button>
            )}
        </div>
    );
}

// F-131/§12.5: heatmap HANYA filter user -- SENGAJA tanpa selector periode
// (navigasi bulan prev/next tetap satu-satunya kontrol waktu, lihat KONTRAK
// DashboardController::heatmap()).
function UserOnlyFilter({ userId, users, onChange }: { userId: number | null; users: FilterUser[]; onChange: (userId: number | null) => void }) {
    return (
        <select
            aria-label="Filter user heatmap"
            className="h-6 rounded-md border border-input bg-background px-1.5 text-xs"
            value={userId ?? ''}
            onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)}
        >
            <option value="">Semua user</option>
            {users.map((u) => (
                <option key={u.id} value={u.id}>
                    {u.name}
                </option>
            ))}
        </select>
    );
}

export default function CommandCenter({
    summary_cards: cards,
    donut_priority: donut,
    progress_distribution: progress,
    task_categories: categories,
    heatmap,
    top_tasks: topTasks,
    recent_activity: recentActivity,
    workload_top5: workloadTop5,
    status_projects: statusProjects,
    team,
    filters,
    filter_users: filterUsers,
}: CommandCenterProps) {
    // A10: indikator loading ringan saat navigasi bulan heatmap (Inertia visit
    // penuh me-reload seluruh props) -- MURNI UI, tidak menyentuh data.
    const [navigating, setNavigating] = useState(false);
    useEffect(() => {
        const stop = router.on('start', () => setNavigating(true));
        const stop2 = router.on('finish', () => setNavigating(false));

        return () => {
            stop();
            stop2();
        };
    }, []);

    // F-109: SATU helper query-string dipakai SEMUA kontrol filter (per-widget,
    // global, navigasi bulan) -- selalu spread filters+month SAAT INI dulu supaya
    // ubah 1 kontrol TIDAK mereset kontrol lain (pola sama activity-logs/index.tsx).
    const applyFilters = (overrides: Record<string, string | number | null>) => {
        router.get(
            route('dashboard.overview'),
            { month: heatmap.month, ...filters, ...overrides },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToMonth = (monthKey: string) => {
        applyFilters({ month: monthKey } as unknown as Record<string, string | number | null>);
    };

    // §12.5: tombol global Last Week/Last Month/Pilih Tanggal -- broadcast SATU
    // rentang ke KELIMA widget berbasis periode sekaligus (donut/progress/
    // kategori/top-10/recent). Heatmap & Workload sengaja TIDAK ikut (lihat
    // KONTRAK heatmap()/workload_top5 di DashboardController -- alasan F-131/F-118).
    const RANGE_PREFIXES = ['donut', 'progress', 'categories', 'top_tasks', 'activity'] as const;
    const applyGlobalRange = (range: { from: string; to: string }) => {
        const patch: Record<string, string | number | null> = {};
        RANGE_PREFIXES.forEach((p) => {
            patch[`${p}_from`] = range.from;
            patch[`${p}_to`] = range.to;
        });
        applyFilters(patch);
    };
    const [customOpen, setCustomOpen] = useState(false);
    const [customFrom, setCustomFrom] = useState('');
    const [customTo, setCustomTo] = useState('');

    // §12.5: sort widget Status Project MURNI client-side -- backend sudah
    // kirim top-5 (task_total DESC), klik header cuma re-urut 5 baris yang
    // SAMA (bukan fetch beda top-5 per kolom, nol query tambahan).
    type StatusProjectSortKey = 'name' | 'task_total' | 'todo' | 'progress' | 'selesai' | 'overdue' | 'due_date';
    const [statusProjectSort, setStatusProjectSort] = useState<{ key: StatusProjectSortKey; dir: 'asc' | 'desc' }>({
        key: 'task_total',
        dir: 'desc',
    });
    const sortedStatusProjects = [...statusProjects].sort((a, b) => {
        const { key, dir } = statusProjectSort;
        const av = a[key];
        const bv = b[key];
        const cmp = av === null ? -1 : bv === null ? 1 : av < bv ? -1 : av > bv ? 1 : 0;

        return dir === 'asc' ? cmp : -cmp;
    });
    const toggleStatusProjectSort = (key: StatusProjectSortKey) => {
        setStatusProjectSort((prev) => (prev.key === key ? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'desc' }));
    };

    const donutChart = buildDonutGradient(donut);
    const progressTotal = progress.selesai + progress.review + progress.progress + progress.todo;

    // A6: grid bulan -- padding sel kosong di depan supaya kolom hari (Sen..Min)
    // sejajar (MURNI layout tampilan, bukan hitungan beban/level).
    const firstDate = new Date(`${heatmap.days[0]?.date ?? heatmap.month + '-01'}T00:00:00`);
    const leadingBlank = (firstDate.getDay() + 6) % 7; // Senin = 0

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard — Command Center" />

            <div className={`flex flex-col gap-4 p-4 transition-opacity ${navigating ? 'opacity-60' : ''}`}>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">Command Center</h1>
                    <div className="flex flex-wrap items-center gap-2">
                        {/* §12.5: tombol global periode -- terapkan ke 5 widget berbasis due_date sekaligus */}
                        <Button type="button" variant="outline" size="sm" onClick={() => applyGlobalRange(presetLastWeek())}>
                            Last Week
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={() => applyGlobalRange(presetLastMonth())}>
                            Last Month
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={() => setCustomOpen((v) => !v)}>
                            Pilih Tanggal
                        </Button>
                        {customOpen && (
                            <div className="flex items-center gap-1.5 rounded-md border p-1.5 text-xs">
                                <input
                                    type="date"
                                    aria-label="Rentang global dari"
                                    className="h-6 rounded-md border border-input bg-background px-1.5 text-xs"
                                    value={customFrom}
                                    onChange={(e) => setCustomFrom(e.target.value)}
                                />
                                <span className="text-muted-foreground">–</span>
                                <input
                                    type="date"
                                    aria-label="Rentang global sampai"
                                    className="h-6 rounded-md border border-input bg-background px-1.5 text-xs"
                                    value={customTo}
                                    onChange={(e) => setCustomTo(e.target.value)}
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    className="h-6 px-2 text-xs"
                                    disabled={!customFrom || !customTo}
                                    onClick={() => {
                                        applyGlobalRange({ from: customFrom, to: customTo });
                                        setCustomOpen(false);
                                    }}
                                >
                                    Terapkan ke semua
                                </Button>
                            </div>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('dashboard')}>Dashboard lama (detail & filter)</Link>
                        </Button>
                    </div>
                </div>

                {/* A2: 5 kartu ringkas -- statis (nol klik-filter, keputusan Boss
                    2026-07-29: halaman "Semua Tugas" lintas-project belum ada, DAN
                    §12.5 tak menyebut kartu ini di daftar 7 widget berfilter). */}
                <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Beban Harian</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">
                            {formatJamPair(cards.beban_harian.used_minutes, cards.beban_harian.capacity_minutes)}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">To Do</CardTitle>
                            <ListTodo className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">{cards.todo}</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">In Progress</CardTitle>
                            <PlayCircle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">{cards.in_progress}</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Review</CardTitle>
                            <Eye className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">{cards.review}</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Overdue</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">{cards.overdue}</CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {/* A3: Donut prioritas */}
                    <Card>
                        <CardHeader className="flex flex-col gap-2">
                            <CardTitle className="text-base">Prioritas Eisenhower</CardTitle>
                            <RangeUserFilter
                                from={filters.donut_from}
                                to={filters.donut_to}
                                userId={filters.donut_user_id}
                                users={filterUsers}
                                onChange={(patch) =>
                                    applyFilters({
                                        ...(patch.from !== undefined && { donut_from: patch.from }),
                                        ...(patch.to !== undefined && { donut_to: patch.to }),
                                        ...(patch.user_id !== undefined && { donut_user_id: patch.user_id }),
                                    })
                                }
                            />
                        </CardHeader>
                        <CardContent>
                            {donutChart.total === 0 ? (
                                <p className="text-sm text-muted-foreground">Belum ada task untuk ditandai prioritas.</p>
                            ) : (
                                <div className="flex items-center gap-4">
                                    <div className="relative h-28 w-28 shrink-0 rounded-full" style={{ background: donutChart.gradient }}>
                                        <div className="absolute inset-3 flex items-center justify-center rounded-full bg-card text-sm font-semibold">
                                            {donutChart.total}
                                        </div>
                                    </div>
                                    <ul className="flex flex-col gap-1 text-xs">
                                        {(['p1', 'p2', 'p3', 'p4', 'none'] as const).map((key) => (
                                            <li key={key} className="flex items-center gap-2">
                                                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: PRIORITY_COLOR[key] }} />
                                                <span className="text-muted-foreground">{PRIORITY_LABEL[key]}</span>
                                                <span className="font-medium">{donut[key]}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* A4: distribusi progress */}
                    <Card>
                        <CardHeader className="flex flex-col gap-2">
                            <CardTitle className="text-base">Distribusi Progress</CardTitle>
                            <RangeUserFilter
                                from={filters.progress_from}
                                to={filters.progress_to}
                                userId={filters.progress_user_id}
                                users={filterUsers}
                                onChange={(patch) =>
                                    applyFilters({
                                        ...(patch.from !== undefined && { progress_from: patch.from }),
                                        ...(patch.to !== undefined && { progress_to: patch.to }),
                                        ...(patch.user_id !== undefined && { progress_user_id: patch.user_id }),
                                    })
                                }
                            />
                        </CardHeader>
                        <CardContent>
                            {progressTotal === 0 ? (
                                <p className="text-sm text-muted-foreground">Belum ada task.</p>
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {[
                                        { label: 'To Do', value: progress.todo, className: 'bg-slate-400' },
                                        { label: 'In Progress', value: progress.progress, className: 'bg-blue-500' },
                                        { label: 'Review', value: progress.review, className: 'bg-amber-500' },
                                        { label: 'Selesai', value: progress.selesai, className: 'bg-green-500' },
                                    ].map((row) => (
                                        <div key={row.label} className="flex flex-col gap-1">
                                            <div className="flex justify-between text-xs">
                                                <span>{row.label}</span>
                                                <span className="text-muted-foreground">{row.value}</span>
                                            </div>
                                            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className={`h-full rounded-full ${row.className}`}
                                                    style={{ width: `${(row.value / progressTotal) * 100}%` }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* A5: kategori tugas */}
                    <Card>
                        <CardHeader className="flex flex-col gap-2">
                            <CardTitle className="text-base">Kategori Tugas</CardTitle>
                            <RangeUserFilter
                                from={filters.categories_from}
                                to={filters.categories_to}
                                userId={filters.categories_user_id}
                                users={filterUsers}
                                onChange={(patch) =>
                                    applyFilters({
                                        ...(patch.from !== undefined && { categories_from: patch.from }),
                                        ...(patch.to !== undefined && { categories_to: patch.to }),
                                        ...(patch.user_id !== undefined && { categories_user_id: patch.user_id }),
                                    })
                                }
                            />
                        </CardHeader>
                        <CardContent>
                            {categories.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Belum ada task.</p>
                            ) : (
                                <ul className="flex flex-col gap-2 text-sm">
                                    {categories.map((c) => (
                                        <li key={c.task_type} className="flex items-center justify-between">
                                            <span className="capitalize">{c.task_type}</span>
                                            <Badge variant="secondary">{c.total}</Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* A6: master calendar heatmap */}
                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2 space-y-0">
                        <CardTitle className="text-base">Kalender Beban Tim — {heatmap.month}</CardTitle>
                        <div className="flex flex-wrap items-center gap-2">
                            <UserOnlyFilter userId={filters.heatmap_user_id} users={filterUsers} onChange={(userId) => applyFilters({ heatmap_user_id: userId })} />
                            <Button variant="outline" size="sm" onClick={() => goToMonth(shiftMonth(heatmap.month, -1))} disabled={navigating}>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => goToMonth(shiftMonth(heatmap.month, 1))} disabled={navigating}>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-2 grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
                            {['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map((d) => (
                                <div key={d}>{d}</div>
                            ))}
                        </div>
                        <div className="grid grid-cols-7 gap-1">
                            {Array.from({ length: leadingBlank }).map((_, i) => (
                                <div key={`blank-${i}`} />
                            ))}
                            {heatmap.days.map((day) => {
                                // F-131: hari LEWAT (level null) -- NETRAL, abu-abu, bukan warna realisasi.
                                const cellClass = day.level ? HEATMAP_LEVEL_CLASS[day.level] : 'bg-muted text-muted-foreground';

                                return (
                                    <div
                                        key={day.date}
                                        className={`flex aspect-square flex-col items-center justify-center rounded-md text-xs ${cellClass}`}
                                        title={day.beban === null ? 'Hari lewat (netral)' : `Beban tim: ${formatLiveMinutes(day.beban)}`}
                                    >
                                        <span className="font-medium">{new Date(`${day.date}T00:00:00`).getDate()}</span>
                                    </div>
                                );
                            })}
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Ambang agregat {heatmap.active_user_count} user aktif — hari lewat selalu netral (F-131).
                        </p>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {/* A7: top-10 task */}
                    <Card>
                        <CardHeader className="flex flex-col gap-2">
                            <CardTitle className="text-base">Top-10 Task Prioritas</CardTitle>
                            <RangeUserFilter
                                from={filters.top_tasks_from}
                                to={filters.top_tasks_to}
                                userId={filters.top_tasks_user_id}
                                users={filterUsers}
                                onChange={(patch) =>
                                    applyFilters({
                                        ...(patch.from !== undefined && { top_tasks_from: patch.from }),
                                        ...(patch.to !== undefined && { top_tasks_to: patch.to }),
                                        ...(patch.user_id !== undefined && { top_tasks_user_id: patch.user_id }),
                                    })
                                }
                            />
                        </CardHeader>
                        <CardContent>
                            {topTasks.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Tidak ada task aktif.</p>
                            ) : (
                                <ul className="flex flex-col gap-2 text-sm">
                                    {topTasks.map((task) => (
                                        <li key={task.id} className="flex items-center justify-between gap-2 border-b pb-2 last:border-0">
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">{task.title}</p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {task.project ?? '-'} · {task.assignees.join(', ') || '-'} ·{' '}
                                                    {new Date(task.due_date).toLocaleDateString('id-ID')}
                                                </p>
                                            </div>
                                            {task.priority_quadrant && (
                                                <Badge
                                                    style={{ backgroundColor: PRIORITY_COLOR[task.priority_quadrant], color: '#fff', borderColor: 'transparent' }}
                                                >
                                                    {task.priority_quadrant.toUpperCase()}
                                                </Badge>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    {/* A8: workload top-5 */}
                    <Card>
                        <CardHeader className="flex flex-col gap-2">
                            <CardTitle className="text-base">Workload Top-5</CardTitle>
                            <div className="flex flex-wrap items-center gap-1.5 text-xs">
                                <span className="text-muted-foreground">Anchor:</span>
                                <input
                                    type="date"
                                    aria-label="Tanggal anchor workload"
                                    className="h-6 rounded-md border border-input bg-background px-1.5 text-xs"
                                    value={filters.workload_date ?? ''}
                                    onChange={(e) => applyFilters({ workload_date: e.target.value || null })}
                                />
                                <UserOnlyFilter
                                    userId={filters.workload_user_id}
                                    users={filterUsers}
                                    onChange={(userId) => applyFilters({ workload_user_id: userId })}
                                />
                            </div>
                        </CardHeader>
                        <CardContent>
                            {workloadTop5.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Belum ada beban tercatat.</p>
                            ) : (
                                <ul className="flex flex-col gap-2 text-sm">
                                    {workloadTop5.map((row) => (
                                        <li key={row.id} className="flex items-center justify-between">
                                            <span>{row.name ?? '-'}</span>
                                            <span className="font-medium">{formatLiveMinutes(row.beban)}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* A9: recent activity -- label APA ADANYA dari ActivityLogPresenter (F-106) */}
                <Card>
                    <CardHeader className="flex flex-col gap-2">
                        <CardTitle className="text-base">Aktivitas Terbaru</CardTitle>
                        <RangeUserFilter
                            from={filters.activity_from}
                            to={filters.activity_to}
                            userId={filters.activity_user_id}
                            users={filterUsers}
                            onChange={(patch) =>
                                applyFilters({
                                    ...(patch.from !== undefined && { activity_from: patch.from }),
                                    ...(patch.to !== undefined && { activity_to: patch.to }),
                                    ...(patch.user_id !== undefined && { activity_user_id: patch.user_id }),
                                })
                            }
                        />
                    </CardHeader>
                    <CardContent>
                        {recentActivity.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Belum ada aktivitas.</p>
                        ) : (
                            <ul className="flex flex-col gap-2 text-sm">
                                {recentActivity.map((log) => (
                                    <li key={log.id} className="flex items-center justify-between gap-2 border-b pb-2 last:border-0">
                                        <span>{log.message}</span>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {new Date(log.created_at).toLocaleString('id-ID')}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {/* §12.5: widget "Status Project" -- COUNTS top-5 proyek (BUKAN
                    derivasi status-label F-125, itu tugas halaman Proyek nanti). */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                        <CardTitle className="text-base">Status Project</CardTitle>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('projects.index')}>Show More →</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-muted-foreground">
                                    {(
                                        [
                                            ['name', 'Proyek'],
                                            ['task_total', 'Task'],
                                            ['todo', 'Todo'],
                                            ['progress', 'Progress'],
                                            ['selesai', 'Selesai'],
                                            ['overdue', 'Overdue'],
                                            ['due_date', 'Deadline'],
                                        ] as [StatusProjectSortKey, string][]
                                    ).map(([key, label]) => (
                                        <th key={key} className="p-3">
                                            <button
                                                type="button"
                                                className="flex items-center gap-1 font-medium hover:text-foreground"
                                                onClick={() => toggleStatusProjectSort(key)}
                                            >
                                                {label}
                                                {statusProjectSort.key === key && <span>{statusProjectSort.dir === 'asc' ? '↑' : '↓'}</span>}
                                            </button>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {sortedStatusProjects.map((row) => (
                                    <tr key={row.id} className="border-b last:border-0">
                                        <td className="p-3 font-medium">{row.name}</td>
                                        <td className="p-3">{row.task_total}</td>
                                        <td className="p-3">{row.todo}</td>
                                        <td className="p-3">{row.progress}</td>
                                        <td className="p-3">{row.selesai}</td>
                                        <td className="p-3">
                                            {row.overdue > 0 ? (
                                                <Badge className="border-transparent bg-red-600 text-white hover:bg-red-600">{row.overdue}</Badge>
                                            ) : (
                                                <span className="text-muted-foreground">0</span>
                                            )}
                                        </td>
                                        <td className="p-3">{row.due_date ? new Date(row.due_date).toLocaleDateString('id-ID') : '-'}</td>
                                    </tr>
                                ))}

                                {sortedStatusProjects.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="p-6 text-center text-muted-foreground">
                                            Belum ada proyek aktif.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {/* F-52/F-121: dashboard 3-angka lama DIPERTAHANKAN sebagai section "Beban
                    Tim" -- read-only (tanpa filter tanggal/user, itu tetap di halaman lama). */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                        <CardTitle className="text-base">Beban Tim — {team.date}</CardTitle>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('dashboard')}>Detail & filter →</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-muted-foreground">
                                    <th className="p-3">Nama</th>
                                    <th className="p-3">Aktif</th>
                                    <th className="p-3">Beban hari ini (idle plan)</th>
                                    <th className="p-3">Backlog</th>
                                    <th className="p-3">Anomali</th>
                                </tr>
                            </thead>
                            <tbody>
                                {team.rows.map((row) => {
                                    const status = classifyWorkload(row.beban, row.kapasitas);
                                    const badge = STATUS_BADGE[status];

                                    return (
                                        <tr key={row.id} className="border-b last:border-0 align-top">
                                            <td className="p-3 font-medium">{row.name}</td>
                                            <td className="p-3">{formatLiveMinutes(row.aktif)}</td>
                                            <td className="p-3">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span>
                                                        {formatLiveMinutes(row.beban)} (idle {formatLiveMinutes(row.idle_plan)})
                                                    </span>
                                                    {badge.label && <Badge className={badge.className}>{badge.label}</Badge>}
                                                </div>
                                            </td>
                                            <td className="p-3">{formatLiveMinutes(row.backlog)}</td>
                                            <td className="p-3">
                                                {row.anomalies.length === 0 ? (
                                                    <span className="text-muted-foreground">0</span>
                                                ) : (
                                                    <Badge className="border-transparent bg-slate-500 text-white hover:bg-slate-500">
                                                        {row.anomalies.length} perlu ditinjau
                                                    </Badge>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}

                                {team.rows.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="p-6 text-center text-muted-foreground">
                                            Tidak ada user aktif untuk ditampilkan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
