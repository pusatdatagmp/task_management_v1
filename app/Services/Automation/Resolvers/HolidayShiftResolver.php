<?php

/**
 * ==========================================================
 * MODUL       : HolidayShiftResolver
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Forward-Shift SEMUA tipe TERMASUK harian (F-153, mengubah F-102
 *               lama) — target_date mulai dari now_WIB, maju hari-per-hari sampai
 *               ketemu hari kerja (F-43 holiday menang atas hari kerja biasa).
 * DIPANGGIL   : Pipeline (SETELAH seluruh Guard chain Pass)
 * MEMANGGIL   : BusinessHoursCalculator::isBusinessDay() (F-72/F-76 -- SATU sumber
 *               kebenaran "hari kerja atau bukan", REUSE, bukan kalkulator baru)
 * DATA MASUK  : now_WIB, WorkSchedule & Holiday organisasi (preload F-85 dari AutomationContext)
 * DATA KELUAR : Carbon target_date (hari kerja pertama >= now_WIB) | null (config korup)
 * RISIKO      : SUMBER pola sama GenerateRecurringTasksCommand::MAX_SHIFT_DAYS --
 *               batas 30 hari mencegah loop nyaris tak berhingga kalau organisasi
 *               tidak punya satu pun WorkSchedule hari kerja terdaftar. null WAJIB
 *               ditangani pemanggil sebagai Decision::error, BUKAN diam-diam
 *               dianggap "hari ini".
 * ==========================================================
 */

namespace App\Services\Automation\Resolvers;

use App\Models\Holiday;
use App\Models\WorkSchedule;
use App\Services\BusinessHoursCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HolidayShiftResolver
{
    private const MAX_SHIFT_DAYS = 30;

    public function __construct(private readonly BusinessHoursCalculator $calculator = new BusinessHoursCalculator) {}

    /**
     * @param  Collection<int, WorkSchedule>  $schedules
     * @param  Collection<int, Holiday>  $holidays
     */
    public function resolve(Carbon $from, Collection $schedules, Collection $holidays): ?Carbon
    {
        // F-85: flip SEKALI di sini, dipakai ulang tiap iterasi shift di bawah --
        // pola identik BusinessHoursCalculator::overlapMinutes()/countBusinessDays().
        $holidayDates = $holidays->map(fn (Holiday $h) => $h->date->toDateString())->flip();

        $candidate = $from->copy();

        for ($i = 0; $i <= self::MAX_SHIFT_DAYS; $i++) {
            if ($this->calculator->isBusinessDay($candidate, $schedules, $holidayDates)) {
                return $candidate->copy();
            }

            $candidate->addDay();
        }

        return null;
    }
}
