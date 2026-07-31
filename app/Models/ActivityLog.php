<?php

/**
 * ==========================================================
 * MODUL       : ActivityLog
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : TULANG PUNGGUNG sistem (F-22/F-23/F-51). Satu-satunya sumber untuk
 *               4 dari 6 metrik KPI. IMMUTABLE selamanya — dipaksa di level model,
 *               bukan cuma konvensi, supaya tidak ada kode lain yang bisa mengakali.
 * DIPANGGIL   : Semua Observer (Task/Project/DeadlineExtension/Attachment)
 * MEMANGGIL   : Organization, User (pelaku, nullable)
 * DATA MASUK  : SETIAP perubahan Task/Project/Extension/Attachment via Eloquent Observer
 * DATA KELUAR : v1.5 Scoring Engine (derived KPI), audit trail
 * RISIKO      : GUARD save()/delete() di bawah SENGAJA melempar exception kalau ada
 *               kode yang mencoba update/hapus baris log (F-23). Jangan dihapus.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class ActivityLog extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'user_id',
        'subject_type',
        'subject_id',
        'event',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * GUARD: F-23 — activity_logs IMMUTABLE selamanya. save() pada baris yang
     * sudah ada (bukan create baru) DITOLAK di level model, bukan cuma konvensi.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('activity_logs immutable (F-23) — tidak boleh update baris yang sudah ada.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('activity_logs immutable (F-23) — tidak boleh hapus, selamanya.');
    }
}
