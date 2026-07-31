<?php

/**
 * ==========================================================
 * MODUL       : NotificationController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Endpoint bell dropdown (F-35 §C4) — 10 notifikasi terbaru, tandai
 *               dibaca satu/semua. JSON (bukan Inertia::render), sama seperti
 *               TaskController::search() — dropdown butuh fetch async tanpa navigasi.
 * DIPANGGIL   : routes/web.php
 * MEMANGGIL   : User::notifications() (Notifiable trait bawaan Laravel)
 * DATA MASUK  : -
 * DATA KELUAR : JSON [notifications[], unread_count] ke bell dropdown
 * RISIKO      : SUMBER : F-72 — DatabaseNotification adalah model BAWAAN Laravel,
 *               TIDAK bisa dipasangi trait SerializesDatesInAppTimezone (bukan
 *               model kita). created_at di-format manual di sini pakai pola yang
 *               SAMA (Carbon::setTimezone(config('app.timezone'))) supaya tidak
 *               diam-diam kembali ke UTC seperti kasus effective_from Hari-3.
 * ==========================================================
 */

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->limit(10)->get()
            ->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? null,
                'message' => $n->data['message'] ?? '',
                'task_id' => $n->data['task_id'] ?? null,
                'project_id' => $n->data['project_id'] ?? null,
                'read_at' => $n->read_at,
                'created_at' => Carbon::instance($n->created_at)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:sP'),
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }
}
