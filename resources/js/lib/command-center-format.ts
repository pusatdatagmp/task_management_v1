// ==========================================================
// MODUL       : command-center-format
// KLASIFIKASI : UTIL
// TUJUAN      : Fungsi format MURNI tampilan (F-109) untuk halaman Command Center —
//               konversi menit->jam untuk kartu "Beban Harian" dan navigasi
//               bulan prev/next heatmap kalender (F-131). TIDAK menghitung
//               beban/kapasitas/anomali apa pun — angka mentahnya SELALU dari
//               commandCenterPayload() backend, di sini cuma diubah representasinya.
// DIPANGGIL   : pages/command-center.tsx
// MEMANGGIL   : -
// DATA MASUK  : angka menit (number) dari summary_cards.beban_harian, string
//               'Y-m' dari heatmap.month
// DATA KELUAR : label string ("6/8 jam") atau string 'Y-m' bulan lain
// RISIKO      : shiftMonth() SALAH -> tombol prev/next heatmap lompat ke bulan
//               keliru (mis. Desember -> "2026-13" bukan "2027-01"). Dites
//               eksplisit di command-center-format.test.ts.
// ==========================================================

/** SUMBER: 90 menit -> "1.5", 480 menit -> "8" (integer tanpa .0 kosong). */
function hoursLabel(minutes: number): string {
    const rounded = Math.round((minutes / 60) * 10) / 10;

    return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1);
}

/** SUMBER: kartu "Beban Harian" blueprint §7.1 — format WAJIB "X/Y jam". */
export function formatJamPair(usedMinutes: number, capacityMinutes: number): string {
    return `${hoursLabel(usedMinutes)}/${hoursLabel(capacityMinutes)} jam`;
}

/**
 * KONTRAK: geser `monthKey` ('Y-m', dari heatmap.month) sejumlah `delta` bulan
 * (positif = maju/next, negatif = mundur/prev). Rollover tahun (Des->Jan,
 * Jan->Des) ditangani Date UTC bawaan JS, bukan modulo manual (rawan off-by-one).
 */
export function shiftMonth(monthKey: string, delta: number): string {
    const [yearStr, monthStr] = monthKey.split('-');
    const shifted = new Date(Date.UTC(Number(yearStr), Number(monthStr) - 1 + delta, 1));

    return `${shifted.getUTCFullYear()}-${String(shifted.getUTCMonth() + 1).padStart(2, '0')}`;
}
