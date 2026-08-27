// ==========================================================
// MODUL       : use-count-up
// KLASIFIKASI : STATE
// TUJUAN      : Animasi angka MURNI presentasi -- render mulai dari 0 lalu naik
//               ke nilai final tiap kali `target` berubah (permintaan Boss:
//               "animasi keluar angka dari terkecil" pada kartu ringkasan
//               Command Center). Nol logika bisnis, nol fetch -- cuma interpolasi
//               angka yang SUDAH final dari props via requestAnimationFrame.
// DIPANGGIL   : pages/command-center.tsx (6 kartu ringkasan)
// MEMANGGIL   : -
// DATA MASUK  : `target` (number, angka final yang SUDAH dihitung backend)
// DATA KELUAR : angka (number) yang berubah tiap frame selama animasi berjalan,
//               berhenti tepat di `target` saat animasi selesai
// RISIKO      : rAF TIDAK dibersihkan saat unmount/target berubah cepat ->
//               setState pada komponen yang sudah lepas. cleanup di useEffect
//               (cancelAnimationFrame) mencegah ini.
// ==========================================================

import { useEffect, useRef, useState } from 'react';

// Permintaan Boss (revisi): count-up dibuat lebih lambat lagi -- 700ms terasa
// terlalu cepat buat kelihatan "menghitung" di kartu ringkasan. 2200ms cukup
// lambat untuk keliatan mengalir tanpa bikin Boss nunggu lama tiap buka halaman.
const DEFAULT_DURATION_MS = 2200;

// SUMBER: easeOutCubic -- naik cepat di awal, melambat mendekati nilai akhir
// (kesan "menghitung", bukan animasi konstan/linear yang terasa kaku).
function easeOutCubic(t: number): number {
    return 1 - Math.pow(1 - t, 3);
}

/**
 * KONTRAK: setiap kali `target` berubah, animasi RESTART dari 0 (bukan dari
 * nilai display sebelumnya) -- sesuai permintaan Boss "dari terkecil" tiap
 * kartu di-render/data widget di-refresh (navigasi filter/bulan).
 */
export function useCountUp(target: number, duration: number = DEFAULT_DURATION_MS): number {
    const [display, setDisplay] = useState(0);
    const frameRef = useRef<number | null>(null);

    useEffect(() => {
        const startedAt = performance.now();

        const tick = (now: number) => {
            const elapsed = now - startedAt;
            const progress = Math.min(1, elapsed / duration);

            setDisplay(Math.round(easeOutCubic(progress) * target));

            if (progress < 1) {
                frameRef.current = requestAnimationFrame(tick);
            }
        };

        frameRef.current = requestAnimationFrame(tick);

        return () => {
            if (frameRef.current !== null) {
                cancelAnimationFrame(frameRef.current);
            }
        };
    }, [target, duration]);

    return display;
}
