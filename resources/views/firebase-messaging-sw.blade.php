{{--
==========================================================
MODUL       : firebase-messaging-sw.blade.php
KLASIFIKASI : CONFIG
TUJUAN      : F-184/F-185 -- service worker FCM (background push, notif muncul
              walau tab/browser ditutup). Blade (BUKAN file statis public/) supaya
              config Firebase Web (public, aman diekspos) SATU sumber dari
              config('services.fcm.web') / .env, tidak perlu di-hardcode dobel
              di sini + resources/js/lib/firebase.ts.
DIPANGGIL   : Browser (auto-register oleh firebase/messaging JS SDK, getToken()
              di resources/js/hooks/use-fcm.ts), route GET /firebase-messaging-sw.js
              (routes/web.php, DI LUAR middleware auth -- service worker fetch
              tanpa cookie session)
MEMANGGIL   : Firebase compat SDK (CDN gstatic, resmi Google)
DATA MASUK  : config('services.fcm.web') (dari .env, lihat config/services.php)
DATA KELUAR : self.registration.showNotification() -- notifikasi OS/browser
RISIKO      : WAJIB di-serve dari ROOT domain (/firebase-messaging-sw.js), BUKAN
              /js/firebase-messaging-sw.js -- scope service worker terbatas ke
              path-nya sendiri & di bawahnya, default getToken() cuma cari di root.
==========================================================
--}}
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: '{{ config('services.fcm.web.api_key') }}',
    authDomain: '{{ config('services.fcm.web.auth_domain') }}',
    projectId: '{{ config('services.fcm.web.project_id') }}',
    messagingSenderId: '{{ config('services.fcm.web.messaging_sender_id') }}',
    appId: '{{ config('services.fcm.web.app_id') }}',
});

const messaging = firebase.messaging();

// Background push (tab tidak fokus OS/browser ditutup) -- foreground push
// ditangani TERPISAH oleh onMessage() di resources/js/hooks/use-fcm.ts, BUKAN
// di sini (Firebase yang membedakan dua jalur ini berdasar status fokus tab).
//
// SUMBER: title/body dibaca dari payload.data (BUKAN payload.notification) --
// FcmService::sendToTokens() SENGAJA kirim data-only (lihat header PHP-nya) --
// kalau pesan pakai field "notification", Firebase auto-nampilin ini TANPA
// pernah masuk sini, cocok untuk kasus itu tapi TIDAK untuk kasus foreground
// (di situ pesan malah ke onMessage() page, nol popup otomatis) -- data-only
// bikin KEDUA jalur konsisten wajib showNotification() manual.
messaging.onBackgroundMessage((payload) => {
    self.registration.showNotification(payload.data.title, {
        body: payload.data.body,
        icon: '/favicon.png',
    });
});
