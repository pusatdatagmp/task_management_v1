// ==========================================================
// MODUL       : leaderboard-period
// KLASIFIKASI : UTIL
// TUJUAN      : Preset rentang tanggal (hari/minggu/bulan) untuk filter halaman
//               Leaderboard (blueprint §7.2) — MURNI perhitungan tanggal kalender
//               (awal/akhir hari/minggu/bulan), BUKAN rumus skor apa pun (F-109 —
//               Point/Rating/Revisi/Ditolak/On-time% SELALU dari LeaderboardService
//               backend, di sini cuma menyiapkan `from`/`to` yang dikirim ke sana).
// DIPANGGIL   : pages/leaderboard/index.tsx
// MEMANGGIL   : -
// DATA MASUK  : Date "sekarang" (parameter, gampang diuji — B2)
// DATA KELUAR : { from: 'Y-m-d', to: 'Y-m-d' }
// RISIKO      : Minggu SALAH mulai (mis. Minggu bukan Senin) -> filter "minggu ini"
//               menampilkan rentang yang tidak sesuai ekspektasi Boss (WIB, F-69).
// ==========================================================

export interface PeriodRange {
    from: string;
    to: string;
}

function toDateString(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/** SUMBER: preset "Hari ini" -- from = to = tanggal `now`. */
export function todayRange(now: Date): PeriodRange {
    const s = toDateString(now);

    return { from: s, to: s };
}

/** SUMBER: preset "Minggu ini" -- Senin s/d Minggu (pola kalender Indonesia). */
export function thisWeekRange(now: Date): PeriodRange {
    const dayIndex = (now.getDay() + 6) % 7; // Senin=0..Minggu=6
    const monday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - dayIndex);
    const sunday = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6);

    return { from: toDateString(monday), to: toDateString(sunday) };
}

/** SUMBER: preset "Bulan ini" -- tanggal 1 s/d hari terakhir bulan berjalan. */
export function thisMonthRange(now: Date): PeriodRange {
    const first = new Date(now.getFullYear(), now.getMonth(), 1);
    const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    return { from: toDateString(first), to: toDateString(last) };
}
