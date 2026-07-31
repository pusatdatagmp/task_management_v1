<?php

/**
 * ==========================================================
 * MODUL       : TaskStatus
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Status task custom per project dengan TIGA FLAG (F-44) — logika sistem
 *               bergantung pada flag, BUKAN nama status.
 * DIPANGGIL   : TaskObserver, ProjectController::store() (seedDefaults, Hari-3)
 * MEMANGGIL   : Organization, Project
 * DATA MASUK  : Seeder 4 status default per project
 * DATA KELUAR : task_status_id di Task
 * RISIKO      : JANGAN PERNAH cek nama status di kode manapun yang memakai model ini —
 *               selalu cek is_work_state/is_review/is_completed (F-44).
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskStatus extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'organization_id',
        'project_id',
        'name',
        'color',
        'position',
        'is_work_state',
        'is_review',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'is_work_state' => 'boolean',
            'is_review' => 'boolean',
            'is_completed' => 'boolean',
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
     * KONTRAK: buat 4 status default (02-DATA-MODEL §3.7) untuk project baru.
     * DIPANGGIL: ProjectController::store(), DIBUNGKUS DB::transaction() bersama
     * Project::create() — project tanpa status adalah project rusak (Hari-3 §C3),
     * jadi kalau salah satu gagal, keduanya harus batal.
     *
     * GUARD: organization_id diambil EKSPLISIT dari $project, BUKAN mengandalkan
     * auto-fill BelongsToOrganization (yang butuh Auth::hasUser()). Method ini bisa
     * dipanggil dari konteks tanpa user login (test, artisan command, queue) —
     * auto-fill via Auth akan diam-diam gagal isi NULL di sana dan meledak di
     * constraint NOT NULL, bukan salah project-nya.
     */
    public static function seedDefaults(Project $project): void
    {
        $blueprint = [
            ['name' => 'TODO', 'color' => '#94a3b8', 'position' => 0, 'is_work_state' => false, 'is_review' => false, 'is_completed' => false],
            ['name' => 'IN PROGRESS', 'color' => '#3b82f6', 'position' => 1, 'is_work_state' => true, 'is_review' => false, 'is_completed' => false],
            ['name' => 'REVIEW', 'color' => '#f59e0b', 'position' => 2, 'is_work_state' => false, 'is_review' => true, 'is_completed' => false],
            ['name' => 'DONE', 'color' => '#22c55e', 'position' => 3, 'is_work_state' => false, 'is_review' => false, 'is_completed' => true],
        ];

        foreach ($blueprint as $status) {
            static::create([
                'organization_id' => $project->organization_id,
                'project_id' => $project->id,
                ...$status,
            ]);
        }
    }

    /**
     * KONTRAK: true kalau MENGECUALIKAN $excludingId akan membuat project ini
     * kehilangan SEMUA status is_work_state=true.
     * DIPAKAI: TaskStatusController::destroy() — SATU-SATUNYA validasi flag yang
     * masih perlu cross-check lintas baris setelah Hari-5 (F-74). is_completed/
     * is_review TIDAK butuh ini lagi: radio di halaman index membuatnya STRUKTURAL
     * tidak mungkin 0/2 (lihat TaskStatusController::updateFlags() — set semua
     * false lalu set yang dipilih true, dalam satu transaction). is_work_state
     * tetap checkbox (boleh lebih dari satu), jadi validasi "minimal 1" ini yang
     * satu-satunya sisa dari flagConstraintViolation() versi lama.
     */
    public static function wouldLeaveNoWorkState(Project $project, int $excludingId): bool
    {
        return static::where('project_id', $project->id)
            ->where('is_work_state', true)
            ->whereKeyNot($excludingId)
            ->doesntExist();
    }
}
