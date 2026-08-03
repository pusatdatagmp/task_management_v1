// ==========================================================
// MODUL       : activity-logs/index
// KLASIFIKASI : UI
// TUJUAN      : Log aktivitas GLOBAL lintas project (v1.0 H4, F-116) — READ-ONLY
//               MUTLAK, tidak ada tombol edit/hapus sama sekali di halaman ini.
//               Permission activity.view (admin default), BUKAN untuk member biasa.
// DIPANGGIL   : ActivityLogController::index()
// MEMANGGIL   : -
// DATA MASUK  : logs (paginator, message SUDAH label manusiawi dari
//               ActivityLogPresenter — F-106, tidak ada terjemahan di sini),
//               users[]/eventTypes[] (opsi filter), filters aktif, summary
//               (4 kartu ringkas, permintaan Boss — REUSE angka yang SAMA
//               dengan filter aktif, lihat ActivityLogController::buildSummary())
// DATA KELUAR : router.get (filter server-side, URL tercermin, pola sama tasks/index.tsx)
// RISIKO      : JANGAN PERNAH tambah tombol/aksi mutasi di halaman ini (F-23/F-39
//               semangat read-only) — kalau ada kebutuhan "hapus log", itu keputusan
//               kebijakan besar yang harus naik ke Boss dulu, bukan ditambah diam-diam.
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, CalendarClock, Flame, UserRound } from 'lucide-react';

interface UserOption {
    id: number;
    name: string;
}

interface EventOption {
    value: string;
    label: string;
}

interface LogRow {
    id: number;
    actor: string;
    event: string;
    event_label: string;
    message: string;
    created_at: string;
}

interface PaginatedLogs {
    data: LogRow[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Filters {
    user_id: number | null;
    event: string | null;
    from: string | null;
    to: string | null;
}

// SUMBER: ActivityLogController::buildSummary() -- 4 angka REUSE closure filter
// yang SAMA dipakai listing (bukan agregat organisasi penuh), jadi kartu ini
// selalu cerminan hasil yang SEDANG ditampilkan di tabel bawah.
interface Summary {
    total: number;
    today: number;
    top_user: string | null;
    top_user_count: number;
    top_event: string | null;
    top_event_count: number;
}

interface ActivityLogIndexProps {
    logs: PaginatedLogs;
    summary: Summary;
    filters: Filters;
    users: UserOption[];
    eventTypes: EventOption[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Log Aktivitas', href: '/pengaturan/activity-log' }];

export default function ActivityLogIndex({ logs, summary, filters, users, eventTypes }: ActivityLogIndexProps) {
    const applyFilters = (overrides: Partial<Filters>) => {
        router.get(
            route('activity-logs.index'),
            { ...filters, ...overrides },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const hasActiveFilter = filters.user_id !== null || filters.event !== null || filters.from !== null || filters.to !== null;

    const resetFilters = () => {
        router.get(
            route('activity-logs.index'),
            { user_id: null, event: null, from: null, to: null },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Log Aktivitas" />

            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Log Aktivitas</h1>

                {/* Permintaan Boss: 4 kartu ringkas -- gaya SAMA kartu Command Center
                    (icon + angka besar), angka REUSE filter aktif (lihat interface Summary). */}
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Log</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">{summary.total}</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Log Hari Ini</CardTitle>
                            <CalendarClock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0 text-2xl font-semibold">{summary.today}</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">User Teraktif</CardTitle>
                            <UserRound className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <p className="truncate text-lg font-semibold">{summary.top_user ?? '-'}</p>
                            {summary.top_user && <p className="text-xs text-muted-foreground">{summary.top_user_count} kejadian</p>}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Event Terbanyak</CardTitle>
                            <Flame className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <p className="truncate text-lg font-semibold">{summary.top_event ?? '-'}</p>
                            {summary.top_event && <p className="text-xs text-muted-foreground">{summary.top_event_count} kejadian</p>}
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-wrap items-end gap-4 rounded-lg border p-4 text-sm">
                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Pelaku</span>
                        <select
                            className="h-8 rounded-md border border-input bg-background px-2"
                            value={filters.user_id ?? ''}
                            onChange={(e) => applyFilters({ user_id: e.target.value ? Number(e.target.value) : null })}
                        >
                            <option value="">Semua</option>
                            {users.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Tipe event</span>
                        <select
                            className="h-8 rounded-md border border-input bg-background px-2"
                            value={filters.event ?? ''}
                            onChange={(e) => applyFilters({ event: e.target.value || null })}
                        >
                            <option value="">Semua</option>
                            {eventTypes.map((e) => (
                                <option key={e.value} value={e.value}>
                                    {e.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Dari tanggal</span>
                        <input
                            type="date"
                            className="h-8 rounded-md border border-input bg-background px-2"
                            value={filters.from ?? ''}
                            onChange={(e) => applyFilters({ from: e.target.value || null })}
                        />
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Sampai tanggal</span>
                        <input
                            type="date"
                            className="h-8 rounded-md border border-input bg-background px-2"
                            value={filters.to ?? ''}
                            onChange={(e) => applyFilters({ to: e.target.value || null })}
                        />
                    </div>

                    {hasActiveFilter && (
                        <Button type="button" variant="ghost" size="sm" onClick={resetFilters}>
                            Reset filter
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{logs.total} kejadian</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {logs.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Tidak ada kejadian yang cocok dengan filter ini.</p>
                        ) : (
                            logs.data.map((log) => (
                                <div key={log.id} className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm">
                                    <div className="flex flex-col gap-1">
                                        <span>{log.message}</span>
                                        <Badge variant="outline" className="w-fit text-[10px]">
                                            {log.event_label}
                                        </Badge>
                                    </div>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {new Date(log.created_at).toLocaleString('id-ID')}
                                    </span>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {logs.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {logs.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded-md border px-3 py-1 text-sm ${
                                    link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'
                                } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
