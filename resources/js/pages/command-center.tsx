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
//               Revisi (permintaan Boss): widget "Kategori Tugas Berulang" &
//               "Status Project" DIHAPUS dari halaman ini (frontend-only —
//               backend commandCenterPayload() TETAP mengirim task_categories/
//               status_projects untuk endpoint JSON commandCenter() yang lain,
//               field itu cuma sudah tidak didestrukturisasi/dirender di sini).
//               Revisi (permintaan Boss): widget "Beban per Kategori" ditambah --
//               stacked bar chart (recharts, via shadcn `ui/chart`) per member,
//               3 kategori (Jatah Harian/To Do/Selesai, MemberCategoryRow,
//               DashboardController::memberCategoryChart(), F-109 page-only).
//               "Selesai" DI SINI = jumlah menit task SELESAI (COUNT/cermin
//               murni, F-38) -- BUKAN skor KPI/LeaderboardService, F-4 TETAP
//               berlaku (nol rupiah/skor-kinerja di halaman ini). Revisi
//               2026-08-22 (permintaan Boss, REVERT dari persentase 2026-08-21):
//               chart TAMPIL MENIT MENTAH lagi -- sumbu Y dikunci domain [0, 480]
//               (480 = jatah harian standar, F-4 bukan rumus baru) supaya SETIAP
//               widget "Beban per Kategori" bisa dibandingkan apple-to-apple
//               antar member walau kapasitas per-user beda. Bar "Jatah Harian"
//               DI-CLAMP ke 0 kalau idle_real negatif (member overload/kerja
//               melebihi jatah) -- MURNI presentasi (F-38), angka asli tidak
//               diubah/dikirim ulang ke backend, cuma tidak digambar minus.
// DIPANGGIL   : DashboardController::commandCenterPage() (route 'dashboard/overview',
//               can:dashboard.view)
// MEMANGGIL   : formatLiveMinutes/classifyWorkload (REUSE F-52, sama persis
//               pages/dashboard.tsx — section "Beban Tim"), formatMenitPair/shiftMonth
//               (lib/command-center-format, F-131), recharts (ChartContainer/BarChart,
//               components/ui/chart.tsx)
// DATA MASUK  : field commandCenterPayload() yang DIPAKAI (summary_cards, donut_priority,
//               progress_distribution, heatmap, top_tasks, recent_activity,
//               workload_top5, filters, filter_users) + team {date,rows} (F-52,
//               loadRows()) + member_category_chart (page-only, lihat atas)
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
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { formatLiveMinutes } from '@/hooks/use-live-counter';
import { classifyWorkload } from '@/lib/dashboard-status';
import { formatMenitPair, shiftMonth } from '@/lib/command-center-format';
import { PRIORITY_QUADRANT_COLOR } from '@/lib/priority-quadrant';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Briefcase, CheckCircle2, ChevronLeft, ChevronRight, Clock, Eye, ListTodo, PlayCircle, Star, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Bar, BarChart, CartesianGrid, PolarRadiusAxis, RadialBar, RadialBarChart, XAxis, YAxis } from 'recharts';

interface SummaryCards {
    beban_harian: { used_minutes: number; capacity_minutes: number };
    todo: number;
    in_progress: number;
    review: number;
    // 2026-08-08 (permintaan Boss): kartu Tugas Selesai.
    selesai: number;
    overdue: number;
}

interface MeetingEvent {
    id: number;
    title: string;
    description: string | null;
    start_at: string;
    end_at: string;
    project: string | null;
    creator: string | null;
    participants: string[];
}

