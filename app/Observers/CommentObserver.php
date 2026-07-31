<?php

/**
 * ==========================================================
 * MODUL       : CommentObserver
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Trigger notifikasi @mention (F-114) saat komentar dibuat/diedit.
 *               🔴 F-113 — SENGAJA TIDAK ada logActivity() di sini sama sekali.
 *               Komentar hidup di tabelnya sendiri; activity_log adalah jejak audit
 *               murni (F-51, sumber KPI) dan TIDAK BOLEH memuat isi obrolan user.
 * DIPANGGIL   : Laravel (event Eloquent) via #[ObservedBy] di App\Models\Comment
 * MEMANGGIL   : MentionNotification, User
 * DATA MASUK  : Comment baru/diedit (mentioned_user_ids sudah final dari CommentController,
 *               sudah difilter member project — C1)
 * DATA KELUAR : notifications
 * RISIKO      : SUMBER : updated() HANYA kirim ke user_id yang BARU muncul di
 *               mentioned_user_ids dibanding versi lama (C4) — kirim ulang ke yang
 *               sudah pernah disebut = spam, persis yang F-36 coba cegah untuk
 *               trigger lifecycle, semangat sama berlaku di sini.
 * ==========================================================
 */

namespace App\Observers;

use App\Models\Comment;
use App\Models\User;
use App\Notifications\MentionNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class CommentObserver
{
    public function created(Comment $comment): void
    {
        $this->notifyMentioned($comment, $comment->mentioned_user_ids ?? []);
    }

    /**
     * BUSINESS RULE: C4 — edit yang menambah mention baru cuma menotif user yang
     * BARU disebut. wasChanged() cek kolom mentioned_user_ids sendiri berubah,
     * lalu diff terhadap nilai LAMA (getOriginal()) supaya yang sudah disebut di
     * versi sebelumnya tidak dinotif ulang.
     */
    public function updated(Comment $comment): void
    {
        if (! $comment->wasChanged('mentioned_user_ids')) {
            return;
        }

        $oldIds = $comment->getOriginal('mentioned_user_ids') ?? [];
        $newIds = $comment->mentioned_user_ids ?? [];
        $newlyMentioned = array_values(array_diff($newIds, $oldIds));

        $this->notifyMentioned($comment, $newlyMentioned);
    }

    /**
     * KONTRAK: kirim MentionNotification ke tiap user_id di $userIds, KECUALI
     * penulis komentar sendiri (F-36 — mention diri sendiri tidak menghasilkan notif).
     */
    private function notifyMentioned(Comment $comment, array $userIds): void
    {
        $userIds = array_values(array_diff($userIds, [Auth::id()]));

        if (empty($userIds)) {
            return;
        }

        $recipients = User::whereIn('id', $userIds)->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new MentionNotification($comment));
        }
    }
}
