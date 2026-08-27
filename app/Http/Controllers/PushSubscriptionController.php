<?php

/**
 * ==========================================================
 * MODUL       : PushSubscriptionController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-184/F-185 — daftar/hapus token FCM milik user login. JSON
 *               (bukan Inertia::render), dipanggil dari resources/js/hooks/use-fcm.ts
 *               setelah browser minta izin notifikasi & dapat token dari Firebase
 *               Web SDK, pola sama NotificationController (endpoint async, nol navigasi).
 * DIPANGGIL   : routes/web.php
 * MEMANGGIL   : App\Models\PushToken
 * DATA MASUK  : fcm_token (string dari Firebase Web SDK getToken()), device_label
 *               opsional (User-Agent, untuk debug "device mana ini")
 * DATA KELUAR : Baris push_tokens, dibaca SendFcmPushNotification Job saat kirim push
 * RISIKO      : updateOrCreate() by fcm_token (BUKAN by user_id) — device/browser
 *               yang SAMA daftar ulang (token refresh Firebase, atau login user
 *               lain di device yang sama) UPDATE baris lama supaya token selalu
 *               terhubung ke user yang BENAR SAAT INI, bukan menumpuk baris basi.
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string|max:512',
            'device_label' => 'nullable|string|max:255',
        ]);

        PushToken::updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'organization_id' => $request->user()->organization_id,
                'device_label' => $validated['device_label'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
        ]);

        PushToken::where('user_id', $request->user()->id)
            ->where('fcm_token', $validated['fcm_token'])
            ->delete();

        return response()->json(['success' => true]);
    }
}
