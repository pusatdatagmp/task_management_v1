<?php

/**
 * ==========================================================
 * MODUL       : BusinessHoursCalculator
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Hitung realisasi kerja (menit) yang JATUH DI DALAM jendela kerja
 *               (F-57) — jam di luar jendela (malam, weekend, libur) tidak dihitung
 *               sama sekali. Ini rumus yang dipakai sebelum actual_minutes DIBEKUKAN
 *               permanen saat approve (F-39), jadi harus benar sebelum data nyata masuk.
 *               v1.0.1 (F-59/F-118) — juga menghitung JUMLAH HARI KERJA antara dua
 *               tanggal (dipakai DashboardService untuk sebar beban), REUSE klasifikasi
 *               hari-kerja/libur yang sama dengan overlapMinutes() lewat isBusinessDay()
 *               (F-72/F-76 — satu sumber kebenaran "hari ini hari kerja atau bukan",
 *               bukan kalkulator kembar).
 * DIPANGGIL   : Task::calculateActualMinutes(), DashboardService::workloadSpread()
 * MEMANGGIL   : WorkSchedule (days_of_week, start_time, end_time — 02-DATA-MODEL §3.2),
 *               Holiday (date — 02-DATA-MODEL §3.3)
 * DATA MASUK  : started_at/ended_at satu baris task_time_segments (02-DATA-MODEL §3.10)
 *               ATAU rentang tanggal (today→due_date) + SELURUH versi WorkSchedule
 *               organisasi + SELURUH holiday organisasi (dimuat sekali oleh caller,
 *               F-85 — bukan query per hari)
 * DATA KELUAR : Menit overlap (int) ATAU jumlah hari kerja (int)
 * RISIKO      : SUMBER : F-57. Kalau logic ini salah, actual_minutes yang di-freeze
 *               (F-39) SALAH PERMANEN dan tidak bisa dihitung ulang — angka penilaian
 *               tim ikut salah selamanya. isBusinessDay() dipakai KEDUA fungsi —
 *               kalau salah, realisasi (F-57) DAN beban (F-118) sama-sama salah.
 * ==========================================================
 */

namespace App\Services;

