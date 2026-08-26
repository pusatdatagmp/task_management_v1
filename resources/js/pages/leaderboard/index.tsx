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
//               Permintaan Boss (ref. docs/task-fixx.html VIEWS.leaderboard,
//               stateLeaderboard.highlight): 3 kartu sorotan DINAMIS di atas tabel
//               -- filter "Sorotan" (Top 3 / Bottom 3, MURNI client-side, F-109)
//               menukar ISI 3 kartu yang SAMA antara 3 tertinggi vs 3 terendah.
//               Cuma SATU set 3 kartu tampil sekaligus (bukan 6) -- MURNI
//               slice/reverse dari rows[] yang SUDAH urut kpi_total desc (F-177,
//               permintaan Boss 2026-08-27, dulu Point desc), nol angka baru
//               dihitung di FE.
// DIPANGGIL   : LeaderboardController::index() (route 'leaderboard', can:leaderboard.view)
// MEMANGGIL   : todayRange/thisWeekRange/thisMonthRange (lib/leaderboard-period,
//               MURNI tanggal, F-109), useInitials (hooks, REUSE F-avatar sama UserInfo)
// DATA MASUK  : from, to (string 'Y-m-d'), rows[] (sudah urut kpi_total desc F-177,
//               tiap row bawa point F-168 kolom terpisah), kpi_enabled (toggle
//               org-level F-166 -- kolom KPI di tabel disembunyikan total kalau
//               false, "tinggal disable")
// DATA KELUAR : router.get (filter periode, tercermin URL — pola sama activity-logs/index.tsx)
// RISIKO      : SUMBER F-4/F-134 — halaman ini SKOR RANKING, BUKAN nominal uang.
//               JANGAN PERNAH tambah rupiah/gaji/reward di sini (itu v2.0). Skor
//               PROVISIONAL (F-2) — catatan di bawah tabel WAJIB tetap ada sampai
//               v1.5 kalibrasi data nyata, jangan dihapus supaya Boss/management
//               tidak salah anggap ini sudah final.
// ==========================================================

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { thisMonthRange, thisWeekRange, todayRange } from '@/lib/leaderboard-period';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface LeaderboardRow {
    id: number;
    name: string;
    point: number;
    rating: number | null;
    revisi: number;
    ditolak: number;
    on_time_percent: number | null;
    // F-168: KOLOM TERPISAH dari point (Σpts TETAP) -- indikator ketepatan-waktu,
    // BUKAN pengganti Point SEBAGAI NILAI. F-177: TAPI sekarang jadi BASIS
    // RANKING rows[] (urutan array dari backend), lihat LeaderboardService.
    kpi_total: number;
}

