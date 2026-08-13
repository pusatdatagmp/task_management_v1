/**
 * ==========================================================
 * MODUL       : loading-overlay.tsx
 * KLASIFIKASI : UI
 * TUJUAN      : Overlay layar penuh + kartu spinner (bukan ikon polos)
 *               saat Inertia sedang memproses request (pindah halaman,
 *               submit form, filter tabel, dsb) -- feedback "sistem
 *               sedang bekerja", warna ikut token brand --primary
 *               (F-143/F-144, bisa di-override per-org).
 * DIPANGGIL   : resources/js/app.tsx (dibungkus di sekeliling <App/>,
 *               satu-satunya titik yang persist di semua halaman)
 * MEMANGGIL   : router event Inertia (@inertiajs/react)
 * DATA MASUK  : event router.on('start' | 'finish') -- semua Inertia
 *               visit (router.get/post/put/delete, useForm submit,
 *               <Link>) otomatis lewat sini, tidak perlu wiring manual
 *               per halaman.
 * DATA KELUAR : tidak ada -- murni efek visual, tidak ubah state app.
 * RISIKO      : threshold delay yang salah bikin overlay flicker
 *               (nyala-mati cepat) di navigasi yang sudah cepat.
 * ==========================================================
 */
import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

// Ambang tunda sebelum overlay ditampilkan. Kalau request selesai
// lebih cepat dari ini, overlay tidak pernah muncul -- mencegah
// kedip-kedip untuk navigasi yang memang sudah instan.
const SHOW_DELAY_MS = 250;

export function LoadingOverlay() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        let showTimer: ReturnType<typeof setTimeout> | undefined;

        const removeStart = router.on('start', () => {
            showTimer = setTimeout(() => setVisible(true), SHOW_DELAY_MS);
        });

        const removeFinish = router.on('finish', () => {
            clearTimeout(showTimer);
            setVisible(false);
        });

        return () => {
            clearTimeout(showTimer);
            removeStart();
            removeFinish();
        };
    }, []);

    if (!visible) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center bg-background/60 backdrop-blur-sm animate-in fade-in-0 duration-200"
            role="status"
            aria-live="polite"
            aria-label="Memuat"
        >
            <div className="flex flex-col items-center gap-3 rounded-2xl border bg-card/90 px-8 py-6 shadow-2xl animate-in zoom-in-95 duration-200">
                <div className="relative flex h-12 w-12 items-center justify-center">
                    {/* Ring statis sebagai jejak, ring primary yang berputar di atasnya --
                        kesan "lingkaran progress" alih-alih ikon spinner polos. */}
                    <div className="absolute inset-0 rounded-full border-4 border-primary/15" />
                    <Loader2 className="h-12 w-12 animate-spin text-primary" strokeWidth={3} />
                </div>
                <span className="text-sm font-medium text-muted-foreground">Memuat...</span>
            </div>
        </div>
    );
}
