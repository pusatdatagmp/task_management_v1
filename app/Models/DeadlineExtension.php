<?php

/**
 * ==========================================================
 * MODUL       : DeadlineExtension
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Alur pengajuan perpanjangan deadline (F-50) — satu-satunya jalan legal
 *               menggeser due_date karena F-29 melarang member ubah due_date langsung.
 * DIPANGGIL   : DeadlineExtensionObserver, notifikasi (F-35 trigger #9/#10)
 * MEMANGGIL   : Organization, Task, User (requested_by, reviewed_by)
 * DATA MASUK  : Form pengajuan member + evidence attachment
 * DATA KELUAR : Saat approve -> Task.original_due_date/due_date/estimated_minutes (F-47)
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Observers\DeadlineExtensionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(DeadlineExtensionObserver::class)]
class DeadlineExtension extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'organization_id',
        'task_id',
        'requested_by',
        'old_due_date',
        'requested_due_date',
        'additional_minutes',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'old_due_date' => 'datetime',
            'requested_due_date' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
