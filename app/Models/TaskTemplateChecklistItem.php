<?php

/**
 * ==========================================================
 * MODUL       : TaskTemplateChecklistItem
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Blueprint checklist di task_templates (F-127) — daftar item yang
 *               disalin ke TaskChecklistItem tiap instance recurring lahir. Tanpa
 *               is_done (baris ini adalah TEMPLATE, belum ada instance untuk
 *               "selesai"). Copy-on-generate = H5, hari ini cuma model+relasi.
 * DIPANGGIL   : (belum — GenerateRecurringTasksCommand H5)
 * MEMANGGIL   : Organization, TaskTemplate
 * DATA MASUK  : Form checklist di halaman template (H5)
 * DATA KELUAR : Disalin jadi TaskChecklistItem saat instance task lahir (H5)
 * RISIKO      : Kalau H5 lupa salin baris di sini saat generate, instance recurring
 *               lahir tanpa checklist walau template-nya sudah diisi — silent gap.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplateChecklistItem extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'organization_id',
        'task_template_id',
        'text',
        'position',
    ];

    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class);
    }
}
