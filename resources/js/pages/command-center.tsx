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
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatLiveMinutes } from '@/hooks/use-live-counter';
import { classifyWorkload } from '@/lib/dashboard-status';
import { formatJamPair, shiftMonth } from '@/lib/command-center-format';
import { PRIORITY_QUADRANT_COLOR } from '@/lib/priority-quadrant';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Briefcase, ChevronLeft, ChevronRight, Clock, Eye, ListTodo, PlayCircle, Star, X } from 'lucide-react';
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
    project_id: number;
    assignees: string[];
    task_type: string;
    status: { name: string; color: string };
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
    team: { date: string; selected_user_id: number | null; rows: TeamRow[] };
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

// SUMBER: label kategori (task_type, DM §3.9) untuk kolom "Kategori" tabel
// Top-10 (permintaan Boss) -- pola SAMA TASK_TYPES di tasks/all.tsx, didefinisikan
// lokal (bukan lib bersama) karena cuma dipakai di satu tabel di halaman ini.
const TASK_TYPE_LABEL: Record<string, string> = {
    daily: 'Harian',
    weekly: 'Mingguan',
    monthly: 'Bulanan',
    tentative: 'Tentatif',
    project: 'Project',
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

// Permintaan Boss: card "Team Work Load" & modal "Detail & filter"-nya BUTUH
// sort per kolom -- MURNI re-urut baris yang SUDAH dikirim backend (team.rows,
// F-52), nol angka beban/idle baru dihitung di sini. `status` diturunkan dari
// classifyWorkload() yang SAMA dipakai badge (bukan derivasi baru).
type TeamSortKey = 'name' | 'aktif' | 'beban' | 'idle_plan' | 'status';
function sortTeamRows(rows: TeamRow[], sort: { key: TeamSortKey; dir: 'asc' | 'desc' }): TeamRow[] {
    return [...rows].sort((a, b) => {
        const av = sort.key === 'status' ? classifyWorkload(a.beban, a.kapasitas) : a[sort.key];
        const bv = sort.key === 'status' ? classifyWorkload(b.beban, b.kapasitas) : b[sort.key];
        const cmp = av < bv ? -1 : av > bv ? 1 : 0;

        return sort.dir === 'asc' ? cmp : -cmp;
    });
}

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

    // Permintaan Boss: tabel Top-10 Task -- sort MURNI client-side (backend
    // sudah kirim 10 baris final, klik header cuma re-urut 10 baris yang SAMA).
    // Default prio_score DESC -- cerminan urutan default backend (topTasks()).
    type TopTaskSortKey = 'title' | 'prio_score' | 'task_type' | 'status' | 'assignees' | 'due_date';
    const [topTaskSort, setTopTaskSort] = useState<{ key: TopTaskSortKey; dir: 'asc' | 'desc' }>({ key: 'prio_score', dir: 'desc' });
    const sortedTopTasks = [...topTasks].sort((a, b) => {
        const { key, dir } = topTaskSort;
        const av = key === 'assignees' ? a.assignees.join(', ') : key === 'status' ? a.status.name : a[key];
        const bv = key === 'assignees' ? b.assignees.join(', ') : key === 'status' ? b.status.name : b[key];
        const cmp = av < bv ? -1 : av > bv ? 1 : 0;

        return dir === 'asc' ? cmp : -cmp;
    });
    const toggleTopTaskSort = (key: TopTaskSortKey) => {
        setTopTaskSort((prev) => (prev.key === key ? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'desc' }));
    };

    // Permintaan Boss: card "Team Work Load" tampil TOP-5 berdasarkan kapasitas
    // idle TERBANYAK (seleksi TETAP, dihitung SEKALI dari team.rows) -- sort per
    // kolom cuma re-urut 5 baris hasil seleksi ini (pola SAMA Status Project),
    // BUKAN memilih ulang top-5 lain per kolom.
    const teamTop5 = [...team.rows].sort((a, b) => b.idle_plan - a.idle_plan).slice(0, 5);
    const [teamSort, setTeamSort] = useState<{ key: TeamSortKey; dir: 'asc' | 'desc' }>({ key: 'idle_plan', dir: 'desc' });
    const sortedTeamTop5 = sortTeamRows(teamTop5, teamSort);
    const toggleTeamSort = (key: TeamSortKey) => {
        setTeamSort((prev) => (prev.key === key ? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'desc' }));
    };

    // Permintaan Boss: modal "Detail & filter" -- tabel PENUH (team.rows, bukan
    // top-5), sort state TERPISAH dari card utama supaya tidak saling timpa.
    const [workloadModalOpen, setWorkloadModalOpen] = useState(false);
    const [teamModalSort, setTeamModalSort] = useState<{ key: TeamSortKey; dir: 'asc' | 'desc' }>({ key: 'name', dir: 'asc' });
    const sortedTeamAll = sortTeamRows(team.rows, teamModalSort);
    const toggleTeamModalSort = (key: TeamSortKey) => {
        setTeamModalSort((prev) => (prev.key === key ? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'desc' }));
    };
    // SUMBER: filter tanggal/user modal REUSE ?date=/?user_id= yang SUDAH dibaca
    // loadRows() (SATU sumber sama dengan dashboard lama, F-52) -- nol param
    // baru di backend, cuma ditambahkan lewat helper applyFilters() yang sudah ada.
    const applyTeamFilter = (overrides: { date?: string; user_id?: number | null }) => {
        applyFilters({
            date: overrides.date ?? team.date,
            user_id: overrides.user_id !== undefined ? overrides.user_id : team.selected_user_id,
        });
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
                        {/* <Button variant="outline" size="sm" asChild>
                            <Link href={route('dashboard')}>Dashboard lama (detail & filter)</Link>
                        </Button> */}
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
                            <CardTitle className="text-base">Prioritas Tugas</CardTitle>
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

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {/* F-52/F-121: dashboard 3-angka lama DIPERTAHANKAN sebagai section "Beban
        Tim" -- Permintaan Boss: top-5 idle terbanyak + sort per kolom + modal
        "Detail & filter" (menggantikan Link ke halaman dashboard lama). */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-base">Team Work Load — {team.date}</CardTitle>
                            <Button type="button" variant="outline" size="sm" onClick={() => setWorkloadModalOpen(true)}>
                                Detail & filter →
                            </Button>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50 text-muted-foreground">
                                        {(
                                            [
                                                ['name', 'Tim'],
                                                ['aktif', 'Waktu Terpakai'],
                                                ['idle_plan', 'Kapasitas Sisa (idle plan)'],
                                                ['status', 'Status'],
                                            ] as [TeamSortKey, string][]
                                        ).map(([key, label]) => (
                                            <th key={key} className="p-3">
                                                <button
                                                    type="button"
                                                    className="flex items-center gap-1 font-medium hover:text-foreground"
                                                    onClick={() => toggleTeamSort(key)}
                                                >
                                                    {label}
                                                    {teamSort.key === key && <span>{teamSort.dir === 'asc' ? '↑' : '↓'}</span>}
                                                </button>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {sortedTeamTop5.map((row) => {
                                        const status = classifyWorkload(row.beban, row.kapasitas);
                                        const badge = STATUS_BADGE[status];

                                        return (
                                            <tr key={row.id} className="border-b last:border-0 align-top">
                                                <td className="p-3 font-medium">{row.name}</td>
                                                <td className="p-3">{formatLiveMinutes(row.aktif)}</td>
                                                <td className="p-3">
                                                    {formatLiveMinutes(row.beban)} (idle {formatLiveMinutes(row.idle_plan)})
                                                </td>
                                                <td className="p-3">{badge.label && <Badge className={badge.className}>{badge.label}</Badge>}</td>
                                            </tr>
                                        );
                                    })}

                                    {sortedTeamTop5.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="p-6 text-center text-muted-foreground">
                                                Tidak ada user aktif untuk ditampilkan.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    {/* Permintaan Boss: modal "Detail & filter" Team Work Load -- tabel PENUH
        (bukan cuma top-5) + filter Tanggal & User yang REUSE ?date=/?user_id=
        yang SUDAH dibaca loadRows() (F-52, nol param baru di backend). */}
                    <Dialog open={workloadModalOpen} onOpenChange={setWorkloadModalOpen}>
                        <DialogContent className="max-h-[85vh] max-w-3xl overflow-y-auto">
                            <DialogHeader>
                                <DialogTitle>Team Work Load — Detail &amp; Filter</DialogTitle>
                            </DialogHeader>

                            <div className="flex flex-wrap items-end gap-3 text-sm">
                                <label className="flex flex-col gap-1">
                                    <span className="font-medium">Tanggal</span>
                                    <input
                                        type="date"
                                        value={team.date}
                                        onChange={(e) => applyTeamFilter({ date: e.target.value })}
                                        className="h-8 rounded-md border border-input bg-background px-2"
                                    />
                                </label>
                                <label className="flex flex-col gap-1">
                                    <span className="font-medium">User</span>
                                    <select
                                        value={team.selected_user_id ?? ''}
                                        onChange={(e) => applyTeamFilter({ user_id: e.target.value ? Number(e.target.value) : null })}
                                        className="h-8 rounded-md border border-input bg-background px-2"
                                    >
                                        <option value="">Semua user</option>
                                        {filterUsers.map((u) => (
                                            <option key={u.id} value={u.id}>
                                                {u.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>

                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/50 text-muted-foreground">
                                            {(
                                                [
                                                    ['name', 'Tim'],
                                                    ['aktif', 'Waktu Terpakai'],
                                                    ['idle_plan', 'Kapasitas Sisa (idle plan)'],
                                                    ['status', 'Status'],
                                                ] as [TeamSortKey, string][]
                                            ).map(([key, label]) => (
                                                <th key={key} className="p-3">
                                                    <button
                                                        type="button"
                                                        className="flex items-center gap-1 font-medium hover:text-foreground"
                                                        onClick={() => toggleTeamModalSort(key)}
                                                    >
                                                        {label}
                                                        {teamModalSort.key === key && <span>{teamModalSort.dir === 'asc' ? '↑' : '↓'}</span>}
                                                    </button>
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {sortedTeamAll.map((row) => {
                                            const status = classifyWorkload(row.beban, row.kapasitas);
                                            const badge = STATUS_BADGE[status];

                                            return (
                                                <tr key={row.id} className="border-b last:border-0 align-top">
                                                    <td className="p-3 font-medium">{row.name}</td>
                                                    <td className="p-3">{formatLiveMinutes(row.aktif)}</td>
                                                    <td className="p-3">
                                                        {formatLiveMinutes(row.beban)} (idle {formatLiveMinutes(row.idle_plan)})
                                                    </td>
                                                    <td className="p-3">{badge.label && <Badge className={badge.className}>{badge.label}</Badge>}</td>
                                                </tr>
                                            );
                                        })}

                                        {sortedTeamAll.length === 0 && (
                                            <tr>
                                                <td colSpan={4} className="p-6 text-center text-muted-foreground">
                                                    Tidak ada user aktif untuk ditampilkan.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </DialogContent>
                    </Dialog>

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


                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
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
                            {/* Header Hari */}
                            <div className="mb-2 grid grid-cols-7 gap-1 text-center text-xs font-semibold text-muted-foreground">
                                {['SEN', 'SEL', 'RAB', 'KAM', 'JUM', 'SAB', 'MIN'].map((d) => (
                                    <div key={d}>{d}</div>
                                ))}
                            </div>

                            {/* Grid Kalender */}
                            <div className="grid grid-cols-7 gap-1">
                                {Array.from({ length: leadingBlank }).map((_, i) => (
                                    <div key={`blank-${i}`} />
                                ))}
                                {heatmap.days.map((day) => {
                                    // F-131: hari LEWAT (level null) -- NETRAL, abu-abu
                                    const cellClass = day.level ? HEATMAP_LEVEL_CLASS[day.level] : 'bg-muted text-muted-foreground';

                                    return (
                                        <div
                                            key={day.date}
                                            /* flex-col & p-1 memastikan posisi angka dan icon muat di dalam kotak secara vertikal */
                                            className={`flex aspect-square flex-col items-center justify-between p-1.5 rounded-lg text-xs font-semibold ${cellClass}`}
                                            title={day.beban === null ? 'Hari lewat (netral)' : `Beban tim: ${formatLiveMinutes(day.beban)}`}
                                        >
                                            {/* Angka Tanggal di Bagian Atas/Tengah Kotak */}
                                            <span>{new Date(`${day.date}T00:00:00`).getDate()}</span>

                                            {/* Icon di Dalam Kotak Tanggal (Bagian Bawah) */}
                                            <div className="h-4 flex items-center justify-center">
                                                {day.type === 'meeting' && (
                                                    <Briefcase className="h-3.5 w-3.5 text-blue-600" />
                                                )}
                                                {day.type === 'libur' && (
                                                    <Star className="h-3.5 w-3.5" />
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Section Legend di Bawah Kalender */}
                            <div className="mt-4 pt-3 border-t border-border space-y-2">
                                <div className="flex flex-wrap items-center justify-start gap-4 text-xs text-muted-foreground font-medium">
                                    {/* Status Beban Warna */}
                                    {Object.keys(HEATMAP_LEVEL_CLASS).map((level) => (
                                        <div key={level} className="flex items-center gap-1.5">
                                            <span className={`h-3 w-3 rounded-sm ${HEATMAP_LEVEL_CLASS[level]}`} />
                                            <span className="capitalize">{level}</span>
                                        </div>
                                    ))}

                                    {/* Icon Legend */}
                                    <div className="flex items-center gap-1.5">
                                        <Briefcase className="h-3.5 w-3.5 text-blue-600" />
                                        <span>Meeting</span>
                                    </div>

                                    <div className="flex items-center gap-1.5">
                                        <Star className="h-3.5 w-3.5" />
                                        <span>Libur</span>
                                    </div>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Ambang agregat {heatmap.active_user_count} user aktif — hari lewat selalu netral (F-131).
                                </p>
                            </div>
                        </CardContent>
                    </Card>

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
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-1">
                    {/* A7: top-10 task -- Permintaan Boss: tabel (bukan list) dengan kolom
        Tugas/Prioritas/Kategori/Status/Tim-Assign/Tgl Deadline, sort per kolom,
        + tombol Show more ke halaman "Semua Tugas" (tasks.all). */}
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
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50 text-muted-foreground">
                                                {(
                                                    [
                                                        ['title', 'Tugas'],
                                                        ['prio_score', 'Prioritas'],
                                                        ['task_type', 'Kategori'],
                                                        ['status', 'Status'],
                                                        ['assignees', 'Tim/Assign'],
                                                        ['due_date', 'Tanggal Deadline'],
                                                    ] as [TopTaskSortKey, string][]
                                                ).map(([key, label]) => (
                                                    <th key={key} className="p-3">
                                                        <button
                                                            type="button"
                                                            className="flex items-center gap-1 font-medium hover:text-foreground"
                                                            onClick={() => toggleTopTaskSort(key)}
                                                        >
                                                            {label}
                                                            {topTaskSort.key === key && <span>{topTaskSort.dir === 'asc' ? '↑' : '↓'}</span>}
                                                        </button>
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {sortedTopTasks.map((task) => (
                                                <tr key={task.id} className="border-b last:border-0">
                                                    <td className="p-3">
                                                        {/* Permintaan Boss: judul task DIKLIK -> langsung ke halaman detail
                                                            (route tasks.show, pola SAMA tasks/all.tsx & tasks/index.tsx). */}
                                                        <Link href={route('tasks.show', [task.project_id, task.id])} className="font-medium hover:underline">
                                                            {task.title}
                                                        </Link>
                                                        <p className="text-xs text-muted-foreground">{task.project ?? '-'}</p>
                                                    </td>
                                                    <td className="p-3">
                                                        {task.priority_quadrant ? (
                                                            <Badge
                                                                style={{
                                                                    backgroundColor: PRIORITY_COLOR[task.priority_quadrant],
                                                                    color: '#fff',
                                                                    borderColor: 'transparent',
                                                                }}
                                                            >
                                                                {task.priority_quadrant.toUpperCase()}
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">-</span>
                                                        )}
                                                    </td>
                                                    <td className="p-3">{TASK_TYPE_LABEL[task.task_type] ?? task.task_type}</td>
                                                    <td className="p-3">
                                                        <Badge style={{ backgroundColor: task.status.color, color: '#fff', borderColor: 'transparent' }}>
                                                            {task.status.name}
                                                        </Badge>
                                                    </td>
                                                    <td className="p-3">{task.assignees.join(', ') || '-'}</td>
                                                    <td className="p-3">{new Date(task.due_date).toLocaleDateString('id-ID')}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            <div className="mt-4 flex justify-center">
                                <Button type="button" variant="outline" size="sm" asChild>
                                    <Link href={route('tasks.all')}>Show more tugas →</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