interface LeaderboardProps {
    from: string;
    to: string;
    rows: LeaderboardRow[];
    // F-166: master toggle org-level -- kolom KPI di tabel disembunyikan kalau false.
    kpi_enabled: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Leaderboard', href: '/leaderboard' }];

const MEDAL = ['🥇', '🥈', '🥉'];
const MEDAL_BORDER = ['border-t-yellow-400', 'border-t-slate-400', 'border-t-amber-700'];

// SUMBER (permintaan Boss, ref. docs/task-fixx.html .leader-card): kartu sorotan
// Top-3/Bottom-3 -- MURNI presentasi dari 1 baris `rows[]` yang SUDAH final dari
// backend (nol Point/Rating dihitung ulang di sini, F-109). `rank` cuma indeks
// tampilan (medali/label), BUKAN dipakai untuk urutan (rows[] SUDAH urut kpi_total
// desc, F-177, permintaan Boss 2026-08-27 -- dulu Point desc).
function LeaderCard({ row, rank, variant, kpiEnabled }: { row: LeaderboardRow; rank: number; variant: 'top' | 'bottom'; kpiEnabled: boolean }) {
    const getInitials = useInitials();
    const isTop = variant === 'top';

    return (
        <Card className={`border-t-4 text-center ${isTop ? MEDAL_BORDER[rank] : 'border-t-rose-500'}`}>
            <CardContent className="flex flex-col items-center gap-1 pt-6">
                <span className="text-3xl">{isTop ? MEDAL[rank] : '⚠️'}</span>
                <Avatar className="mt-1 h-16 w-16">
                    <AvatarFallback className="bg-neutral-200 text-lg dark:bg-neutral-700">{getInitials(row.name)}</AvatarFallback>
                </Avatar>
                <p className="mt-2 text-base font-bold">{row.name}</p>
                {/* F-62: label NETRAL -- ini rem Goodhart (F-4), bukan papan malu member.
                    "Terbawah N" (bukan "terburuk"/"gagal") -- pola sama task-fixx.html. */}
                <p className="text-muted-foreground text-xs">{isTop ? `Peringkat ${rank + 1}` : `Terbawah ${rank + 1}`}</p>
                {/* F-173 (permintaan Boss): kartu sorotan tampilkan NILAI KPI (kpi_total,
                    F-168), bukan Point -- angka besar di kartu ini. F-177 (permintaan Boss
                    2026-08-27): RANKING (urutan rows[]) SEKARANG JUGA dari kpi_total desc,
                    dulu dari Point (lihat KONTRAK LeaderboardService::forPeriod()) -- jadi
                    urutan+angka kartu SEKARANG konsisten satu sumber. Fallback ke Point
                    kalau kpi_enabled=false (F-166 -- org yang belum aktifkan KPI, kpi_total
                    akan 0 utk semua, tampilkan itu jelas salah) TETAP DIPERTAHANKAN untuk
                    ANGKA yang ditampilkan -- TAPI catatan: kalau kpi_enabled=false, URUTAN
                    baris (rows[] dari backend) tetap berbasis kpi_total yang all-zero itu
                    (bukan Point), jadi urutan tampil BISA tidak sinkron dengan angka Point
                    yang ditampilkan di kartu ini saat toggle KPI organisasi mati. Belum ada
                    fallback sort di backend untuk skenario ini -- lapor Boss kalau perlu. */}
                <p className={`mt-1 text-2xl font-bold ${isTop ? '' : 'text-rose-600'}`}>
                    {kpiEnabled ? (
                        <>
                            {row.kpi_total} <span className="text-muted-foreground text-sm font-normal">KPI</span>
                        </>
                    ) : (
                        <>
                            {row.point} <span className="text-muted-foreground text-sm font-normal">pts</span>
                        </>
                    )}
                </p>
                <div className="mt-3 grid w-full grid-cols-3 gap-2 border-t pt-3">
                    <div>
                        <p className="text-lg font-semibold">{row.rating !== null ? `⭐ ${row.rating.toFixed(1)}` : '-'}</p>
                        <p className="text-muted-foreground text-[11px]">Rating</p>
                    </div>
                    <div>
                        <p className="text-lg font-semibold">{row.revisi}</p>
                        <p className="text-muted-foreground text-[11px]">Revisi</p>
                    </div>
                    <div>
                        <p className="text-lg font-semibold">{row.ditolak}</p>
                        <p className="text-muted-foreground text-[11px]">Ditolak</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function LeaderboardIndex({ from, to, rows, kpi_enabled }: LeaderboardProps) {
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

    // Permintaan Boss (ref. task-fixx.html stateLeaderboard.highlight): filter
    // "Sorotan" MURNI client-side -- switch antara 3 Point tertinggi vs 3 Point
    // terendah, tanpa round-trip server (rows[] sudah punya SEMUA data periode
    // terpilih). Bottom-3 di-reverse supaya kartu pertama = Point PALING rendah
    // (pola SAMA template: "Terbawah 1" = user paling perlu dibantu).
    const [highlight, setHighlight] = useState<'top' | 'bottom'>('top');
    const cardRows = highlight === 'top' ? rows.slice(0, 3) : [...rows].reverse().slice(0, 3);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leaderboard" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
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

                    <label className="flex flex-col gap-1">
                        <span className="font-medium">Sorotan</span>
                        <Select value={highlight} onValueChange={(value) => setHighlight(value as 'top' | 'bottom')}>
                            <SelectTrigger className="h-8 text-sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="top">🏆 Top 3</SelectItem>
                                <SelectItem value="bottom">📉 Bottom 3</SelectItem>
                            </SelectContent>
                        </Select>
                    </label>
                </div>

                {/* Permintaan Boss (ref. docs/task-fixx.html): SATU set 3 kartu, isinya
                    dinamis ikut filter "Sorotan" di atas -- bukan 6 kartu sekaligus. */}
                {cardRows.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <p className="text-sm font-semibold">{highlight === 'top' ? '🏆 Top Performer' : '📉 Needs Improvement'}</p>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            {cardRows.map((row, index) => (
                                <LeaderCard key={row.id} row={row} rank={index} variant={highlight} kpiEnabled={kpi_enabled} />
                            ))}
                        </div>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Ranking {from} s/d {to}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="bg-muted/50 text-muted-foreground border-b">
                                    <th className="p-3">Rank</th>
                                    <th className="p-3">Nama</th>
                                    <th className="p-3">Point</th>
                                    {/* F-168: kolom TERPISAH dari Point, disembunyikan total kalau
                                        kpi_enabled=false (F-166 -- "tinggal disable"). */}
                                    {kpi_enabled && <th className="p-3">KPI</th>}
                                    <th className="p-3">Rating</th>
                                    <th className="p-3">Revisi</th>
                                    <th className="p-3">Ditolak</th>
                                    <th className="p-3">On-time%</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, index) => {
                                    const isTop3 = index < 3;
                                    // B2: Bottom-3 = 3 baris TERAKHIR (list sudah urut kpi_total desc
                                    // dari server, F-177 -- dulu Point desc) -- utk analisa manajemen
                                    // (blueprint §7.2), BUKAN papan malu member (halaman ini
                                    // management-only, F-134). Kalau daftar pendek (<6 baris), boleh
                                    // tumpang tindih dengan Top-3 -- itu wajar.
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
                                            {kpi_enabled && <td className="p-3">{row.kpi_total}</td>}
                                            <td className="p-3">{row.rating !== null ? row.rating.toFixed(2) : '-'}</td>
                                            <td className="p-3">{row.revisi}</td>
                                            <td className="p-3">{row.ditolak}</td>
                                            <td className="p-3">{row.on_time_percent !== null ? `${row.on_time_percent}%` : '-'}</td>
                                        </tr>
                                    );
                                })}

                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={kpi_enabled ? 8 : 7} className="text-muted-foreground p-8 text-center">
                                            Tidak ada user aktif untuk ditampilkan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {/* F-2/F-134: catatan provisional WAJIB tetap ada -- ini bukan skor final. */}
                <p className="text-muted-foreground text-xs">
                    Skor provisional — kalibrasi final v1.5. Peringkat saat ini berdasarkan nilai KPI (bukan Point, F-177). Point tetap dihitung
                    dari task yang sudah disetujui pada periode terpilih; kolom Rating/Revisi/Ditolak/On-time% adalah konteks tampilan dan tidak
                    memengaruhi peringkat.
                </p>
            </div>
        </AppLayout>
    );
}
