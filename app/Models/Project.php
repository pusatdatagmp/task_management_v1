<?php

/**
 * ==========================================================
 * MODUL       : Project
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Level ke-2 hierarki (F-8: Org -> Project -> Task -> Subtask).
 * DIPANGGIL   : ProjectController (Hari-2, belum dibuat), TaskStatus/TaskTemplate/Task
 * MEMANGGIL   : Organization, User (owner + members)
 * DATA MASUK  : Form CRUD project admin-only (F-29)
 * DATA KELUAR : project_id dipakai tasks, task_statuses, task_templates
 * RISIKO      : F-16 — soft delete wajib (dipasang lewat SoftDeletes), hard delete
 *               project dengan riwayat task/KPI DILARANG.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Observers\ProjectObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ProjectObserver::class)]
class Project extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'owner_id',
        'is_archived',
        // F-125: goal/due_date = integrasi mockup v1.7 (modal detail proyek,
        // kolom Dateline). `status` SENGAJA TIDAK ada di sini — akan DITURUNKAN
        // dari agregasi task (aturan derivasi + method-nya dibangun H3), bukan
        // disimpan sebagai kolom.
        'goal',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'due_date' => 'date',
        ];
    }

    /**
     * KONTRAK (2026-08-08, keputusan Boss): owner_id/owner() TETAP ada, sekarang
     * cerminan otomatis owner posisi-0 (pivot project_owners) -- lihat owners()
     * di bawah untuk daftar LENGKAP. Dipertahankan apa adanya supaya automation
     * (GenerateRecurringTasksCommand/GenerateTaskAction, created_by) dan kode lama
     * yang baca owner_id/owner() tunggal TIDAK perlu berubah.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * KONTRAK: daftar LENGKAP owner (F-28 "reviewer"), terurut posisi pilih di
     * form (position=0 = utama, cerminan owner_id/owner() di atas). Pivot pakai
     * model ProjectOwner (bukan default) supaya sync() memicu ProjectOwnerObserver
     * -> log assigned/unassigned (F-51/F-71), pola IDENTIK members().
     */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_owners')
            ->using(ProjectOwner::class)
            ->withPivot('position')
            ->orderBy('project_owners.position');
    }

    public function members(): BelongsToMany
    {
        // BUSINESS RULE: F-71 — pivot pakai model ProjectUser (bukan default) supaya
        // event attach/detach/sync memicu ProjectUserObserver -> log assigned/unassigned.
        // Sebelum ini, sync member TIDAK tercatat (lubang audit trail F-51).
        return $this->belongsToMany(User::class)->using(ProjectUser::class);
    }

    public function taskStatuses(): HasMany
    {
        return $this->hasMany(TaskStatus::class)->orderBy('position');
    }

    public function taskTemplates(): HasMany
    {
        return $this->hasMany(TaskTemplate::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
