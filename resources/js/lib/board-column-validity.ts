// ==========================================================
// MODUL       : board-column-validity
// KLASIFIKASI : UTIL
// TUJUAN      : Aturan C (F-110) — dari posisi kolom ASAL P, kolom mana yang SAH
//               menerima drop: P (batal), P+1 (maju satu, F-45), dan semua < P
//               (mundur bebas). Posisi > P+1 TAK-SAH. Fungsi PURE, dipakai board.tsx
//               untuk redup kolom SEBELUM user melepas drag (bukan tolak-sesudah).
// DIPANGGIL   : pages/tasks/board.tsx
// MEMANGGIL   : -
// DATA MASUK  : position kolom asal + array position semua kolom
// DATA KELUAR : Set posisi yang SAH menerima drop
// RISIKO      : Ini HINT UI SAJA (F-110) — validasi ASLI tetap di
//               TaskTransitionService::changeStatus() (server, C1). Salah di sini
//               paling buruk kolom disable/enable keliru secara visual, BUKAN
//               lubang keamanan — server tetap menolak transisi tak sah.
// ==========================================================

/**
 * KONTRAK: true kalau kolom posisi $targetPosition boleh menerima drop dari
 * kolom posisi $sourcePosition (F-45: maju cuma +1, mundur bebas, diam di
 * tempat juga "sah" karena itu cuma batal drag, bukan pelanggaran apa pun).
 */
export function isValidDropTarget(sourcePosition: number, targetPosition: number): boolean {
    if (targetPosition <= sourcePosition) {
        return true;
    }

    return targetPosition === sourcePosition + 1;
}
