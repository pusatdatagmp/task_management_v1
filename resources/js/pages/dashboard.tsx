// ==========================================================
// MODUL       : dashboard
// KLASIFIKASI : UI
// TUJUAN      : Dashboard tim admin (02-DATA-MODEL §5, F-52) — render TIGA angka
//               per user (Aktif, Beban/idle plan, Backlog) + Idle Real sekunder +
//               anomali (F-53). Nol rumus dihitung ulang di sini — semua angka
//               datang APA ADANYA dari DashboardController::index() (yang cuma
//               meneruskan DashboardService, H2). Kalau angka tampak salah, itu
//               bug di service/controller, BUKAN sesuatu yang ditambal di sini.
// DIPANGGIL   : DashboardController::index() (route 'dashboard', can:dashboard.view)
// MEMANGGIL   : formatLiveMinutes (reuse dari hooks/use-live-counter, F-94),
//               classifyWorkload (lib/dashboard-status)
// DATA MASUK  : date, selectedUserId, users[] (roster untuk filter), rows[]
//               (metrik per user: kapasitas/aktif/beban/backlog/idle_plan/idle_real/anomalies)
// DATA KELUAR : router.get (filter tanggal/user, query string ikut URL — A6)
// RISIKO      : SUMBER F-4/A7 — dashboard v0.8 adalah CERMIN waktu & beban, BUKAN
//               penilaian. JANGAN PERNAH tambah rupiah/skor/reward/punishment di
//               halaman ini (itu v1.5/v2.0). F-53 — anomali WAJIB label netral
//               ("perlu ditinjau"), bukan "melanggar"/"pelanggaran".
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { formatLiveMinutes } from '@/hooks/use-live-counter';
import { classifyWorkload } from '@/lib/dashboard-status';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

interface AnomalyRow {
    task_id: number;
    title: string;
    estimated_minutes: number;
    actual_minutes: number;
}

interface DashboardRow {
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

interface UserOption {
    id: number;
    name: string;
}

interface DashboardProps {
    date: string;
    selectedUserId: number | null;
    users: UserOption[];
    rows: DashboardRow[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

// SUMBER: warna badge status beban (A3) -- MURNI visual (lib/dashboard-status),
// bukan rumus KPI. Netral secara sengaja (bukan merah "salah") untuk idle-tinggi,
// karena idle tinggi bukan kesalahan siapa pun.
const STATUS_BADGE: Record<string, { label: string; className: string }> = {
    overload: { label: 'Overload', className: 'border-transparent bg-red-600 text-white hover:bg-red-600' },
    'idle-tinggi': { label: 'Idle tinggi', className: 'border-transparent bg-amber-500 text-white hover:bg-amber-500' },
    normal: { label: '', className: '' },
};

export default function Dashboard({ date, selectedUserId, users, rows }: DashboardProps) {
    // SUMBER: kirim OBJEK BARU tiap kali (bukan spread query string saat ini) --
    // pola sama tasks/index.tsx (Hari-5 §C6), supaya filter selalu jelas apa
    // adanya dan tercermin utuh di URL (A6 — bisa di-bookmark/refresh).
    const applyFilters = (overrides: { date?: string; user_id?: number | null }) => {
        const next = { date, user_id: selectedUserId, ...overrides };

        router.get(
            route('dashboard'),
            { date: next.date, ...(next.user_id ? { user_id: next.user_id } : {}) },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Dashboard Tim</h1>
                </div>

                <div className="flex flex-wrap items-end gap-4 rounded-lg border p-4 text-sm">
                    <label className="flex flex-col gap-1">
                        <span className="font-medium">Tanggal</span>
                        <input
                            type="date"
                            value={date}
                            onChange={(e) => applyFilters({ date: e.target.value })}
                            className="h-8 rounded-md border border-input bg-background px-2"
                        />
                    </label>

                    <label className="flex flex-col gap-1">
                        <span className="font-medium">User</span>
                        <select
                            value={selectedUserId ?? ''}
                            onChange={(e) => applyFilters({ user_id: e.target.value ? Number(e.target.value) : null })}
                            className="h-8 rounded-md border border-input bg-background px-2"
                        >
                            <option value="">Semua user</option>
                            {users.map((u) => (
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
                                <th className="p-3">Nama</th>
                                <th className="p-3">Aktif</th>
                                <th className="p-3">
                                    <span className="inline-flex items-center gap-1">
                                        Beban hari ini (idle plan)
                                        {/* F-96/F-118: penjelasan kenapa task multi-assignee "kelihatan"
                                            menyumbang lebih sedikit dari total menit-nya, DAN kenapa task
                                            besar tidak muncul penuh di hari ini -- cegah admin lapor bug
                                            yang bukan bug (v1.0.1, sama semangat F-96 Fase A4). */}
                                        <span
                                            className="cursor-help text-muted-foreground"
                                            title="Beban dibagi rata antar assignee (F-96) — task 4 jam dengan 2 assignee menyumbang 2 jam ke masing-masing, bukan 4 jam penuh. Lalu porsi itu DISEBAR ke tiap hari kerja sampai tenggat (F-118) — task 40 jam dengan tenggat 5 hari kerja lagi cuma menyumbang 8 jam ke hari ini, sisanya tampil di Backlog."
                                        >
                                            ⓘ
                                        </span>
                                    </span>
                                </th>
                                <th className="p-3">Backlog</th>
                                <th className="p-3">Anomali</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const status = classifyWorkload(row.beban, row.kapasitas);
                                const badge = STATUS_BADGE[status];

                                return (
                                    <tr key={row.id} className="border-b last:border-0 align-top">
                                        <td className="p-3 font-medium">{row.name}</td>
                                        <td className="p-3">
                                            <div>{formatLiveMinutes(row.aktif)}</div>
                                            {/* A3: IDLE_REAL sekunder -- efisiensi (KPI), BUKAN dipakai
                                                admin untuk keputusan assign (itu IDLE_PLAN di kolom beban). */}
                                            <div className="text-xs text-muted-foreground" title="Idle real = kapasitas dikurangi realisasi aktual (efisiensi, bukan untuk keputusan assign)">
                                                idle real: {formatLiveMinutes(row.idle_real)}
                                            </div>
                                        </td>
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
                                                <details>
                                                    {/* F-53: label NETRAL -- ini rem Goodhart (F-4), BUKAN vonis. */}
                                                    <summary className="cursor-pointer">
                                                        <Badge className="border-transparent bg-slate-500 text-white hover:bg-slate-500">
                                                            {row.anomalies.length} perlu ditinjau
                                                        </Badge>
                                                    </summary>
                                                    <ul className="mt-2 flex flex-col gap-1 text-xs text-muted-foreground">
                                                        {row.anomalies.map((a) => (
                                                            <li key={a.task_id}>
                                                                {a.title} — estimasi {formatLiveMinutes(a.estimated_minutes)}, realisasi{' '}
                                                                {formatLiveMinutes(a.actual_minutes)}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </details>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}

                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="p-8 text-center text-muted-foreground">
                                        Tidak ada user aktif untuk ditampilkan.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
