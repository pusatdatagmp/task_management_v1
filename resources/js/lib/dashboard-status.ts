// ==========================================================
// MODUL       : dashboard-status
// KLASIFIKASI : UTIL
// TUJUAN      : Klasifikasi visual baris dashboard (A3) — overload / idle-tinggi /
//               normal. Fungsi PURE (gampang diuji, B2), TIDAK mengubah/menghitung
//               ulang angka dari DashboardService — cuma membaca beban & kapasitas
//               yang sudah dihitung backend untuk memutuskan warna badge.
// DIPANGGIL   : pages/dashboard.tsx
// MEMANGGIL   : -
// DATA MASUK  : beban & kapasitas (menit) dari baris DashboardService::forUsers()
// DATA KELUAR : label status untuk badge (bukan angka baru)
// RISIKO      : Ambang IDLE_TINGGI_THRESHOLD murni pilihan VISUAL (bukan aturan
//               bisnis F-N) — gampang diubah Boss kapan saja tanpa menyentuh rumus
//               dashboard (F-52, tetap di DashboardService, JANGAN disamakan).
// ==========================================================

export type WorkloadStatus = 'overload' | 'idle-tinggi' | 'normal';

// MAGIC NUMBER: 50% — kapasitas nganggur (idle_plan) di atas separuh dianggap
// "idle tinggi" untuk ditandai admin (A3). Bukan hasil keputusan Boss, ambang
// awal yang wajar untuk tim ~10 orang — ubah di sini saja kalau Boss mau nilai lain.
const IDLE_TINGGI_THRESHOLD = 0.5;

/**
 * KONTRAK: klasifikasi murni dari dua angka yang SUDAH dihitung backend
 * (DashboardService::beban()/kapasitas()) — tidak pernah menghitung idle/beban
 * sendiri (itu tanggung jawab service, F-52).
 *
 * - kapasitas <= 0 (belum ada work_schedule aktif) -> 'normal' (tidak bisa dinilai,
 *   BUKAN idle-tinggi -- 0/0 secara matematis bukan "banyak nganggur").
 * - beban > kapasitas -> 'overload'.
 * - idle_plan (kapasitas - beban) >= 50% kapasitas -> 'idle-tinggi'.
 */
export function classifyWorkload(bebanMinutes: number, kapasitasMinutes: number): WorkloadStatus {
    if (kapasitasMinutes <= 0) {
        return 'normal';
    }

    if (bebanMinutes > kapasitasMinutes) {
        return 'overload';
    }

    const idlePlanMinutes = kapasitasMinutes - bebanMinutes;

    if (idlePlanMinutes / kapasitasMinutes >= IDLE_TINGGI_THRESHOLD) {
        return 'idle-tinggi';
    }

    return 'normal';
}