interface HeatmapDay {
    date: string;
    beban: number | null;
    level: 'aman' | 'tengah' | 'overload' | null;
    type: 'meeting' | 'libur' | null;
    holiday: string | null;
    meetings: MeetingEvent[];
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

// SUMBER: DashboardController::memberCategoryChart() -- 3 kategori DALAM MENIT
// (mentah, BUKAN persentase -- F-38, dihitung ulang di titik pakai), untuk
// team.date yang SAMA dengan tabel Team Work Load. longgar_minutes REUSE
// idle_real (nol rumus baru), todo/achievement = estimated_minutes dibagi
// rata assignee (F-96a) tugas due_date/completed_at jatuh di tanggal itu.
// `kapasitas` (permintaan Boss 2026-08-21): basis pembagi PERSENTASE chart --
// "Jatah Harian" jadi 100%, lihat toMemberCategoryChartPct().
interface MemberCategoryRow {
    id: number;
    name: string;
    kapasitas: number;
    longgar_minutes: number;
    todo_minutes: number;
    achievement_minutes: number;
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
    // Revisi 2026-08-06: viewer TANPA project.viewAll -- seluruh widget di atas
    // sudah DIBATASI ke data sendiri di backend (server guard). Flag ini juga
    // dipakai untuk teks penanda cakupan data (scopeLabel) & link "Tugas Saya".
    restricted_to_self: boolean;
    summary_cards: SummaryCards;
    donut_priority: Record<'p1' | 'p2' | 'p3' | 'p4' | 'none', number>;
    progress_distribution: { selesai: number; review: number; progress: number; todo: number };
    heatmap: { month: string; days: HeatmapDay[]; active_user_count: number };
    top_tasks: TopTask[];
    recent_activity: ActivityRow[];
    workload_top5: WorkloadRow[];
    team: { date: string; selected_user_id: number | null; rows: TeamRow[] };
    // Permintaan Boss: widget "Beban per Kategori" (stacked bar chart per
    // member) -- PAGE-ONLY (pola SAMA `team`, lihat KONTRAK memberCategoryChart()).
    member_category_chart: MemberCategoryRow[];
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
    p1: 'P1',
    p2: 'P2',
    p3: 'P3',
    p4: 'P4',
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

// Permintaan Boss: label tampilan "Longgar"/"Sedang" (key backend 'aman'/'tengah'
// TIDAK diubah -- itu flag F-44 dari DashboardController::heatmap(), ganti nama
// key butuh migrasi test/backend, di luar permintaan ini yang MURNI soal label
// & warna tampilan). Warna: overload=merah, sedang=hijau, longgar=abu-abu.
const HEATMAP_LEVEL_LABEL: Record<'aman' | 'tengah' | 'overload', string> = {
    aman: 'Longgar',
    tengah: 'Sedang',
    overload: 'Overload',
};

const HEATMAP_LEVEL_CLASS: Record<'aman' | 'tengah' | 'overload', string> = {
    aman: 'bg-gray-200 text-gray-900 dark:bg-gray-800 dark:text-gray-200',
    tengah: 'bg-green-300 text-green-900 dark:bg-green-950 dark:text-green-200',
    overload: 'bg-red-300 text-red-900 dark:bg-red-950 dark:text-red-200',
};

const STATUS_BADGE: Record<string, { label: string; className: string }> = {
    overload: { label: 'Overload', className: 'border-transparent bg-red-600 text-white hover:bg-red-600' },
    'idle-tinggi': { label: 'Idle tinggi', className: 'border-transparent bg-amber-500 text-white hover:bg-amber-500' },
    normal: { label: '', className: '' },
};

// Permintaan Boss (2026-08-22, REVERT dari persentase 2026-08-21): widget
// "Beban per Kategori" -- stacked bar chart per member, DALAM MENIT MENTAH
// (dataKey = field MemberCategoryRow apa adanya, nol turunan). Warna: Jatah
// Harian = grey, To Do = biru, Selesai = lime. Urutan tumpukan (Bar di JSX,
// BAWAH->ATAS): Selesai, To Do, Jatah Harian -- Jatah Harian SENGAJA di ATAS
// (mewakili SISA kapasitas yang belum terpakai), pola mockup Boss.
// Revisi 2026-08-21 (permintaan Boss): shade dipertajam 1 tingkat dari
// default Tailwind (slate-400->500, blue-500->600, lime-500->600) --
// background card di app ini PUTIH, shade -500/-400 default kontrasnya
// terlalu rendah (grey pudar, lime pucat) terhadap putih, jadi batang chart
// sulit dibedakan dari card-nya sendiri.
const MEMBER_CATEGORY_CONFIG = {
    achievement_minutes: { label: 'Selesai', color: '#65a30d' }, // lime-600
    todo_minutes: { label: 'To Do', color: '#2563eb' }, // blue-600
    longgar_minutes: { label: 'Jatah Harian', color: '#76ABAE' }, // slate-500
} satisfies ChartConfig;

// Permintaan Boss: card "Team Work Load" & modal "Detail & filter"-nya BUTUH
// sort per kolom -- MURNI re-urut baris yang SUDAH dikirim backend (team.rows,
// F-52), nol angka beban/idle baru dihitung di sini. `status` diturunkan dari
// classifyWorkload() yang SAMA dipakai badge (bukan derivasi baru).
// Revisi 2026-08-15 (permintaan Boss): kolom "Kapasitas Sisa" pindah basis dari
// idle_plan (kapasitas - estimasi rencana) ke idle_real (kapasitas - realisasi
// AKUMULASI hari itu, dari task_time_segments) -- "Waktu Terpakai" (row.aktif)
// TETAP sesi yang sedang berjalan, TIDAK ikut berubah. idle_real sudah dihitung
// & dikirim backend (DashboardService::forUsers()), cuma belum pernah dipakai
// di sini -- nol rumus baru, murni pindah field mana yang dirender.
type TeamSortKey = 'name' | 'aktif' | 'beban' | 'idle_real' | 'status';
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

// Permintaan Boss: loading screen profesional (skeleton, bukan spinner/dim) --
// SATU kerangka baris tabel dipakai ulang di SEMUA tabel widget (Team Work Load,
// modalnya, Top-10 Task, modal Workload Tim per-tanggal) supaya
// bentuknya konsisten & nol duplikasi markup per tabel. `rows`/`cols` disesuaikan
// jumlah baris/kolom tabel asli tiap widget supaya tinggi kerangka mendekati
// tinggi konten asli (nol "lompat" layout pas data masuk).
function TableSkeletonRows({ rows, cols }: { rows: number; cols: number }) {
    return (
        <>
            {Array.from({ length: rows }).map((_, r) => (
                <tr key={r} className="border-b last:border-0">
                    {Array.from({ length: cols }).map((_, c) => (
                        <td key={c} className="p-3">
                            <Skeleton className="h-4 w-full max-w-32" />
                        </td>
                    ))}
                </tr>
            ))}
        </>
    );
}

export default function CommandCenter({
    restricted_to_self: restrictedToSelf,
    summary_cards: cards,
    donut_priority: donut,
    progress_distribution: progress,
    heatmap,
    top_tasks: topTasks,
    recent_activity: recentActivity,
    team,
    member_category_chart: memberCategoryChart,
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

    // Revisi 2026-08-06 (permintaan Boss): label widget WAJIB beda antara admin
    // (data agregat seluruh tim) dan viewer terbatas (data cuma dirinya sendiri)
    // -- supaya tidak ada kesan angka yang sama artinya sama utk keduanya. Nol
    // logic baru di sini, data-nya SUDAH beda dari backend (restrictedToSelf),
    // ini MURNI teks penanda.
    const scopeLabel = (base: string) => `${base} ${restrictedToSelf ? 'Saya' : 'Sistem'}`;

    // §12.5: tombol global Last Week/Last Month/Pilih Tanggal -- broadcast SATU
    // rentang ke widget berbasis periode sekaligus (donut/progress/top-10/recent).
    // Heatmap & Workload sengaja TIDAK ikut (lihat KONTRAK heatmap()/workload_top5
    // di DashboardController -- alasan F-131/F-118).
    const RANGE_PREFIXES = ['donut', 'progress', 'top_tasks', 'activity'] as const;
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
    // kolom cuma re-urut 5 baris hasil seleksi ini, BUKAN memilih ulang top-5
    // lain per kolom.
    const teamTop5 = [...team.rows].sort((a, b) => b.idle_real - a.idle_real).slice(0, 5);
    const [teamSort, setTeamSort] = useState<{ key: TeamSortKey; dir: 'asc' | 'desc' }>({ key: 'idle_real', dir: 'desc' });
    const sortedTeamTop5 = sortTeamRows(teamTop5, teamSort);
    const toggleTeamSort = (key: TeamSortKey) => {
        setTeamSort((prev) => (prev.key === key ? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'desc' }));
    };

    // Permintaan Boss (2026-08-22): widget "Beban per Kategori" -- MENIT mentah
    // apa adanya dari backend (longgar_minutes SUDAH di-clamp non-negatif di
    // DashboardController::memberCategoryChart(), F-38 -- nol olahan tambahan
    // di sini, beda dari sebelumnya yang masih clamp ulang di frontend).
    const memberCategoryChartData = memberCategoryChart;

    // Permintaan Boss (2026-08-22): chart "Beban per Kategori" dipaginasi 10
    // member per halaman -- widget jadi tidak melebar tak terkendali kalau tim
    // besar. MURNI potongan tampilan (slice), TIDAK mengubah data/urutan asli
    // dari backend, dan teamCategoryTotals/teamCategoryRadialData di BAWAH
    // TETAP pakai memberCategoryChartData PENUH (bukan halaman aktif) -- radial
    // "Komposisi Beban Tim" harus selalu total SEMUA member, bukan cuma yang
    // sedang tampil di halaman chart batang.
    const CHART_PAGE_SIZE = 10;
    const [chartPage, setChartPage] = useState(0);
    const chartPageCount = Math.max(1, Math.ceil(memberCategoryChartData.length / CHART_PAGE_SIZE));
    // GUARD: kalau halaman aktif jadi tidak valid (mis. filter/tanggal ganti,
    // roster menyusut) -- turun ke halaman terakhir yang masih ada, bukan
    // diam-diam nge-render array kosong.
    const safeChartPage = Math.min(chartPage, chartPageCount - 1);
    const pagedMemberCategoryChartData = memberCategoryChartData.slice(
        safeChartPage * CHART_PAGE_SIZE,
        safeChartPage * CHART_PAGE_SIZE + CHART_PAGE_SIZE,
    );

    // Permintaan Boss (2026-08-22): widget "Prioritas Tugas" ditambah Simple
    // Radial Bar Chart (recharts) -- 3 kategori SAMA dengan "Beban per Kategori"
    // di atasnya, DIJUMLAHKAN lintas SELURUH member jadi 1 angka tim per
    // kategori (F-38 -- nol query baru, murni penjumlahan client-side dari
    // memberCategoryChartData yang SUDAH difetch). `kapasitas` ikut dijumlah
    // jadi domain radial (lihat JSX) supaya panjang tiap busur proporsional
    // terhadap "jatah harian tim" -- pola sama YAxis [0,480] chart batang di
    // atas, cuma basisnya sekarang per-tim bukan per-orang.
    const teamCategoryTotals = memberCategoryChartData.reduce(
        (totals, row) => ({
            achievement_minutes: totals.achievement_minutes + row.achievement_minutes,
            todo_minutes: totals.todo_minutes + row.todo_minutes,
            longgar_minutes: totals.longgar_minutes + row.longgar_minutes,
            kapasitas: totals.kapasitas + row.kapasitas,
        }),
        { achievement_minutes: 0, todo_minutes: 0, longgar_minutes: 0, kapasitas: 0 },
    );
    const teamCategoryRadialData = (['achievement_minutes', 'todo_minutes', 'longgar_minutes'] as const).map((key) => ({
        key,
        value: teamCategoryTotals[key],
        fill: MEMBER_CATEGORY_CONFIG[key].color,
    }));

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

    // Permintaan Boss: klik tanggal di Kalender Beban Tim -> modal detail
    // acara/peristiwa (libur + meeting hari itu, MURNI data yang SUDAH dikirim
    // di heatmap.days -- nol fetch tambahan untuk bagian ini). Data workload
    // per-user DI FETCH terpisah saat modal dibuka (route('dashboard.summary'),
    // endpoint JSON YANG SUDAH ADA sejak H2 -- REUSE loadRows()/forUsers() yang
    // SAMA dipakai dashboard.tsx & section "Beban Tim", nol rumus baru). Tidak
    // di-precompute untuk 30+ hari sekaligus di heatmap() -- itu akan melanggar
    // F-85 (query bertumbuh dengan jumlah hari), jadi lazy-fetch PER TANGGAL
    // yang benar-benar diklik.
    const [selectedDay, setSelectedDay] = useState<HeatmapDay | null>(null);
    const [dayWorkload, setDayWorkload] = useState<{ date: string; rows: TeamRow[] } | null>(null);
    const [dayWorkloadLoading, setDayWorkloadLoading] = useState(false);

    // Permintaan Boss: sort per kolom tabel "Workload Tim" di modal kalender --
    // pola SAMA teamSort/teamModalSort (reuse TeamSortKey/sortTeamRows yang sudah
    // ada), murni re-urut baris yang SUDAH di-fetch, nol fetch ulang per kolom.
    const [dayWorkloadSort, setDayWorkloadSort] = useState<{ key: TeamSortKey; dir: 'asc' | 'desc' }>({ key: 'name', dir: 'asc' });
    const sortedDayWorkload = dayWorkload ? sortTeamRows(dayWorkload.rows, dayWorkloadSort) : [];
    const toggleDayWorkloadSort = (key: TeamSortKey) => {
        setDayWorkloadSort((prev) => (prev.key === key ? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'desc' }));
    };

