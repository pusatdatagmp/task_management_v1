<?php

/**
 * ==========================================================
 * MODUL       : Task
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Model INTI. Wadah kolom RAW dasar KPI (F-37) + self-relation subtask (F-20).
 * DIPANGGIL   : TaskObserver, seluruh service Task (Hari-3)
 * MEMANGGIL   : Organization, Project, TaskTemplate, TaskStatus, User, self (parent/children),
 *               BusinessHoursCalculator (F-57, via calculateActualMinutes())
 * DATA MASUK  : Form Task CRUD admin-only (F-29), engine recurring (v0.8)
 * DATA KELUAR : task_time_segments (realisasi), activity_logs, dashboard v0.8, scoring v1.5
 * RISIKO      : Guard saving() menolak subtask 2 level (F-20). due_date WAJIB (F-31) dan
 *               TIDAK BOLEH di-default otomatis di model (F-68) — kalau admin lupa isi,
 *               DB menolak (NOT NULL), bukan diam-diam diisi +7 hari. Validasi wajib &
 *               pre-fill +7 hari di FORM (bukan model) menyusul di FormRequest Hari-3.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Models\Scopes\OrganizationScope;
use App\Observers\TaskObserver;
use App\Services\BusinessHoursCalculator;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[ObservedBy(TaskObserver::class)]
class Task extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone, SoftDeletes;

    /**
     * BUSINESS RULE: F-35 trigger #8 — alasan penolakan diisi admin di form reject,
     * dipakai HANYA untuk isi notifikasi (bukan data KPI, tidak ada di 02-DATA-MODEL).
     * Properti PHP biasa (DEKLARASI EKSPLISIT), bukan magic attribute Eloquent —
     * kalau ini di-set lewat $task->rejectionReasonTransient tanpa dideklarasikan
     * di sini, Eloquent akan menganggapnya kolom dan meledak saat save() (unknown
     * column). DIISI: TaskTransitionService::reject(). DIBACA: TaskObserver::updated().
     */
    public ?string $rejectionReasonTransient = null;

    protected $fillable = [
        'organization_id',
        'project_id',
        'task_template_id',
        'parent_task_id',
        'task_status_id',
        'title',
        'description',
        'task_type',
        'priority',
        // F-122/F-126: kolom Eisenhower TERPISAH dari `priority` enum lama. Enum lama
        // dipertahankan di DB (legacy, disembunyikan dari UI), bukan dihapus (F-121).
        'priority_quadrant',
        'points',
        'estimated_minutes',
        'actual_minutes',
        'quality_rating',
        'rejection_count',
        'due_date',
        'original_due_date',
        'started_at',
        'completed_at',
        'approved_at',
        'approved_by',
        'position',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'original_due_date' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // BUSINESS RULE: F-20 — subtask maksimal 1 level. Parent yang sudah jadi
        // subtask (punya parent_task_id sendiri) TIDAK BOLEH dijadikan parent lagi.
        static::saving(function (Task $task) {
            if (! $task->parent_task_id) {
                return;
            }

            $parentIsSubtask = static::withoutGlobalScope(OrganizationScope::class)
                ->whereKey($task->parent_task_id)
                ->whereNotNull('parent_task_id')
                ->exists();

            if ($parentIsSubtask) {
                throw new InvalidArgumentException('Subtask maksimal 1 level (F-20) — parent yang dipilih sudah jadi subtask.');
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class);
    }

    public function taskStatus(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function assignees(): BelongsToMany
    {
        // BUSINESS RULE: pivot pakai model TaskUser (bukan default) supaya event
        // attach/detach memicu TaskUserObserver -> log assigned/unassigned (F-22, F-35 #1/#2).
        return $this->belongsToMany(User::class)->using(TaskUser::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function timeSegments(): HasMany
    {
        return $this->hasMany(TaskTimeSegment::class);
    }

    /**
     * KONTRAK: Σ realisasi seluruh segmen task, dicap ke jendela kerja (F-57).
     * DIPANGGIL: TaskObserver::updating() — SEKALI saat task pertama kali masuk
     * is_completed, hasilnya dibekukan ke actual_minutes (F-39). JANGAN dipanggil
     * lagi setelah frozen — TaskObserver sendiri sudah menjaga ini via is_null() guard.
     *
     * F-66 matang: SELURUH versi WorkSchedule organisasi dimuat SEKALI di sini (bukan
     * per segmen atau per hari) lalu diteruskan ke BusinessHoursCalculator, yang
     * me-resolve versi mana berlaku PER HARI iterasi — work_schedules versioned (F-40),
     * segmen panjang bisa menyeberang perubahan config di tengah jalan.
     * F-43: holiday organisasi juga dimuat SEKALI di sini, bukan per segmen —
     * F-85, benih N+1 kalau query diulang per baris task_time_segments.
     */
    public function calculateActualMinutes(): int
    {
        $calculator = new BusinessHoursCalculator;

        $schedules = WorkSchedule::where('organization_id', $this->organization_id)->get();
        $holidays = Holiday::where('organization_id', $this->organization_id)->get();

        return (int) $this->timeSegments()
            ->get()
            ->sum(fn (TaskTimeSegment $segment) => $calculator->overlapMinutes(
                $segment->started_at,
                $segment->ended_at,
                $schedules,
                $holidays,
            ));
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function deadlineExtensions(): HasMany
    {
        return $this->hasMany(DeadlineExtension::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * F-123/F-127: checklist dalam-tugas (BEDA dari subtask self-relation di atas).
     * Gate transisi ->REVIEW dibangun H5 — relasi ini cuma baca/tulis item, belum
     * ditegakkan jadi syarat wajib di sini.
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position');
    }
}
