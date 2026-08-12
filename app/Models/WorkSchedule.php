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
 * RISIKO      : SUMBER : F-40 — JANGAN PERNAH update baris yang SUDAH PERNAH aktif
 *               (effective_from <= hari ini, PERNAH dipakai hitung actual_minutes/KPI)
 *               lewat model ini. Ubah setting versi itu = create() baris baru dengan
 *               effective_from baru.
 *               REVISI 2026-08-10 (audit Boss, F-40 tetap dihormati): edit/arsip
 *               manual SEKARANG diizinkan TERBATAS untuk versi FUTURE (effective_from
 *               > hari ini, belum pernah aktif, nol dampak KPI) -- guard ketat ada
 *               di WorkScheduleController::update()/archive(), BUKAN di model ini
 *               (model tetap pasif, nol validasi tanggal di sini).
 *               F-72 — serializeDate() DIPINDAH ke trait SerializesDatesInAppTimezone
 *               (dipasang di semua 14 model bisnis), bukan override lokal di sini lagi.
 *               F-169 — #[ObservedBy(WorkScheduleObserver::class)] DITAMBAHKAN (audit
 *               Boss 2026-08-12): sebelumnya model ini NOL Observer, tiap create/
 *               update lolos tanpa jejak di activity_logs (celah F-51). Sumber "Log
 *               Perubahan" di halaman Pengaturan > Jam Kerja.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Observers\WorkScheduleObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(WorkScheduleObserver::class)]
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
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'days_of_week' => 'array',
            'is_archived' => 'boolean',
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

        // F-40 (revisi 2026-08-10): is_archived DIKECUALIKAN -- versi yang
        // dibatalkan admin (SELALU versi future saat diarsipkan, lihat guard
        // WorkScheduleController::archive()) tidak boleh diam-diam "hidup lagi"
        // jadi versi aktif kalau kalender jalan terus melewati effective_from-nya.
        return $query->where('effective_from', '<=', ($asOf ?? now())->toDateString())
            ->where('is_archived', false)
            ->orderByDesc('effective_from')
            ->first();
    }
}
