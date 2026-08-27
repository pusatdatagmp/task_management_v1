<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_27_124000_create_push_tokens_table
 * KLASIFIKASI : DATA
 * TUJUAN      : F-184 — simpan token registrasi FCM (Firebase Cloud Messaging)
 *               per browser/device, supaya notifikasi bisa dikirim sebagai push
 *               level OS/device (bukan cuma tersimpan di tabel `notifications`).
 *               Boss instruksikan majukan F-6 ("Firebase ditunda v3.0") sekarang.
 * DIPANGGIL   : App\Http\Controllers\PushSubscriptionController (register/hapus),
 *               App\Jobs\SendFcmPushNotification (baca token buat dikirimi)
 * MEMANGGIL   : organizations, users (FK)
 * DATA MASUK  : Token dari Firebase Web SDK (`getToken()`, browser), dikirim
 *               client saat user klik "Aktifkan notifikasi"
 * DATA KELUAR : Dibaca App\Services\FcmService::sendToTokens() saat kirim push
 * RISIKO      : SUMBER : F-5 — organization_id baris pertama (pola sama tags/
 *               holidays). unique(fcm_token) WAJIB — PushSubscriptionController::
 *               store() pakai updateOrCreate() by token supaya browser yang sama
 *               daftar ulang (refresh token/login ulang) UPDATE baris lama, bukan
 *               duplikat. cascadeOnDelete(user_id) — user dihapus (soft-delete
 *               F-16 tetap berlaku di tabel users, ini FK murni integritas) ikut
 *               bersihkan token mati, nol token nyasar ke user yang sudah tidak ada.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fcm_token', 512)->unique();
            $table->string('device_label')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
    }
};
