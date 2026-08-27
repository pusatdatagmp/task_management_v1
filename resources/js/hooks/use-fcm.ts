// ==========================================================
// MODUL       : use-fcm
// KLASIFIKASI : STATE
// TUJUAN      : F-184/F-185 — hook FCM: minta izin browser + daftarkan token ke
//               backend (enable()), DAN dengar push FOREGROUND (tab sedang FOKUS
//               OS) buat (a) tampilkan notifikasi manual + (b) trigger
//               partial-reload badge yang basi (bell/review/perpanjangan/tugas
//               saya) TANPA refresh manual. F-38: MURNI event-driven (reload
//               cuma jalan pas push BENERAN masuk) — NOL setInterval/polling,
//               pola sama use-live-counter.ts.
//               SUMBER (ditemukan verifikasi bareng Boss): payload data-only
//               (FcmService::sendToTokens(), BUKAN withNotification()) — kalau
//               tab TIDAK fokus OS, Firebase otomatis rutekan ke service worker
//               (firebase-messaging-sw.blade.php) yang munculin popup SENDIRI;
//               onMessage() DI SINI cuma jalan kalau tab BENERAN fokus, jadi
//               WAJIB tampilkan notifikasi manual juga di sini (Firebase TIDAK
//               auto-nampilkan apa pun untuk payload data-only) — TANPA ini,
//               push saat tab fokus jadi diam-diam, keliatan seperti error
//               padahal cuma badge yang (sebelumnya) di-refresh senyap.
// DIPANGGIL   : resources/js/components/push-permission-prompt.tsx
// MEMANGGIL   : lib/firebase.ts (getFirebaseMessaging), route('push-subscriptions.store'),
//               @inertiajs/react router.reload()
// DATA MASUK  : Notification.permission (browser), token dari firebase/messaging getToken()
// DATA KELUAR : POST push-subscriptions (daftarkan token), partial reload props
//               unreadNotificationsCount/myTasksCount/reviewTasksCount/
//               pendingExtensionsCount (SATU sumber HandleInertiaRequests::share())
//               + prop halaman aktif kalau relevan (tugas-saya/perpanjangan)
// RISIKO      : enable() TIDAK PERNAH dipanggil otomatis di sini (nol auto-prompt
//               saat mount) — pemanggil (push-permission-prompt.tsx) yang
//               memutuskan KAPAN minta izin (WAJIB lewat gestur klik user,
//               browser makin agresif blokir prompt otomatis/tanpa interaksi).
// ==========================================================

import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { getFirebaseMessaging } from '@/lib/firebase';
import { getToken, onMessage } from 'firebase/messaging';

/**
 * WORKAROUND: fetch() bawaan tidak otomatis kirim CSRF seperti axios (dipakai
 * Inertia router internal) — pola SAMA notification-bell.tsx (getXsrfToken()),
 * disalin di sini karena cuma dipakai hook ini, belum ada util bersama untuk itu.
 */
function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * KONTRAK: 4 prop badge (SATU sumber HandleInertiaRequests::share()) SELALU
 * di-reload tiap push foreground masuk, TERLEPAS jenis notifikasinya —
 * unconditional lebih sederhana & tidak gampang basi dibanding pemetaan
 * per-`data.type` yang harus di-update tiap kali trigger baru ditambah
 * (biaya: satu request partial-reload kecil, 4 integer, dianggap murah).
 * Prop HALAMAN AKTIF ikut direload kalau user sedang di halaman yang datanya
 * sendiri (bukan cuma badge-nya) jadi basi.
 */
function reloadStaleProps() {
    const badgeProps = ['unreadNotificationsCount', 'myTasksCount', 'reviewTasksCount', 'pendingExtensionsCount'];

    if (route().current('tasks.my')) {
        router.reload({ only: [...badgeProps, 'groups'] });
    } else if (route().current('extensions.my')) {
        router.reload({ only: [...badgeProps, 'tasks', 'extensions'] });
    } else if (route().current('extensions.index')) {
        router.reload({ only: [...badgeProps, 'extensions'] });
    } else {
        router.reload({ only: badgeProps });
    }
}

export function useFcm() {
    /**
     * KONTRAK: minta izin notifikasi browser + daftarkan token FCM ke backend.
     * WAJIB dipanggil dari gestur klik user (tombol "Aktifkan"), BUKAN otomatis
     * saat halaman dibuka. Return false (silent, BUKAN throw) kalau browser
     * tidak dukung FCM atau user menolak izin — pemanggil cukup cek boolean.
     */
    async function enable(): Promise<boolean> {
        const messaging = await getFirebaseMessaging();
        if (!messaging) return false;

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return false;

        const token = await getToken(messaging, { vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY });
        if (!token) return false;

        await fetch(route('push-subscriptions.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({ fcm_token: token, device_label: navigator.userAgent.slice(0, 255) }),
        });

        return true;
    }

    useEffect(() => {
        let unsubscribe: (() => void) | undefined;
        let cancelled = false;

        // WORKAROUND: efek ini jalan OTOMATIS di SETIAP halaman (dipasang di
        // header, bukan di balik klik user) -- kalau getFirebaseMessaging()
        // reject (mis. config Firebase belum diisi Boss), TANGKAP diam-diam
        // (nol notifikasi ke user, nol console error mengganggu), JANGAN biarkan
        // unhandled rejection. Beda dari enable() yang MEMANG boleh gagal
        // terlihat (pemanggilnya, push-permission-prompt.tsx, punya try/catch sendiri).
        getFirebaseMessaging()
            .then((messaging) => {
                if (!messaging || cancelled) return;

                unsubscribe = onMessage(messaging, (payload) => {
                    // SUMBER: payload data-only (lihat header modul) -- Firebase
                    // TIDAK auto-nampilkan apa pun di jalur foreground ini,
                    // WAJIB manual. `new Notification()` langsung dari halaman
                    // (BUKAN lewat service worker) -- aman, permission SUDAH
                    // granted (syarat onMessage() ini bisa jalan sama sekali).
                    if (payload.data?.title) {
                        new Notification(payload.data.title, { body: payload.data.body, icon: '/favicon.png' });
                    }

                    reloadStaleProps();
                });
            })
            .catch(() => {});

        return () => {
            cancelled = true;
            unsubscribe?.();
        };
    }, []);

    return {
        enable,
        permission: typeof Notification === 'undefined' ? 'unsupported' : Notification.permission,
    };
}
