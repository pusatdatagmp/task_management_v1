<?php

/**
 * ==========================================================
 * MODUL       : TaskTemplate
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Blueprint recurring task (F-46). Skema Hari-1, engine generator v0.8.
 *               AE-2b (F-158): jadwal sungguhan sekarang dari anchor_strategy dkk,
 *               dibaca aktif oleh automation:run (BUKAN lagi "belum dipakai").
 * DIPANGGIL   : (belum dipakai — v0.8 scheduler harian)
 * MEMANGGIL   : Organization, Project
 * DATA MASUK  : Seeder 3 template (daily/weekly/monthly), is_active=true, belum generate
 * DATA KELUAR : Task.task_template_id menunjuk balik ke sini saat instance lahir (v0.8)
 * RISIKO      : last_generated_date WAJIB diisi scheduler v0.8 — tanpa ini task
 *               duplikat kalau scheduler jalan 2x (F-61). Kolom anchor_strategy,
 *               interval_value, interval_unit, anchor_config, date_window_config,
 *               max_active_instances, blocked_since, last_block_notified_at (F-159)
 *               dibaca Pipeline AE-2/3 sejak AE-2b -- HANYA `task_type`/
 *               `recurrence_config` yang murni skema (dead-tapi-aman, permintaan
 *               Boss 2026-08-07: dipertahankan cuma supaya jalur rollback F-162
 *               `GenerateRecurringTasksCommand` tetap utuh, TIDAK lagi ditampilkan/
 *               diisi dari form -- lihat `schedule_label` di bawah).
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskTemplate extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    /**
     * KONTRAK: dibaca create.tsx/edit.tsx/index.tsx/all.tsx sebagai teks
     * "Jadwal" pengganti dropdown daily/weekly/monthly lama (permintaan Boss
     * 2026-08-07) -- SATU sumber label, dipakai juga saat GenerateTaskAction
     * menyalin ke Task.task_type instance baru (F-38 spirit: dihitung dari
     * config, bukan disimpan dobel di kolom lain).
     */
    protected $appends = ['schedule_label'];

    protected $fillable = [
        'organization_id',
        'project_id',
        'title',
        'description',
        'task_type',
        'estimated_minutes',
        'points',
        'priority',
        'recurrence_config',
        // Revisi 2026-08-06 item 7: nullable, null = perilaku LAMA (due_date =
        // target_date, sama hari). Terisi = jumlah HARI KERJA dari hari lahir
        // sampai jatuh tempo (GenerateTaskAction::execute()).
        'due_offset_days',
        'default_assignees',
        'is_active',
        'last_generated_date',
        // F-151/158/161: kolom Automation Engine v1.3 — skema H1, dibaca AE-2.
        'anchor_strategy',
        'interval_value',
        'interval_unit',
        'anchor_config',
        'date_window_config',
        'max_active_instances',
        'blocked_since',
        'last_block_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_config' => 'array',
            'default_assignees' => 'array',
            'is_active' => 'boolean',
            'last_generated_date' => 'date',
            'anchor_config' => 'array',
            'date_window_config' => 'array',
            'blocked_since' => 'date',
            'last_block_notified_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * F-127: blueprint checklist yang NANTI disalin ke task_checklist_items tiap
     * instance lahir (copy-on-generate dibangun H5, sentuh GenerateRecurringTasksCommand).
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskTemplateChecklistItem::class)->orderBy('position');
    }

    /**
     * KONTRAK: F-158/permintaan Boss 2026-08-07 — ringkasan jadwal dari kolom
     * Automation Engine (anchor_strategy/interval_value/interval_unit/
     * anchor_config), pengganti label statis daily/weekly/monthly lama.
     * Versi PHP dari `describeSchedule()` di create.tsx/edit.tsx (JS, dipakai
     * live-preview SEBELUM simpan) — teks di sini murni ringkasan SETELAH
     * tersimpan, tanpa prefix "Preview:".
     */
    protected function scheduleLabel(): Attribute
    {
        return Attribute::make(get: function () {
            $dayNames = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jum'at", 'Sabtu', 'Minggu'];
            $unitLabels = ['day' => 'hari', 'week' => 'minggu', 'month' => 'bulan'];

            if (in_array($this->anchor_strategy, ['time_based', 'completion_based'], true)) {
                $unit = $unitLabels[$this->interval_unit] ?? $this->interval_unit;

                return "Tiap {$this->interval_value} {$unit}";
            }

            $dayOfWeek = $this->anchor_config['day_of_week'] ?? null;
            if ($dayOfWeek !== null) {
                return 'Tiap '.($dayNames[$dayOfWeek] ?? '?');
            }

            $dayOfMonth = $this->anchor_config['day_of_month'] ?? '-';

            return "Tanggal {$dayOfMonth} tiap bulan";
        });
    }
}