    const openDayModal = (day: HeatmapDay) => {
        setSelectedDay(day);
        setDayWorkload(null);
        setDayWorkloadLoading(true);

        fetch(route('dashboard.summary', { date: day.date }), { headers: { Accept: 'application/json' } })
            .then((res) => res.json())
            .then((data: { date: string; users: TeamRow[] }) => setDayWorkload({ date: data.date, rows: data.users }))
            .finally(() => setDayWorkloadLoading(false));
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
                    <div>
                        <h1 className="text-xl font-semibold">Command Center</h1>
                        {/* Revisi 2026-08-06: penanda cakupan data di level halaman -- viewer
                            terbatas (project.viewAll) WAJIB langsung sadar semua angka di
                            bawah ini milik dirinya sendiri, bukan seluruh tim. */}
                        {restrictedToSelf && <p className="text-xs text-muted-foreground">Menampilkan data milik kamu sendiri.</p>}
                    </div>
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

                {/* A2: 6 kartu ringkas -- statis (nol klik-filter, keputusan Boss
                    2026-07-29: halaman "Semua Tugas" lintas-project belum ada, DAN
                    §12.5 tak menyebut kartu ini di daftar 7 widget berfilter).
                    Kartu "Selesai" ditambah 2026-08-08 (permintaan Boss). */}
                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{scopeLabel('Beban Harian')}</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">
                            {navigating ? (
                                <Skeleton className="h-7 w-24" />
                            ) : (
                                formatMenitPair(cards.beban_harian.used_minutes, cards.beban_harian.capacity_minutes)
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{scopeLabel('To Do')}</CardTitle>
                            <ListTodo className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">
                            {navigating ? <Skeleton className="h-7 w-10" /> : cards.todo}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{scopeLabel('In Progress')}</CardTitle>
                            <PlayCircle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">
                            {navigating ? <Skeleton className="h-7 w-10" /> : cards.in_progress}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{scopeLabel('Review')}</CardTitle>
                            <Eye className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">
                            {navigating ? <Skeleton className="h-7 w-10" /> : cards.review}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{scopeLabel('Selesai')}</CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">
                            {navigating ? <Skeleton className="h-7 w-10" /> : cards.selesai}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{scopeLabel('Overdue')}</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">
                            {navigating ? <Skeleton className="h-7 w-10" /> : cards.overdue}
                        </CardContent>
                    </Card>
                </div>

                {/* Permintaan Boss: widget "Beban per Kategori" -- stacked bar
        chart per member DALAM MENIT (longgar/to do/selesai, MemberCategoryRow,
        F-109 page-only -- lihat KONTRAK DashboardController::memberCategoryChart()).
        Tanggal SAMA dengan tabel Team Work Load (section di bawah). Posisi
        DIPINDAH ke ATAS widget "Prioritas Tugas"/"Distribusi Progress"
        (permintaan Boss 2026-08-22, murni urutan tampilan -- data/filter TIDAK
        berubah). */}
                <div className="grid grid-cols-1 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {scopeLabel('Beban per Kategori')} — {team.date}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {navigating ? (
                                <Skeleton className="h-64 w-full" />
                            ) : memberCategoryChartData.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Tidak ada user aktif untuk ditampilkan.</p>
                            ) : (
                                <ChartContainer config={MEMBER_CATEGORY_CONFIG} className="aspect-auto h-64 w-full">
                                    <BarChart data={pagedMemberCategoryChartData} margin={{ left: 4, right: 4 }}>
                                        {/* Permintaan Boss (2026-08-22): tiap kategori pakai GRADASI warna
                                        (bukan flat) -- linearGradient vertikal, terang di atas ke warna
                                        dasar MEMBER_CATEGORY_CONFIG di bawah. id di-prefix "mcat-" supaya
                                        tidak tabrakan sama elemen SVG lain di halaman ini.
                                        Revisi 2026-08-22 (permintaan Boss): gradasi "Jatah Harian" (abu)
                                        DIBUAT LEBIH TRANSPARAN (stopOpacity, GRADASI TETAP ADA) supaya
                                        secara visual lebih redup/tidak mendominasi dibanding Selesai/To Do
                                        -- warna hex TIDAK diubah, cuma opacity tiap stop-nya diturunkan. */}
                                        <defs>
                                            <linearGradient id="mcat-achievement" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stopColor="#bef264" />
                                                <stop offset="100%" stopColor="#65a30d" />
                                            </linearGradient>
                                            <linearGradient id="mcat-todo" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stopColor="#93c5fd" />
                                                <stop offset="100%" stopColor="#2563eb" />
                                            </linearGradient>
                                            <linearGradient id="mcat-longgar" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stopColor="#cbd5e1" stopOpacity={0.45} />
                                                <stop offset="100%" stopColor="#64748b" stopOpacity={0.45} />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid vertical={false} />
                                        <XAxis dataKey="name" tickLine={false} axisLine={false} tickMargin={8} />
                                        {/* SUMBER (permintaan Boss 2026-08-22): domain DIKUNCI [0, 480] --
                                        480 menit = jatah harian standar (F-4, bukan rumus baru, cuma batas
                                        tampilan). allowDataOverflow FALSE (default) supaya batang yang
                                        melebihi 480 (gabungan kategori overload) tetap kelihatan dipotong
                                        di puncak, bukan mendorong domain naik -- dan `longgar_minutes` sudah
                                        di-clamp non-negatif di DashboardController::memberCategoryChart()
                                        (backend) jadi sumbu TIDAK PERNAH turun di bawah 0. */}
                                        <YAxis
                                            tickLine={false}
                                            axisLine={false}
                                            width={56}
                                            domain={[0, 480]}
                                            tickFormatter={(v: number) => formatLiveMinutes(v)}
                                        />
                                        <ChartTooltip
                                            content={
                                                <ChartTooltipContent
                                                    formatter={(value, name) => {
                                                        const key = name as keyof typeof MEMBER_CATEGORY_CONFIG;

                                                        return (
                                                            <div className="flex w-full items-center gap-2">
                                                                <span
                                                                    className="h-2.5 w-2.5 shrink-0 rounded-[2px]"
                                                                    style={{ backgroundColor: MEMBER_CATEGORY_CONFIG[key].color }}
                                                                />
                                                                <span className="flex-1 text-muted-foreground">
                                                                    {MEMBER_CATEGORY_CONFIG[key].label}
                                                                </span>
                                                                <span className="font-mono font-medium tabular-nums text-foreground">
                                                                    {formatLiveMinutes(value as number)}
                                                                </span>
                                                            </div>
                                                        );
                                                    }}
                                                />
                                            }
                                        />
                                        {/* Urutan tumpukan BAWAH->ATAS: Selesai, To Do, Jatah Harian --
                                        Jatah Harian di ATAS (sisa kapasitas belum terpakai), pola mockup Boss. */}
                                        <Bar dataKey="achievement_minutes" stackId="beban" fill="url(#mcat-achievement)" />
                                        <Bar dataKey="todo_minutes" stackId="beban" fill="url(#mcat-todo)" />
                                        <Bar dataKey="longgar_minutes" stackId="beban" fill="url(#mcat-longgar)" radius={[4, 4, 0, 0]} />
                                    </BarChart>
                                </ChartContainer>
                            )}
                            {/* Permintaan Boss (2026-08-22): kontrol paginasi -- cuma tampil
                            kalau member LEBIH dari 1 halaman (CHART_PAGE_SIZE=10). Pola
                            tombol Prev/Next SAMA navigasi bulan heatmap di bawah (ChevronLeft/
                            ChevronRight), murni geser slice tampilan, nol fetch/query baru. */}
                            {!navigating && chartPageCount > 1 && (
                                <div className="mt-3 flex items-center justify-center gap-3 text-xs text-muted-foreground">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setChartPage((p) => Math.max(0, p - 1))}
                                        disabled={safeChartPage === 0}
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                    </Button>
                                    <span>
                                        Halaman {safeChartPage + 1} dari {chartPageCount}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setChartPage((p) => Math.min(chartPageCount - 1, p + 1))}
                                        disabled={safeChartPage >= chartPageCount - 1}
                                    >
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            )}
                            {/* Legend manual (bukan ChartLegend/recharts) -- 3 kategori TETAP
                            (bukan dari payload dinamis), pola sederhana SAMA legend heatmap
                            di bawah (span warna + label). */}
                            <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                                {(Object.keys(MEMBER_CATEGORY_CONFIG) as (keyof typeof MEMBER_CATEGORY_CONFIG)[]).map((key) => (
                                    <div key={key} className="flex items-center gap-1.5">
                                        <span className="h-2.5 w-2.5 rounded-[2px]" style={{ backgroundColor: MEMBER_CATEGORY_CONFIG[key].color }} />
                                        <span>{MEMBER_CATEGORY_CONFIG[key].label}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Revisi 2026-08-22 (permintaan Boss): layout dipecah rasio 2:1 (bukan
                50/50 lagi) -- "Prioritas Tugas" LEBIH LEBAR (lg:col-span-2) karena
                sekarang berisi 2 pie chart (donut Prioritas + radial Komposisi Beban
                Tim), "Distribusi Progress" jadi 1/3 bagian (lg:col-span-1). */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {/* A3: Donut prioritas */}
                    <Card className="lg:col-span-2">
                        <CardHeader className="flex flex-col gap-2">
                            <CardTitle className="text-base">{scopeLabel('Prioritas Tugas')}</CardTitle>
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
                            {/* Revisi 2026-08-22 (permintaan Boss "line vertikal jangan patah"):
                            chart + legend masing-masing SEKARANG SATU KOLOM (donut+legend
                            Prioritas di kiri, radial+legend Beban Tim di kanan), dengan SATU
                            <Separator> di tengah yang MEMBENTANG PENUH via CSS Grid stretch
                            (kolom tengah "auto" TANPA height fixed -- align-items grid default
                            "stretch" otomatis menyamakan tingginya dengan kolom tertinggi).
                            SEBELUMNYA ada 2 potongan garis terpisah (Separator h-56 di baris
                            chart + border-l di baris legend) yang keliatan patah/tidak
                            menyambung -- sekarang murni SATU elemen garis, nol jahitan. */}
                            <div className="grid grid-cols-2 gap-10 sm:grid-cols-[1fr_auto_1fr]">
                                <div className="flex flex-col items-center gap-4 mt-10">
                                    {navigating ? (
                                        <Skeleton className="h-60 w-60 shrink-0 rounded-full" />
                                    ) : donutChart.total === 0 ? (
                                        <p className="text-sm text-muted-foreground">Belum ada task untuk ditandai prioritas.</p>
                                    ) : (
                                        <div className="relative h-40 w-40 shrink-0 rounded-full" style={{ background: donutChart.gradient }}>
                                            <div className="absolute inset-4 flex items-center justify-center rounded-full bg-card text-base font-semibold">
                                                {donutChart.total}
                                            </div>
                                        </div>
                                    )}

                                    {navigating ? (
                                        <div className="flex w-full flex-col gap-2">
                                            {Array.from({ length: 3 }).map((_, i) => (
                                                <Skeleton key={i} className="h-3 w-32" />
                                            ))}
                                        </div>
                                    ) : (
                                        donutChart.total > 0 && (
                                            <div className="w-full text-xs mt-12">
                                                <p className="mb-1.5 font-semibold text-foreground">Prioritas</p>
                                                <ul className="grid grid-cols-2 gap-x-4 gap-y-1.5">
                                                    {(['p1', 'p2', 'p3', 'p4', 'none'] as const).map((key) => (
                                                        <li key={key} className="flex items-center gap-1.5">
                                                            <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: PRIORITY_COLOR[key] }} />
                                                            <span className="text-muted-foreground">{PRIORITY_LABEL[key]}</span>
                                                            <span className="font-medium">{donut[key]}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        )
                                    )}
                                </div>

                                <Separator orientation="vertical" className="hidden sm:block" />

                                <div className="flex flex-col items-center gap-4">
                                    {/* Permintaan Boss (2026-08-22): Simple Radial Bar Chart (recharts)
                                    -- ringkasan 3 kategori "Beban per Kategori" DIJUMLAHKAN se-tim
                                    (bukan per-member seperti chart di atas). Data & warna REUSE
                                    memberCategoryChartData/MEMBER_CATEGORY_CONFIG (F-109, nol
                                    fetch/config baru). Ukuran h-56 w-56 -- SENGAJA lebih besar dari
                                    donut Prioritas (permintaan Boss "perbesar lagi chart beban
                                    tim"), barSize 16 supaya ketebalan cincin proporsional. */}
                                    {navigating ? (
                                        <Skeleton className="h-56 w-56 shrink-0 rounded-full" />
                                    ) : teamCategoryTotals.kapasitas === 0 ? (
                                        <p className="text-sm text-muted-foreground">Tidak ada user aktif untuk ditampilkan.</p>
                                    ) : (
                                        <ChartContainer config={MEMBER_CATEGORY_CONFIG} className="aspect-square h-60 w-60 shrink-0">
                                            <RadialBarChart
                                                data={teamCategoryRadialData}
                                                innerRadius="30%"
                                                outerRadius="100%"
                                                startAngle={90}
                                                endAngle={-270}
                                                barSize={16}
                                                margin={{ top: 0, right: 0, bottom: 0, left: 0 }}
                                            >
                                                <PolarRadiusAxis type="number" domain={[0, teamCategoryTotals.kapasitas]} tick={false} axisLine={false} />
                                                <RadialBar
                                                    dataKey="value"
                                                    cornerRadius={4}
                                                    /* Diubah dari boolean 'background' ke objek warna */
                                                    background={{ fill: "#f0f" }} // atau warna hex seperti "#ffff" / "rgba(255,255,255,0.1)"
                                                />
                                                <ChartTooltip
                                                    content={
                                                        <ChartTooltipContent
                                                            hideLabel
                                                            formatter={(value, _name, item) => {
                                                                const row = item.payload as { key: keyof typeof MEMBER_CATEGORY_CONFIG };
                                                                const cfg = MEMBER_CATEGORY_CONFIG[row.key];

                                                                return (
                                                                    <div className="flex w-full items-center gap-2">
                                                                        <span className="h-2.5 w-2.5 shrink-0 rounded-[2px]" style={{ backgroundColor: cfg.color }} />
                                                                        <span className="flex-1 text-muted-foreground">{cfg.label}</span>
                                                                        <span className="font-mono font-medium tabular-nums text-foreground">
                                                                            {formatLiveMinutes(value as number)}
                                                                        </span>
                                                                    </div>
                                                                );
                                                            }}
                                                        />
                                                    }
                                                />
                                            </RadialBarChart>
                                        </ChartContainer>
                                    )}

                                    {navigating ? (
                                        <div className="flex w-full flex-col gap-2">
                                            {Array.from({ length: 3 }).map((_, i) => (
                                                <Skeleton key={i} className="h-3 w-32" />
                                            ))}
                                        </div>
                                    ) : (
                                        teamCategoryTotals.kapasitas > 0 && (
                                            <div className="w-full text-xs">
                                                <p className="mb-1.5 font-semibold text-foreground">Beban Tim</p>
                                                <div className="flex flex-wrap gap-x-4 gap-y-1.5">
                                                    {(Object.keys(MEMBER_CATEGORY_CONFIG) as (keyof typeof MEMBER_CATEGORY_CONFIG)[]).map((key) => (
                                                        <div key={key} className="flex items-center gap-1.5">
                                                            <span className="h-2.5 w-2.5 rounded-[2px]" style={{ backgroundColor: MEMBER_CATEGORY_CONFIG[key].color }} />
                                                            <span className="text-muted-foreground">{MEMBER_CATEGORY_CONFIG[key].label}</span>
                                                            <span className="font-medium">{formatLiveMinutes(teamCategoryTotals[key])}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* A4: distribusi progress */}
                    <Card className="lg:col-span-1">
                        <CardHeader className="flex flex-col gap-2">
                            <CardTitle className="text-base">{scopeLabel('Distribusi Progress')}</CardTitle>
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
                            {navigating ? (
                                <div className="flex flex-col gap-3">
                                    {Array.from({ length: 4 }).map((_, i) => (
                                        <div key={i} className="flex flex-col gap-1">
                                            <Skeleton className="h-3 w-16" />
                                            <Skeleton className="h-2 w-full rounded-full" />
                                        </div>
                                    ))}
                                </div>
                            ) : progressTotal === 0 ? (
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
                </div>

                <div className="grid grid-cols-1 gap-4">
                    {/* F-52/F-121: dashboard 3-angka lama DIPERTAHANKAN sebagai section "Beban
        Tim" -- Permintaan Boss: top-5 idle terbanyak + sort per kolom + modal
        "Detail & filter" (menggantikan Link ke halaman dashboard lama).
        Revisi (hapus widget "Status Project" dari halaman ini): grid ini kini
        HANYA berisi kartu ini, jadi tanpa lg:grid-cols-2/col-span. */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-base">{scopeLabel('Team Work Load')} — {team.date}</CardTitle>
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
                                                ['idle_real', 'Kapasitas Sisa'],
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
                                    {navigating ? (
                                        <TableSkeletonRows rows={5} cols={3} />
                                    ) : (
                                        <>
                                            {sortedTeamTop5.map((row) => {
                                                const status = classifyWorkload(row.beban, row.kapasitas);
                                                const badge = STATUS_BADGE[status];

                                                return (
                                                    <tr key={row.id} className="border-b last:border-0 align-top">
                                                        <td className="p-3 font-medium">{row.name}</td>
                                                        <td className="p-3">
                                                            {formatLiveMinutes(row.kapasitas - row.idle_real)} (idle{' '}
                                                            {formatLiveMinutes(row.idle_real)})
                                                        </td>
                                                        <td className="p-3">
                                                            {badge.label && <Badge className={badge.className}>{badge.label}</Badge>}
                                                        </td>
                                                    </tr>
                                                );
                                            })}

                                            {sortedTeamTop5.length === 0 && (
                                                <tr>
                                                    <td colSpan={3} className="p-6 text-center text-muted-foreground">
                                                        Tidak ada user aktif untuk ditampilkan.
                                                    </td>
                                                </tr>
                                            )}
                                        </>
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
                                <DialogTitle>{scopeLabel('Team Work Load')} — Detail &amp; Filter</DialogTitle>
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
                                                    ['idle_real', 'Kapasitas Sisa'],
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
                                        {navigating ? (
                                            <TableSkeletonRows rows={6} cols={3} />
                                        ) : (
                                            <>
                                                {sortedTeamAll.map((row) => {
                                                    const status = classifyWorkload(row.beban, row.kapasitas);
                                                    const badge = STATUS_BADGE[status];

                                                    return (
                                                        <tr key={row.id} className="border-b last:border-0 align-top">
                                                            <td className="p-3 font-medium">{row.name}</td>
                                                            <td className="p-3">
                                                                {formatLiveMinutes(row.kapasitas - row.idle_real)} (idle{' '}
                                                                {formatLiveMinutes(row.idle_real)})
                                                            </td>
                                                            <td className="p-3">
                                                                {badge.label && <Badge className={badge.className}>{badge.label}</Badge>}
                                                            </td>
                                                        </tr>
                                                    );
                                                })}

                                                {sortedTeamAll.length === 0 && (
                                                    <tr>
                                                        <td colSpan={3} className="p-6 text-center text-muted-foreground">
                                                            Tidak ada user aktif untuk ditampilkan.
                                                        </td>
                                                    </tr>
                                                )}
                                            </>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </DialogContent>
                    </Dialog>

                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {/* A6: master calendar heatmap */}
                    <Card>
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2 space-y-0">
                            <CardTitle className="text-base">{scopeLabel('Kalender Beban')} — {heatmap.month}</CardTitle>
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

                            {/* Grid Kalender -- A10: navigasi bulan/filter user me-reload heatmap.days
                                lewat props (Inertia visit penuh), makanya di-skeleton saat `navigating`
                                (bukan `dayWorkloadLoading`, itu punya modal detail hari terpisah). 35 sel
                                (5 baris x 7 kolom) MENDEKATI jumlah hari rata-rata sebulan -- MURNI
                                perkiraan visual, bukan angka presisi (leadingBlank ikut dihitung ulang
                                begitu heatmap.days baru datang). */}
                            <div className="grid grid-cols-7 gap-1">
                                {navigating
                                    ? Array.from({ length: 35 }).map((_, i) => <Skeleton key={i} className="aspect-square rounded-lg" />)
                                    : null}
                                {!navigating && Array.from({ length: leadingBlank }).map((_, i) => (
                                    <div key={`blank-${i}`} />
                                ))}
                                {!navigating && heatmap.days.map((day) => {
                                    // F-131: hari LEWAT (level null) -- NETRAL, abu-abu
                                    const cellClass = day.level ? HEATMAP_LEVEL_CLASS[day.level] : 'bg-muted text-muted-foreground';

                                    return (
                                        <button
                                            type="button"
                                            key={day.date}
                                            onClick={() => openDayModal(day)}
                                            /* flex-col & p-1 memastikan posisi angka dan icon muat di dalam kotak secara vertikal */
                                            className={`flex aspect-square flex-col items-center justify-between p-1.5 rounded-lg text-xs font-semibold hover:bg-primary/30 transition-all duration-200 hover:-translate-y-1 hover:shadow cursor-pointer ${cellClass}`}
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
                                        </button>
                                    );
                                })}
                            </div>

                            {/* Section Legend di Bawah Kalender */}
                            <div className="mt-4 pt-3 border-t border-border space-y-2">
                                <div className="flex flex-wrap items-center justify-start gap-4 text-xs text-muted-foreground font-medium">
                                    {/* Status Beban Warna */}
                                    {(['aman', 'tengah', 'overload'] as const).map((level) => (
                                        <div key={level} className="flex items-center gap-1.5">
                                            <span className={`h-3 w-3 rounded-sm ${HEATMAP_LEVEL_CLASS[level]}`} />
                                            <span>{HEATMAP_LEVEL_LABEL[level]}</span>
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

                    {/* Permintaan Boss: modal detail acara/peristiwa per tanggal -- MURNI
        render ulang data yang SUDAH ada di heatmap.days (holiday/meetings),
        nol fetch tambahan saat modal dibuka. */}
                    <Dialog
                        open={selectedDay !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setSelectedDay(null);
                                setDayWorkload(null);
                            }
                        }}
                    >
                        <DialogContent className="max-h-[85vh] max-w-lg overflow-y-auto">
                            <DialogHeader>
                                <DialogTitle>
                                    {selectedDay &&
                                        new Date(`${selectedDay.date}T00:00:00`).toLocaleDateString('id-ID', {
                                            weekday: 'long',
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                        })}
                                </DialogTitle>
                            </DialogHeader>

                            {selectedDay && (
                                <div className="flex flex-col gap-4 text-sm">
                                    <p className="text-muted-foreground">
                                        {selectedDay.beban === null
                                            ? 'Hari lewat (netral, tidak dihitung).'
                                            : `Beban tim hari ini: ${formatLiveMinutes(selectedDay.beban)}`}
                                    </p>

                                    {/* Permintaan Boss: data workload per-user untuk tanggal ini --
                                        di-fetch lazy dari route('dashboard.summary', {date}), endpoint
                                        JSON yang SUDAH ADA (F-52/H2), nol rumus baru. */}
                                    <div className="flex flex-col gap-2">
                                        <p className="font-medium">Workload Tim</p>
                                        {dayWorkloadLoading ? (
                                            <div className="overflow-x-auto rounded-md border">
                                                <table className="w-full text-left text-sm">
                                                    <tbody>
                                                        <TableSkeletonRows rows={4} cols={4} />
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : dayWorkload && dayWorkload.rows.length > 0 ? (
                                            <div className="overflow-x-auto rounded-md border">
                                                <table className="w-full text-left text-sm">
                                                    <thead>
                                                        <tr className="border-b bg-muted/50 text-muted-foreground">
                                                            {(
                                                                [
                                                                    ['name', 'Tim'],
                                                                    ['aktif', 'Waktu Terpakai'],
                                                                    ['idle_real', 'Kapasitas Sisa'],
                                                                    ['status', 'Status'],
                                                                ] as [TeamSortKey, string][]
                                                            ).map(([key, label]) => (
                                                                <th key={key} className="p-2">
                                                                    <button
                                                                        type="button"
                                                                        className="flex items-center gap-1 font-medium hover:text-foreground"
                                                                        onClick={() => toggleDayWorkloadSort(key)}
                                                                    >
                                                                        {label}
                                                                        {dayWorkloadSort.key === key && <span>{dayWorkloadSort.dir === 'asc' ? '↑' : '↓'}</span>}
                                                                    </button>
                                                                </th>
                                                            ))}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {sortedDayWorkload.map((row) => {
                                                            const status = classifyWorkload(row.beban, row.kapasitas);
                                                            const badge = STATUS_BADGE[status];

                                                            return (
                                                                <tr key={row.id} className="border-b last:border-0 align-top">
                                                                    <td className="p-2 font-medium">{row.name}</td>
                                                                    <td className="p-2">{formatLiveMinutes(row.aktif)}</td>
                                                                    <td className="p-2">
                                                                        {formatLiveMinutes(row.kapasitas - row.idle_real)} (idle{' '}
                                                                        {formatLiveMinutes(row.idle_real)})
                                                                    </td>
                                                                    <td className="p-2">
                                                                        {badge.label && <Badge className={badge.className}>{badge.label}</Badge>}
                                                                    </td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <p className="text-muted-foreground">Tidak ada user aktif untuk ditampilkan.</p>
                                        )}
                                    </div>

                                    {selectedDay.holiday && (
                                        <div className="flex items-start gap-2 rounded-md border bg-muted/30 p-3">
                                            <Star className="mt-0.5 h-4 w-4 shrink-0" />
                                            <div>
                                                <p className="font-medium">Hari Libur</p>
                                                <p className="text-muted-foreground">{selectedDay.holiday}</p>
                                            </div>
                                        </div>
                                    )}

                                    {selectedDay.meetings.length > 0 && (
                                        <div className="flex flex-col gap-2">
                                            <p className="font-medium">Meeting ({selectedDay.meetings.length})</p>
                                            {selectedDay.meetings.map((meeting) => (
                                                <div key={meeting.id} className="rounded-md border p-3">
                                                    <div className="flex items-start gap-2">
                                                        <Briefcase className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                                                        <div className="min-w-0">
                                                            <p className="font-medium">{meeting.title}</p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {new Date(meeting.start_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}
                                                                {' – '}
                                                                {new Date(meeting.end_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}
                                                                {meeting.project && ` · ${meeting.project}`}
                                                            </p>
                                                            {meeting.description && <p className="mt-1 text-xs">{meeting.description}</p>}
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                Peserta: {meeting.participants.join(', ') || '-'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {!selectedDay.holiday && selectedDay.meetings.length === 0 && (
                                        <p className="text-center text-muted-foreground">Tidak ada acara/peristiwa tercatat pada tanggal ini.</p>
                                    )}
                                </div>
                            )}
                        </DialogContent>
                    </Dialog>

                    {/* A9: recent activity -- label APA ADANYA dari ActivityLogPresenter (F-106) */}
                    <Card>
                        <CardHeader className="flex flex-col gap-2">
                            <CardTitle className="text-base">{scopeLabel('Aktivitas Terbaru')}</CardTitle>
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
                            {navigating ? (
                                <div className="flex flex-col gap-2">
                                    {Array.from({ length: 5 }).map((_, i) => (
                                        <div key={i} className="flex items-center justify-between gap-2 border-b pb-2 last:border-0">
                                            <Skeleton className="h-4 w-48" />
                                            <Skeleton className="h-3 w-24 shrink-0" />
                                        </div>
                                    ))}
                                </div>
                            ) : recentActivity.length === 0 ? (
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
                            <CardTitle className="text-base">{scopeLabel('Top-10 Task Prioritas')}</CardTitle>
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
                            {navigating ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <tbody>
                                            <TableSkeletonRows rows={8} cols={6} />
                                        </tbody>
                                    </table>
                                </div>
                            ) : topTasks.length === 0 ? (
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
                                                <tr key={task.id} onClick={() => router.visit(route('tasks.show', [task.project_id, task.id]))} className="border-b last:border-0 cursor-pointer hover:bg-primary/10">
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

                            {/* BUG FIX (permintaan Boss 2026-08-07): tombol ini SELALU ke
                            route('tasks.all') ("Semua Tugas") -- route itu digerbangi
                            can:project.viewAll (routes/admin.php:72). Widget Top-10 ini
                            TETAP tampil untuk viewer TERBATAS (restrictedToSelf, isinya
                            "data milik saya"), tapi viewer itu TIDAK PUNYA project.viewAll
                            -- klik tombol jadi 403 ("mati"). Sekarang diarahkan ke
                            route('tasks.my') ("Tugas Saya", nol permission khusus, auth
                            saja) utk viewer terbatas, konsisten dgn scope data yang
                            memang sudah ditampilkan widget ini ke mereka. */}
                            <div className="mt-4 flex justify-center">
                                <Button type="button" variant="outline" size="sm" asChild>
                                    <Link href={restrictedToSelf ? route('tasks.my') : route('tasks.all')}>Show more tugas →</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
