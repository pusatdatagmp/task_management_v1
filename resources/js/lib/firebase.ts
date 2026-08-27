// ==========================================================
// MODUL       : firebase
// KLASIFIKASI : CONFIG
// TUJUAN      : F-184/F-185 — init Firebase Web SDK (FOREGROUND saja, tab lagi
//               dibuka). Background push (tab tidak fokus/browser ditutup) lewat
//               jalur TERPISAH: resources/views/firebase-messaging-sw.blade.php
//               (service worker, config-nya dari config('services.fcm.web') di
//               server, BUKAN file ini).
// DIPANGGIL   : resources/js/hooks/use-fcm.ts
// MEMANGGIL   : firebase/app, firebase/messaging (npm package resmi Google)
// DATA MASUK  : import.meta.env.VITE_FIREBASE_* (Vite env, di-mirror dari
//               FIREBASE_* di .env — lihat .env.example untuk daftar lengkap)
// DATA KELUAR : instance Messaging (dipakai getToken()/onMessage() di use-fcm.ts)
// RISIKO      : Nilai-nilai ini AMAN diekspos ke browser (Firebase Web SDK config
//               MEMANG publik by design, keamanan Firebase bukan dari menyembunyikan
//               ini — beda dari FCM_SERVICE_ACCOUNT_PATH di backend yang WAJIB
//               rahasia). isSupported() FALSE di browser lama/iOS Safari non-PWA
//               — getFirebaseMessaging() balik null, pemanggil WAJIB cek null
//               (nol crash di browser yang tidak dukung).
// ==========================================================

import { initializeApp, type FirebaseApp } from 'firebase/app';
import { getMessaging, isSupported, type Messaging } from 'firebase/messaging';

const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

let app: FirebaseApp | null = null;

export async function getFirebaseMessaging(): Promise<Messaging | null> {
    if (!(await isSupported())) {
        return null;
    }

    app ??= initializeApp(firebaseConfig);

    return getMessaging(app);
}
