<?php

/**
 * ==========================================================
 * MODUL       : WorkSchedule
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Jendela kerja versioned (F-40) — sumber KAPASITAS untuk rumus
 *               IDLE_PLAN/IDLE_REAL (02-DATA-MODEL §5).
 * DIPANGGIL   : Rumus dashboard v0.8, TaskTimeSegment overlap calc (F-57, belum diimplementasi Hari-1)
 * MEMANGGIL   : Organization, User (created_by)
 * DATA MASUK  : Seeder Hari-1 (1 baris), form pengaturan jam kerja (Hari-2)
 * DATA KELUAR : daily_capacity_minutes, days_of_week, start_time/end_time
 * RISIKO      : SUMBER : F-40 — JANGAN PERNAH update baris lama lewat model ini.
 *               Ubah setting = create() baris baru dengan effective_from baru.
 *               active() sengaja tidak punya method update/edit setting di tempat lain.
 *               F-72 — serializeDate() DIPINDAH ke trait SerializesDatesInAppTimezone
 *               (dipasang di semua 14 model bisnis), bukan override lokal di sini lagi.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'organization_id',
        'effective_from',
        'days_of_week',
        'start_time',
        'end_time',
        'daily_capacity_minutes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'days_of_week' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * KONTRAK: ambil config jendela kerja yang aktif pada tanggal tertentu.
     * SUMBER : 02-DATA-MODEL §3.2 — "Config aktif = baris dengan effective_from <= today,
     *          urut desc, ambil 1". $organizationId dipakai eksplisit untuk konteks tanpa
     *          Auth (seeder/console) karena OrganizationScope hanya aktif saat ada user login.
     * F-66   : $asOf default ke SEKARANG (dipakai dashboard v0.8, form pengaturan Hari-2),
     *          tapi caller boleh isi tanggal lain — dipakai Task::calculateActualMinutes()
     *          untuk resolve config yang aktif SAAT task_time_segments.started_at, BUKAN
     *          config aktif hari ini (work_schedules versioned, F-40, segmen bisa
     *          menyeberang perubahan config).
     */
    public static function active(?int $organizationId = null, ?Carbon $asOf = null): ?self
    {
        $query = static::query();

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->where('effective_from', '<=', ($asOf ?? now())->toDateString())
            ->orderByDesc('effective_from')
            ->first();
    }
}
