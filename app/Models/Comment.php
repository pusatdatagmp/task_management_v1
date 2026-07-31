<?php

/**
 * ==========================================================
 * MODUL       : Comment
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Diskusi per task (v1.0 H3, F-113) — tabel SENDIRI, terpisah dari
 *               activity_logs (F-51 audit murni, sumber KPI). @mention (F-114)
 *               dicache di mentioned_user_ids saat simpan, bukan di-parse ulang tiap baca.
 * DIPANGGIL   : CommentController (CRUD), CommentObserver (notif mention)
 * MEMANGGIL   : Organization, Task, User (penulis)
 * DATA MASUK  : Form komentar member/admin project (F-95)
 * DATA KELUAR : Ditampilkan di tasks/show.tsx, mentioned_user_ids dibaca CommentObserver
 * RISIKO      : F-115 — SoftDeletes WAJIB terpasang. Hapus komentar = deleted_at
 *               terisi, BUKAN baris hilang. JANGAN pernah forceDelete() di controller.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Observers\CommentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(CommentObserver::class)]
class Comment extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'task_id',
        'user_id',
        'body',
        'mentioned_user_ids',
    ];

    protected function casts(): array
    {
        return [
            'mentioned_user_ids' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
