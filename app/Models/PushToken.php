<?php

/**
 * ==========================================================
 * MODUL       : PushToken
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-184/F-185 — token registrasi FCM per browser/device milik satu
 *               user, dasar pengiriman push notification level OS (bell/review/
 *               perpanjangan/tugas saya). Satu user bisa punya banyak baris
 *               (banyak device/browser login bersamaan).
 * DIPANGGIL   : PushSubscriptionController (register/hapus), SendFcmPushNotification
 *               Job (baca token buat dikirimi)
 * MEMANGGIL   : Organization (BelongsToOrganization), User
 * DATA MASUK  : POST push-subscriptions (fcm_token dari Firebase Web SDK)
 * DATA KELUAR : Dibaca FcmService::sendToTokens()
 * RISIKO      : Token yang ditolak Firebase sebagai invalid/unregistered DIHAPUS
 *               otomatis oleh FcmService (bukan di sini) — kalau logic itu lupa
 *               dipanggil, tabel ini bisa menumpuk token mati selamanya.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushToken extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'organization_id',
        'user_id',
        'fcm_token',
        'device_label',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
