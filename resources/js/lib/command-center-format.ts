// ==========================================================
// MODUL       : command-center-format
// KLASIFIKASI : UTIL
// TUJUAN      : Fungsi format MURNI tampilan (F-109) untuk halaman Command Center —
//               label satuan menit untuk kartu "Beban Harian" (permintaan Boss
//               2026-08-15: satuan tunggal menit, jam DICABUT) dan navigasi
//               bulan prev/next heatmap kalender (F-131). TIDAK menghitung
//               beban/kapasitas/anomali apa pun — angka mentahnya SELALU dari
//               commandCenterPayload() backend, di sini cuma diubah representasinya.
// DIPANGGIL   : pages/command-center.tsx
// MEMANGGIL   : -
// DATA MASUK  : angka menit (number) dari summary_cards.beban_harian, string
//               'Y-m' dari heatmap.month
// DATA KELUAR : label string ("360/480 menit") atau string 'Y-m' bulan lain
// RISIKO      : shiftMonth() SALAH -> tombol prev/next heatmap lompat ke bulan
//               keliru (mis. Desember -> "2026-13" bukan "2027-01"). Dites
//               eksplisit di command-center-format.test.ts.
// ==========================================================

/** SUMBER: kartu "Beban Harian" — permintaan Boss 2026-08-15: satuan MENIT
 *  saja (bukan "X/Y jam" lagi), angka mentah dari backend tidak dibulatkan/
 *  dikonversi sama sekali di sini. */
export function formatMenitPair(usedMinutes: number, capacityMinutes: number): string {
    return `${usedMinutes}/${capacityMinutes} menit`;
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
