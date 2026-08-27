<?php

/**
 * ==========================================================
 * MODUL       : SendFcmPushNotification
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-184/F-185 — kirim push FCM lewat queue (`database`, F-6/F-38
 *               nol infrastruktur baru, cuma job PERTAMA yang benar-benar
 *               memakainya). Dipisah dari FcmChannel supaya kredensial Firebase
 *               salah/kosong atau Firebase down TIDAK PERNAH memblokir alur
 *               Observer yang memicunya (assign task, approve, extension, dst) --
 *               notifikasi database (channel lama, F-6) tetap tersimpan APA PUN
 *               yang terjadi di sini.
 * DIPANGGIL   : App\Notifications\Channels\FcmChannel::send()
 * MEMANGGIL   : App\Models\PushToken (baca token user), App\Services\FcmService
 * DATA MASUK  : userId + judul/isi/data notifikasi (SUDAH final dari toFcm()
 *               notification class, job ini TIDAK menyusun pesan)
 * DATA KELUAR : Push ke device via FcmService::sendToTokens()
 * RISIKO      : GOTCHA OrganizationScope (F-15) — job ini jalan di worker
 *               (php artisan queue:work/listen), BUKAN di dalam HTTP request.
 *               Auth::hasUser() FALSE di situ -> OrganizationScope TIDAK
 *               memfilter organization_id SAMA SEKALI (bukan "memfilter ke
 *               kosong", betul-betul tanpa filter). AMAN di sini KARENA query
 *               dibatasi user_id (satu user = satu organisasi pasti, bukan
 *               query lintas-organisasi) -- job FCM LAIN yang nanti query
 *               PushToken TANPA syarat user_id WAJIB tambah
 *               where('organization_id', ...) manual, jangan andalkan scope.
 * ==========================================================
 */

namespace App\Jobs;

use App\Models\PushToken;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendFcmPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(FcmService $fcm): void
    {
        $tokens = PushToken::where('user_id', $this->userId)->pluck('fcm_token')->all();

        $fcm->sendToTokens($tokens, $this->title, $this->body, $this->data);
    }
}
