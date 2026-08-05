<?php

/**
 * ==========================================================
 * MODUL       : AutomationRunLog
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Backbone observability Automation Engine (F-159 poin 3) — 1 baris
 *               per evaluasi template per run, queryable (bukan cuma log file).
 * DIPANGGIL   : RunAutomationEngineCommand (INSERT 1 baris per Decision)
 * MEMANGGIL   : Organization (F-5), TaskTemplate
 * DATA MASUK  : Decision (action/reason/target_date/delta_days/meta) dari Pipeline
 * DATA KELUAR : (AE-4, belum dibangun) UI riwayat automation
 * RISIKO      : organization_id WAJIB diisi EKSPLISIT oleh command — model ini
 *               dibuat dari konteks scheduler/artisan TANPA user login, jadi
 *               auto-fill BelongsToOrganization (bergantung Auth::hasUser()) TIDAK
 *               akan mengisi apa pun (lihat RISIKO BelongsToOrganization). F-72:
 *               trait SerializesDatesInAppTimezone WAJIB supaya run_at/target_date
 *               tidak diam-diam terkirim UTC ke UI riwayat automation kelak.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRunLog extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $table = 'automation_run_log';

    protected $fillable = [
        'organization_id',
        'task_template_id',
        'run_at',
        'action',
        'reason',
        'target_date',
        'delta_days',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'run_at' => 'datetime',
            'target_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class);
    }
}
