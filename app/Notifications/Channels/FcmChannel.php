<?php

/**
 * ==========================================================
 * MODUL       : FcmChannel
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-184/F-185 — custom Laravel notification channel. Laravel
 *               resolve channel custom via FQCN yang di-return langsung dari
 *               via() (mekanisme baku, TANPA perlu Notification::extend()/
 *               registrasi di provider). Satu-satunya isi: dispatch job kirim
 *               push, TIDAK menyusun pesan sendiri (itu tugas toFcm() di tiap
 *               notification class, F-35-style: SATU tempat per notifikasi
 *               yang tahu isi pesannya).
 * DIPANGGIL   : Laravel (Illuminate\Notifications\ChannelManager), otomatis
 *               saat via($notifiable) notification class me-return
 *               FcmChannel::class
 * MEMANGGIL   : App\Jobs\SendFcmPushNotification
 * DATA MASUK  : $notifiable (User, harus punya properti `id`), $notification
 *               (WAJIB implementasi method toFcm(object $notifiable): array
 *               bentuk {title, body, data?} -- silent no-op kalau tidak ada,
 *               BUKAN error, supaya notification class lama yang belum sempat
 *               ditambah toFcm() tidak tiba-tiba crash kalau channel ini
 *               ke-attach)
 * DATA KELUAR : Job baru di queue `database`
 * RISIKO      : send() SENGAJA TIDAK memanggil FcmService langsung (nol HTTP
 *               call Firebase di sini) -- lihat header SendFcmPushNotification
 *               kenapa harus lewat queue.
 * ==========================================================
 */

namespace App\Notifications\Channels;

use App\Jobs\SendFcmPushNotification;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $payload = $notification->toFcm($notifiable);

        SendFcmPushNotification::dispatch(
            $notifiable->id,
            $payload['title'],
            $payload['body'],
            $payload['data'] ?? [],
        );
    }
}
