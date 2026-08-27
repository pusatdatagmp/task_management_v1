<?php

/**
 * ==========================================================
 * MODUL       : FcmService
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-184/F-185 — bungkus kreait/firebase-php (Firebase Admin SDK
 *               PHP) supaya kirim push FCM cukup satu method call, pola SAMA
 *               DashboardService/LeaderboardService (plain class, TANPA
 *               interface/binding container, langsung di-instantiate).
 * DIPANGGIL   : App\Jobs\SendFcmPushNotification (SATU-SATUNYA pemanggil --
 *               service ini TIDAK dipanggil langsung dari Observer/Controller,
 *               lihat header Job itu kenapa harus lewat queue)
 * MEMANGGIL   : Kreait\Firebase\Factory (baca config('services.fcm.credentials')),
 *               App\Models\PushToken (hapus token yang ditolak Firebase)
 * DATA MASUK  : Daftar fcm_token + judul/isi/data notifikasi
 * DATA KELUAR : Push ke device via Firebase, baris push_tokens terhapus kalau
 *               Firebase bilang token itu sudah tidak valid/unregistered
 * RISIKO      : SUMBER : constructor MEMBACA config('services.fcm.credentials')
 *               SAAT DIBUAT (bukan lazy) -- kalau file service-account belum ada
 *               (Boss belum kasih kredensial), instantiate FcmService LEMPAR
 *               EXCEPTION. Ini SENGAJA hanya terjadi di dalam queued Job (lihat
 *               SendFcmPushNotification), BUKAN di request/observer sinkron --
 *               jadi kredensial salah/kosong TIDAK PERNAH menggagalkan alur
 *               utama (assign task, approve, dst), cuma Job-nya yang retry lalu
 *               masuk failed_jobs.
 *               SUMBER (ditemukan saat verifikasi browser bareng Boss): title/body
 *               DIKIRIM SEBAGAI DATA (bukan withNotification()) -- kalau pesan
 *               punya field "notification", Firebase Web SDK OTOMATIS nampilin
 *               popup lewat service worker begitu tab TIDAK fokus OS (nol kontrol
 *               kita), TAPI kalau tab FOKUS, pesan lewat onMessage() di halaman
 *               dan TIDAK auto-muncul apa pun -- device Boss cuma refresh badge
 *               diam-diam, popup-nya "hilang" tanpa terlihat ada masalah. Data-only
 *               bikin KEDUA jalur (SW background & onMessage foreground) SAMA-SAMA
 *               WAJIB nampilkan notifikasi manual (lihat firebase-messaging-sw.blade.php
 *               & use-fcm.ts) -- perilaku konsisten, tidak bergantung status fokus.
 * ==========================================================
 */

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Throwable;

class FcmService
{
    private Messaging $messaging;

    public function __construct()
    {
        $this->messaging = (new Factory)
            ->withServiceAccount(config('services.fcm.credentials'))
            ->createMessaging();
    }

    /**
     * KONTRAK: kirim SATU notifikasi yang SAMA ke banyak token sekaligus
     * (sendMulticast, 1 HTTP request Firebase utk semua device user itu).
     * Token yang Firebase tolak sebagai invalid/unregistered otomatis DIHAPUS
     * dari push_tokens -- kalau ini tidak dilakukan, push berikutnya akan terus
     * mencoba token mati selamanya (device lama/uninstall/logout browser).
     *
     * @param  array<int, string>  $tokens
     * @param  array<string, string>  $data  nilai HARUS string -- batasan payload data FCM.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        if (empty($tokens)) {
            return;
        }

        // SUMBER: data-only (BUKAN withNotification()) -- lihat RISIKO header
        // modul. title/body dititip di dalam data, dibaca manual oleh
        // onBackgroundMessage() (SW) & onMessage() (foreground, use-fcm.ts).
        $message = CloudMessage::new()->withData([...$data, 'title' => $title, 'body' => $body]);

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);
        } catch (Throwable $e) {
            Log::warning('FCM sendMulticast gagal total', ['error' => $e->getMessage()]);

            throw $e; // biarkan SendFcmPushNotification Job retry (F-184: transient outage Firebase).
        }

        $invalidTokens = $report->invalidTokens();
        if (! empty($invalidTokens)) {
            PushToken::whereIn('fcm_token', $invalidTokens)->delete();
        }
    }
}
