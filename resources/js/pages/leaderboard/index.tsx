// ==========================================================
// MODUL       : leaderboard/index
// KLASIFIKASI : UI
// TUJUAN      : Halaman Leaderboard MANAGEMENT-ONLY (F-134, blueprint §7.2) —
//               MERENDER rows[] apa adanya dari LeaderboardController::index()
//               (F-109 — Point/Rating/Revisi/Ditolak/On-time% SELALU dari
//               LeaderboardService backend, nol rumus dihitung ulang di sini).
//               Kolom konteks (Rating/Revisi/Ditolak/On-time%) ditampilkan
//               BERDAMPINGAN dengan Point, TIDAK PERNAH dijumlahkan/dicampur ke
//               Point (F-62 — konteks bukan hukuman tersembunyi terhadap ranking).
// DIPANGGIL   : LeaderboardController::index() (route 'leaderboard', can:leaderboard.view)
// MEMANGGIL   : todayRange/thisWeekRange/thisMonthRange (lib/leaderboard-period,
//               MURNI tanggal, F-109)
// DATA MASUK  : from, to (string 'Y-m-d'), rows[] (sudah urut Point desc)
// DATA KELUAR : router.get (filter periode, tercermin URL — pola sama activity-logs/index.tsx)
// RISIKO      : SUMBER F-4/F-134 — halaman ini SKOR RANKING, BUKAN nominal uang.
//               JANGAN PERNAH tambah rupiah/gaji/reward di sini (itu v2.0). Skor
//               PROVISIONAL (F-2) — catatan di bawah tabel WAJIB tetap ada sampai
//               v1.5 kalibrasi data nyata, jangan dihapus supaya Boss/management
//               tidak salah anggap ini sudah final.
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { thisMonthRange, thisWeekRange, todayRange } from '@/lib/leaderboard-period';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

interface LeaderboardRow {
    id: number;
    name: string;
    point: number;
    rating: number | null;
    revisi: number;
    ditolak: number;
    on_time_percent: number | null;
}

interface LeaderboardProps {
    from: string;
    to: string;
    rows: LeaderboardRow[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Leaderboard', href: '/leaderboard' }];

const MEDAL = ['🥇', '🥈', '🥉'];

export default function LeaderboardIndex({ from, to, rows }: LeaderboardProps) {
    const applyRange = (range: { from: string; to: string }) => {
        router.get(route('leaderboard.index'), range, { preserveState: true, preserveScroll: true, replace: true });
    };

    // B3: preset MURNI kalkulasi tanggal (lib/leaderboard-period) -- angka Point/
    // Rating/dll TIDAK pernah dihitung di sini, cuma rentang from/to yang dikirim
    // ulang ke server (F-109).
    const applyPreset = (preset: 'today' | 'week' | 'month') => {
        const now = new Date();
        const range = preset === 'today' ? todayRange(now) : preset === 'week' ? thisWeekRange(now) : thisMonthRange(now);
        applyRange(range);
    };

    const bottomStartIndex = Math.max(rows.length - 3, 3);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leaderboard" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Leaderboard</h1>
                </div>

                <div className="flex flex-wrap items-end gap-4 rounded-lg border p-4 text-sm">
                    <label className="flex flex-col gap-1">
                        <span className="font-medium">Dari</span>
                        <Input type="date" value={from} onChange={(e) => applyRange({ from: e.target.value, to })} className="h-8 w-40" />
                    </label>
                    <label className="flex flex-col gap-1">
                        <span className="font-medium">Sampai</span>
                        <Input type="date" value={to} onChange={(e) => applyRange({ from, to: e.target.value })} className="h-8 w-40" />
                    </label>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => applyPreset('today')}>
                            Hari ini
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={() => applyPreset('week')}>
                            Minggu ini
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={() => applyPreset('month')}>
                            Bulan ini
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Ranking {from} s/d {to}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-muted-foreground">
                                    <th className="p-3">Rank</th>
                                    <th className="p-3">Nama</th>
                                    <th className="p-3">Point</th>
                                    <th className="p-3">Rating</th>
                                    <th className="p-3">Revisi</th>
                                    <th className="p-3">Ditolak</th>
                                    <th className="p-3">On-time%</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, index) => {
                                    const isTop3 = index < 3;
                                    // B2: Bottom-3 = 3 baris TERAKHIR (list sudah urut Point desc dari
                                    // server) -- utk analisa manajemen (blueprint §7.2), BUKAN papan
                                    // malu member (halaman ini management-only, F-134). Kalau daftar
                                    // pendek (<6 baris), boleh tumpang tindih dengan Top-3 -- itu wajar.
                                    const isBottom3 = index >= bottomStartIndex;

                                    return (
                                        <tr key={row.id} className="border-b last:border-0">
                                            <td className="p-3 font-medium">{isTop3 ? MEDAL[index] : `#${index + 1}`}</td>
                                            <td className="p-3">
                                                <div className="flex items-center gap-2">
                                                    {row.name}
                                                    {isBottom3 && (
                                                        <Badge variant="outline" className="text-xs">
                                                            Perlu dibantu
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="p-3 font-semibold">{row.point}</td>
                                            <td className="p-3">{row.rating !== null ? row.rating.toFixed(2) : '-'}</td>
                                            <td className="p-3">{row.revisi}</td>
                                            <td className="p-3">{row.ditolak}</td>
                                            <td className="p-3">{row.on_time_percent !== null ? `${row.on_time_percent}%` : '-'}</td>
                                        </tr>
                                    );
                                })}

                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="p-8 text-center text-muted-foreground">
                                            Tidak ada user aktif untuk ditampilkan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {/* F-2/F-134: catatan provisional WAJIB tetap ada -- ini bukan skor final. */}
                <p className="text-xs text-muted-foreground">
                    Skor provisional — kalibrasi final v1.5. Point dihitung dari task yang sudah disetujui pada periode terpilih; kolom Rating/
                    Revisi/Ditolak/On-time% adalah konteks tampilan dan tidak memengaruhi urutan Point.
                </p>
            </div>
        </AppLayout>
    );
}