use App\Models\Holiday;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class BusinessHoursCalculator
{
    /**
     * BATAS AMAN: segmen lebih dari 365 hari dianggap data korup (mis. ended_at
     * salah input, atau bug lain yang lolos F-48 "maks 1 segmen terbuka"). Daripada
     * diam-diam menghasilkan angka besar yang salah, sistem berhenti dan lapor.
     */
    private const MAX_SPAN_DAYS = 365;

    /**
     * KONTRAK: hitung menit overlap satu segmen kerja terhadap jendela kerja mingguan,
     * RESOLVE PER HARI dari $schedules (F-66 matang). Dipanggil sekali per baris
     * task_time_segments, hasilnya di-Σ oleh caller (Task::calculateActualMinutes())
     * untuk dapat total realisasi task.
     *
     * @param  Carbon  $start  started_at segmen.
     * @param  Carbon|null  $end  ended_at segmen. NULL = segmen masih berjalan (F-38 —
     *                            counter tampak jalan di UI, tapi di backend cukup
     *                            hitung sampai batas bawah ini).
     * @param  Collection<int, WorkSchedule>  $schedules  SELURUH versi WorkSchedule milik
     *                                                    organisasi (F-40, tidak difilter tanggal). F-66
     *                                                    matang: method ini sendiri yang resolve versi mana
     *                                                    berlaku PER HARI iterasi (effective_from <= hari
     *                                                    itu, versi terbaru) — bukan 1 versi untuk seluruh
     *                                                    segmen seperti v0.5. CALLER wajib memuat koleksi
     *                                                    ini SEKALI per panggilan (bukan query di sini) —
     *                                                    F-85, benih N+1 kalau di-query per hari.
     * @param  Collection<int, Holiday>  $holidays  SELURUH holiday organisasi yang
     *                                              overlap rentang tanggal segmen (F-43). Sama
     *                                              seperti $schedules — dimuat SEKALI oleh caller.
     * @return int menit overlap. 0 kalau tidak ada overlap sama sekali.
     *
     * @throws RuntimeException kalau rentang segmen > 365 hari (data korup).
     */
    public function overlapMinutes(Carbon $start, ?Carbon $end, Collection $schedules, Collection $holidays): int
    {
        // SUMBER: 02-DATA-MODEL §3.10 — "ended_at NULL -> hitung sampai min(now, end_time
        // hari ini)". Segmen yang masih berjalan di-cap ke SEKARANG atau penutupan jendela
        // HARI INI (mana yang lebih dulu) — supaya realisasi tidak dihitung lewat jam
        // pulang kalau baris ini dipanggil sebelum segmen ditutup. F-66: penutupan hari
        // ini memakai config yang berlaku HARI INI, bukan config saat started_at.
        if ($end === null) {
            $todaySchedule = $this->resolveScheduleForDay(Carbon::today(), $schedules);

            if ($todaySchedule === null) {
                // Tidak ada config berlaku hari ini -> tidak bisa menentukan penutupan
                // jendela. 0, bukan ditebak (F-42/F-40, sama seperti hari tanpa schedule).
                return 0;
            }

            $todayClose = Carbon::today()->setTimeFromTimeString((string) $todaySchedule->end_time);
            $now = Carbon::now();
            $end = $now->lessThan($todayClose) ? $now : $todayClose;
        }

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $cursor = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        // GUARD: cegah loop nyaris tak berhingga akibat data korup (mis. ended_at
        // ketuker tahun). JANGAN diam-diam kembalikan angka — lempar exception supaya
        // ketahuan sebelum ikut membekukan actual_minutes yang salah (F-39).
        if ($cursor->diffInDays($lastDay) > self::MAX_SPAN_DAYS) {
            $maxDays = self::MAX_SPAN_DAYS;

            throw new RuntimeException(
                "Segmen melebihi {$maxDays} hari (started_at={$start->toDateTimeString()}, ".
                "ended_at={$end->toDateTimeString()}) — kemungkinan data korup, tidak dihitung."
            );
        }

        // F-43: holiday di-index per tanggal (Y-m-d) SEKALI sebelum loop, bukan di-query
        // atau di-filter ulang tiap iterasi hari — pola sama dengan $schedules (F-85).
        $holidayDates = $holidays->map(fn (Holiday $h) => $h->date->toDateString())->flip();

        $totalMinutes = 0;

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            // F-43/F-118: klasifikasi "hari kerja atau bukan" (libur + days_of_week)
            // di-REUSE dari isBusinessDay() — SATU sumber kebenaran dipakai juga oleh
            // countBusinessDays() (F-72/F-76, cegah kalkulator kembar).
            if ($this->isBusinessDay($cursor, $schedules, $holidayDates)) {
                // F-66: schedule di-resolve PER HARI ($cursor), BUKAN 1 versi untuk
                // seluruh segmen — work_schedules versioned (F-40), segmen bisa
                // menyeberang perubahan config di tengah jalan.
                $schedule = $this->resolveScheduleForDay($cursor, $schedules);
                $windowStart = $cursor->copy()->setTimeFromTimeString((string) $schedule->start_time);
                $windowEnd = $cursor->copy()->setTimeFromTimeString((string) $schedule->end_time);

                $overlapStart = $start->greaterThan($windowStart) ? $start : $windowStart;
                $overlapEnd = $end->lessThan($windowEnd) ? $end : $windowEnd;

                if ($overlapEnd->greaterThan($overlapStart)) {
                    $totalMinutes += $overlapStart->diffInMinutes($overlapEnd);
                }
            }

            $cursor->addDay();
        }

        return $totalMinutes;
    }

    /**
     * KONTRAK: F-59/F-118 — jumlah HARI KERJA dari $from s/d $to INKLUSIF (dipakai
     * DashboardService untuk menyebar beban ke hari kerja sampai tenggat), REUSE
     * isBusinessDay() (libur F-43 + days_of_week F-44 + versi schedule F-40/F-66) —
     * SATU sumber sama dengan overlapMinutes(), bukan kalkulator baru (F-72/F-76).
     * $schedules/$holidays WAJIB dimuat SEKALI oleh caller (F-85, pola sama overlapMinutes()).
     *
     * A3: kalau $to sudah lewat ($from) — tidak bisa "mundur" menghitung hari yang
     * sudah lampau — kembalikan 0. Pemanggil (DashboardService) yang memutuskan
     * fallback (F-118: overdue/tenggat-hari-ini -> seluruh porsi jatuh ke hari ini),
     * BUKAN tanggung jawab kalkulator generik ini untuk menebak kebijakan bisnis itu.
     *
     * @param  Collection<int, WorkSchedule>  $schedules
     * @param  Collection<int, Holiday>  $holidays
     */
    public function countBusinessDays(Carbon $from, Carbon $to, Collection $schedules, Collection $holidays): int
    {
        $cursor = $from->copy()->startOfDay();
        $lastDay = $to->copy()->startOfDay();

        if ($lastDay->lessThan($cursor)) {
            return 0;
        }

        $holidayDates = $holidays->map(fn (Holiday $h) => $h->date->toDateString())->flip();

        $businessDays = 0;

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            if ($this->isBusinessDay($cursor, $schedules, $holidayDates)) {
                $businessDays++;
            }

            $cursor->addDay();
        }

        return $businessDays;
    }

    /**
     * KONTRAK: SATU tempat yang menentukan "$day ini hari kerja atau bukan" —
     * libur (F-43) DULU (menang atas schedule, libur yang jatuh di hari kerja
     * tetap bukan hari kerja), baru days_of_week schedule yang berlaku PER HARI
     * itu (F-66, F-44 — bukan nama hari hardcode). $schedule null (tidak ada
     * config berlaku hari itu) = bukan hari kerja (F-42/F-40).
     *
     * @param  Collection<int, WorkSchedule>  $schedules
     * @param  Collection<int, int>  $holidayDates  keyed by 'Y-m-d' (hasil flip(), F-85 — sudah di-index SEKALI oleh caller)
     */
    private function isBusinessDay(Carbon $day, Collection $schedules, Collection $holidayDates): bool
    {
        if ($holidayDates->has($day->toDateString())) {
            return false;
        }

        $schedule = $this->resolveScheduleForDay($day, $schedules);

        return $schedule !== null && in_array($day->isoWeekday(), $schedule->days_of_week, true);
    }

    /**
     * KONTRAK: dari koleksi SELURUH versi WorkSchedule organisasi, pilih versi yang
     * berlaku pada $day. SUMBER: 02-DATA-MODEL §3.2 — "Config aktif = baris dengan
     * effective_from <= today, urut desc, ambil 1" — logic identik WorkSchedule::active(),
     * tapi beroperasi DI MEMORI atas koleksi yang sudah dimuat caller (F-85), bukan query
     * DB per panggilan. F-66: dipanggil sekali per hari iterasi overlapMinutes().
     *
     * @param  Collection<int, WorkSchedule>  $schedules
     */
    private function resolveScheduleForDay(Carbon $day, Collection $schedules): ?WorkSchedule
    {
        return $schedules
            ->filter(fn (WorkSchedule $s) => $s->effective_from->lessThanOrEqualTo($day))
            ->sortByDesc('effective_from')
            ->first();
    }
}
