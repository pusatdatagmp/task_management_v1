<?php

/**
 * ==========================================================
 * MODUL       : TaskTimeSegment
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : JANTUNG REALISASI (F-41). Satu baris = satu rentang waktu kerja nyata.
 * DIPANGGIL   : TaskObserver (insert/update), rumus realisasi (v0.8, belum diimplementasi)
 * MEMANGGIL   : Organization, Task, User
 * DATA MASUK  : Transisi status task via observer (bukan input manual)
 * DATA KELUAR : Σ overlap(started_at, ended_at, work_schedule) -> tasks.actual_minutes (F-39)
 * RISIKO      : Tabel ini TIDAK punya kolom updated_at (F-38: hanya timestamp, tidak ada
 *               state "durasi berjalan" yang perlu di-update tiap menit). UPDATED_AT
 *               di-null-kan supaya Eloquent tidak mencoba menulis kolom yang tidak ada.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTimeSegment extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'task_id',
        'user_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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
