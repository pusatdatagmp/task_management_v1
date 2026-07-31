// ==========================================================
// MODUL       : lib/priority-quadrant
// KLASIFIKASI : UTIL
// TUJUAN      : Konstanta tampilan Eisenhower quadrant (F-122/F-126) — label +
//               warna dipakai BERSAMA oleh form task (create/edit), badge (show/
//               index/board). Satu sumber supaya warna/label tidak drift antar view.
// DIPANGGIL   : tasks/create.tsx, tasks/edit.tsx, tasks/show.tsx, tasks/index.tsx,
//               tasks/board.tsx
// MEMANGGIL   : -
// DATA MASUK  : -
// DATA KELUAR : PRIORITY_QUADRANT_OPTIONS (untuk <select>), PRIORITY_QUADRANT_COLOR/
//               LABEL (untuk badge)
// RISIKO      : command-center.tsx (v1.2 H3) mendefinisikan warna Eisenhower SENDIRI
//               secara terpisah (di luar scope prompt ini untuk disatukan) — kalau
//               warna di sana diubah, badge di halaman task ini TIDAK ikut berubah
//               otomatis. Dicatat sebagai deviasi kecil di laporan H5.
// ==========================================================

export type PriorityQuadrant = 'p1' | 'p2' | 'p3' | 'p4';

export const PRIORITY_QUADRANT_OPTIONS: { value: PriorityQuadrant; label: string }[] = [
    { value: 'p1', label: '#1 Penting – Mendesak' },
    { value: 'p2', label: '#2 Penting – Tdk Mendesak' },
    { value: 'p3', label: '#3 Tdk Penting – Mendesak' },
    { value: 'p4', label: '#4 Tdk Penting – Tdk Mendesak' },
];

// SUMBER: BLUEPRINT-UIUX-v1.7.md §5 — p1 merah, p2 amber, p3 biru, p4 abu.
export const PRIORITY_QUADRANT_COLOR: Record<PriorityQuadrant, string> = {
    p1: '#dc2626',
    p2: '#f59e0b',
    p3: '#2563eb',
    p4: '#64748b',
};

export const PRIORITY_QUADRANT_LABEL: Record<PriorityQuadrant, string> = {
    p1: 'P1',
    p2: 'P2',
    p3: 'P3',
    p4: 'P4',
};
