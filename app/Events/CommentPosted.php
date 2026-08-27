<?php

/**
 * ==========================================================
 * MODUL       : CommentPosted
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-184 — broadcast komentar BARU lewat Laravel Reverb (WebSocket
 *               self-hosted) supaya SEMUA orang yang lagi buka halaman detail
 *               task yang sama lihat komentar muncul TANPA refresh manual.
 *               Event PERTAMA di codebase ini yang implements ShouldBroadcast —
 *               dispatch lewat queue (F-184: `database` connection yang sudah
 *               ada, BUKAN ShouldBroadcastNow) supaya Reverb down/lambat TIDAK
 *               PERNAH memblokir CommentObserver/CommentController.
 *               Scope Fase 1 SENGAJA cuma komentar BARU (created) -- edit/hapus
 *               live-sync belum diimplementasi (CommentObserver belum punya
 *               method deleted(), lihat header modul itu), follow-up terpisah
 *               kalau Boss minta.
 * DIPANGGIL   : App\Observers\CommentObserver::created()
 * MEMANGGIL   : App\Models\Comment (baca body/user/task_id untuk payload)
 * DATA MASUK  : Comment yang baru dibuat
 * DATA KELUAR : Payload JSON ke channel privat "task.{task_id}" (routes/channels.php),
 *               dikonsumsi resources/js/pages/tasks/show.tsx via useEcho()
 * RISIKO      : `is_mine` SENGAJA TIDAK dikirim di payload -- itu relatif ke
 *               VIEWER (bukan ke pembuat comment), beda per orang yang nonton.
 *               Frontend WAJIB hitung sendiri (bandingkan payload.user.id ke
 *               auth.user.id lokal), BUKAN dikirim dari sini. Body dikirim APA
 *               ADANYA (sama seperti TaskController::show(), nol sanitasi HTML
 *               tambahan -- React sudah escape otomatis lewat text node biasa,
 *               lihat renderBody() task-comments.tsx).
 * ==========================================================
 */

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Comment $comment) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('task.'.$this->comment->task_id)];
    }

    public function broadcastAs(): string
    {
        return 'comment.posted';
    }

    /**
     * KONTRAK: bentuk field IDENTIK TaskController::show()'s 'comments' map
     * (id/body/user/created_at/is_edited/is_deleted) MINUS is_mine (lihat RISIKO
     * header) -- supaya frontend bisa perlakukan payload broadcast ini SAMA
     * seperti satu baris dari prop `comments`, nol pemetaan field terpisah.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->comment->id,
            'task_id' => $this->comment->task_id,
            'body' => $this->comment->body,
            'user' => $this->comment->user->only(['id', 'name']),
            'created_at' => $this->comment->created_at,
            'is_edited' => false,
            'is_deleted' => false,
        ];
    }
}
