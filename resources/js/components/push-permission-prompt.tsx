/**
 * ==========================================================
 * MODUL       : push-permission-prompt.tsx
 * KLASIFIKASI : UI
 * TUJUAN      : F-184/F-185 — ajakan aktifkan notifikasi push (FCM), bentuk &
 *               interaksi DISAMAKAN NotificationBell/ReviewNotice (permintaan
 *               Boss: taruh dekat ikon bell/review di header) — ikon + klik buka
 *               popover kecil, BUKAN banner besar yang mengganggu.
 * DIPANGGIL   : app-sidebar-header.tsx
 * MEMANGGIL   : resources/js/hooks/use-fcm.ts (enable())
 * DATA MASUK  : Notification.permission (browser)
 * DATA KELUAR : localStorage key 'fcmPromptDismissed' (murni state tampilan
 *               klien, BUKAN entitas KPI/DB — F-4, pola SAMA reviewTasksSeenCount)
 * RISIKO      : Komponen TIDAK RENDER apa pun kalau browser tidak dukung FCM,
 *               izin SUDAH ditolak permanen ('denied' — browser blokir prompt
 *               ulang, tombol tidak bisa apa-apa lagi), atau user sudah pernah
 *               dismiss ('Nanti'/gagal enable). Permission 'granted' TIDAK lagi
 *               menyembunyikan tombol (permintaan Boss 2026-08-28) — device baru
 *               / token FCM expired butuh cara re-trigger enable() tanpa hapus
 *               localStorage manual; re-klik aman krn PushSubscriptionController
 *               ::store() updateOrCreate() by fcm_token (idempotent). enable()
 *               HANYA dipanggil dari klik tombol di sini (gestur user), TIDAK
 *               OTOMATIS saat mount (lihat use-fcm.ts kenapa).
 * ==========================================================
 */
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useFcm } from '@/hooks/use-fcm';
import { BellPlus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const DISMISS_KEY = 'fcmPromptDismissed';

export function PushPermissionPrompt() {
    const { enable, permission } = useFcm();
    const [dismissed, setDismissed] = useState(true);
    const [isOpen, setIsOpen] = useState(false);
    const [enabling, setEnabling] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    // GUARD: baca localStorage cuma di client (SSR-safe default true = anggap
    // sudah dismiss, sampai efek ini jalan dan buktikan sebaliknya).
    useEffect(() => {
        setDismissed(localStorage.getItem(DISMISS_KEY) === '1');
    }, []);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    function dismiss() {
        localStorage.setItem(DISMISS_KEY, '1');
        setDismissed(true);
        setIsOpen(false);
    }

    async function handleEnable() {
        setEnabling(true);

        // WORKAROUND: Firebase SDK (initializeApp/getToken) THROW kalau config
        // salah/kosong (mis. Boss belum pasang kredensial, FIREBASE_API_KEY dkk
        // masih string kosong) -- BUKAN cuma return false. Tanpa try/catch,
        // exception di sini jadi unhandled rejection dan setEnabling(false) DI
        // BAWAH tidak pernah jalan -> tombol macet permanen di "Memproses...".
        let success = false;
        try {
            success = await enable();
        } catch {
            success = false;
        }

        setEnabling(false);

        if (success) {
            setIsOpen(false);
        } else {
            // SUMBER: gagal (browser tidak dukung / user tolak prompt native /
            // config belum siap) -- dismiss juga, supaya tidak terus menawari
            // orang yang percobaannya sudah gagal.
            dismiss();
        }
    }

    // GUARD: sembunyi kalau browser TIDAK DUKUNG, izin DITOLAK permanen ('denied'
    // -- browser blokir prompt ulang, tombol tidak bisa apa-apa lagi), atau user
    // sudah pernah dismiss. 'granted' SENGAJA TIDAK disembunyikan (beda dari
    // sebelumnya) -- Boss minta tombol tetap tampil supaya bisa re-trigger
    // enable() (mis. ganti device/browser, token FCM expired) tanpa perlu hapus
    // localStorage manual dari devtools.
    if (permission === 'unsupported' || permission === 'denied' || dismissed) {
        return null;
    }

    return (
        <div ref={containerRef} className="relative">
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            onClick={() => setIsOpen((prev) => !prev)}
                            className="relative flex h-9 w-9 items-center justify-center rounded-md hover:bg-accent"
                            aria-label="Aktifkan notifikasi push"
                        >
                            <BellPlus className="h-5 w-5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Aktifkan notifikasi push</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>

            {isOpen && (
                <div className="absolute top-full right-0 z-50 mt-1 w-72 rounded-md border bg-popover p-3 shadow-md">
                    <p className="text-sm font-medium">Aktifkan notifikasi push?</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Dapat notifikasi langsung di device ini untuk tugas baru, review, dan perpanjangan deadline — walau tab ini tidak dibuka.
                    </p>
                    <div className="mt-3 flex gap-2">
                        <button
                            type="button"
                            onClick={handleEnable}
                            disabled={enabling}
                            className="rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60"
                        >
                            {enabling ? 'Memproses...' : 'Aktifkan'}
                        </button>
                        <button type="button" onClick={dismiss} className="rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-accent">
                            Nanti
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
