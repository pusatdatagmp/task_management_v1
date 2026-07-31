<?php

/**
 * ==========================================================
 * MODUL       : TaskChecklistItem
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Checklist dalam-tugas (F-123) — BEDA dari subtask (F-20, self-relation
 *               di Task). Item ringan text+is_done, dipakai gate transisi ->REVIEW.
 *               Hari ini (v1.2 H2) cuma model+relasi — gate SERVER-side & UI di H5
 *               (F-127 masih TERBUKA: gate-only vs wajib >=1 item per task).
 * DIPANGGIL   : (belum — TaskController/TaskTransitionService H5)
 * MEMANGGIL   : Organization, Task
 * DATA MASUK  : Form checklist admin/assignee di halaman detail task (H5)
 * DATA KELUAR : TaskTransitionService (gate ->REVIEW, H5)
 * RISIKO      : Belum ada observer/log di sini — perubahan checklist TIDAK tercatat
 *               activity_logs hari ini (F-51 gap sementara). Ditutup saat H5 bangun
 *               fitur nyata, bukan lupa — dicatat di laporan H2.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'organization_id',
        'task_id',
        'text',
        'is_done',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
