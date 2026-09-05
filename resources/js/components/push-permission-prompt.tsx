/**
 * ==========================================================
 * MODUL       : push-permission-prompt.tsx
 * KLASIFIKASI : UI
 * TUJUAN      : F-184/F-185 + toggle nonaktifkan (permintaan Boss 2026-09-05) --
 *               icon notifikasi push di header SEKARANG toggle ON/OFF langsung
 *               (klik = ganti status, TANPA popover) -- gantikan alur lama
 *               "Aktifkan"/"Nanti" satu-arah yang tidak punya jalan matikan.
 * DIPANGGIL   : app-sidebar-header.tsx
 * MEMANGGIL   : resources/js/hooks/use-fcm.ts (enable()/disable())
 * DATA MASUK  : Notification.permission (browser), localStorage 'fcmDisabledByUser'
 * DATA KELUAR : localStorage 'fcmDisabledByUser' (state ON/OFF sisi klien,
 *               BUKAN entitas KPI/DB -- F-4, pola sama fcmPromptDismissed lama
 *               yang digantikan flag ini)
 * RISIKO      : Browser TIDAK BISA dicabut izinnya lewat JS setelah granted --
 *               "OFF" di sini artinya token dihapus dari push_tokens (server
 *               berhenti kirim), BUKAN mencabut Notification.permission. Kalau
 *               user granted lalu OFF via toggle ini, `permission` TETAP
 *               'granted' selamanya -- SATU-SATUNYA penanda OFF adalah flag
 *               localStorage ini. ATURAN DEFAULT: ON kalau permission granted
 *               DAN flag belum pernah di-set -- migrasi halus untuk user lama
 *               yang sudah subscribe lewat alur "Aktifkan" versi sebelumnya,
 *               supaya mereka TIDAK dianggap OFF diam-diam cuma karena baru
 *               lihat UI toggle ini. 'denied' (blokir permanen browser) --
 *               toggle tampil TERKUNCI (nol aksi saat diklik), user WAJIB ubah
 *               izin di pengaturan browser sendiri, JS tidak bisa membantu.
 * ==========================================================
 */
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useFcm } from '@/hooks/use-fcm';
import { Bell, BellOff } from 'lucide-react';
import { useEffect, useState } from 'react';

const DISABLED_KEY = 'fcmDisabledByUser';

export function PushPermissionPrompt() {
    const { enable, disable, permission } = useFcm();
    const [disabledByUser, setDisabledByUser] = useState(false);
    const [busy, setBusy] = useState(false);

    // GUARD: baca localStorage cuma di client (SSR-safe default false = anggap
    // ON, dikoreksi efek ini begitu jalan kalau ternyata user pernah matikan).
    useEffect(() => {
        setDisabledByUser(localStorage.getItem(DISABLED_KEY) === '1');
    }, []);

    // GUARD: browser tidak dukung Notification API sama sekali -- nol yang
    // bisa ditoggle, sembunyikan total (SATU-SATUNYA kondisi sembunyi sekarang,
    // permintaan Boss: selain ini toggle SELALU tampil termasuk saat OFF/denied).
    if (permission === 'unsupported') {
        return null;
    }

    const locked = permission === 'denied';
    const isOn = !locked && permission === 'granted' && !disabledByUser;

    async function handleToggle() {
        if (locked || busy) return;

        setBusy(true);
        try {
            if (isOn) {
                await disable();
                localStorage.setItem(DISABLED_KEY, '1');
                setDisabledByUser(true);
            } else {
                // WORKAROUND: Firebase SDK (initializeApp/getToken) THROW kalau
                // config salah/kosong -- pola sama handleEnable() versi lama,
                // ditangkap try/catch di luar supaya busy tidak macet permanen.
                const success = await enable();
                if (success) {
                    localStorage.removeItem(DISABLED_KEY);
                    setDisabledByUser(false);
                }
                // Gagal (user tolak prompt native / config belum siap): diam
                // saja -- kalau ditolak, `permission` jadi 'denied' dan
                // re-render otomatis mengunci toggle (locked=true).
            }
        } catch {
            // lihat WORKAROUND di atas
        }
        setBusy(false);
    }

    const label = locked
        ? 'Notifikasi push diblokir di pengaturan browser'
        : isOn
          ? 'Notifikasi push aktif — klik untuk matikan'
          : 'Notifikasi push nonaktif — klik untuk aktifkan';

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        onClick={handleToggle}
                        disabled={busy || locked}
                        aria-label={label}
                        aria-pressed={isOn}
                        className="flex h-9 w-9 items-center justify-center rounded-md hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {isOn ? <Bell className="h-5 w-5" /> : <BellOff className="h-5 w-5 text-muted-foreground" />}
                    </button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{label}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
