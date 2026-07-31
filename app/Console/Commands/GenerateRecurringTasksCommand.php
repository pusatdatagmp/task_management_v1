<?php

/**
 * ==========================================================
 * MODUL       : GenerateRecurringTasksCommand
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Engine recurring (F-46) — melahirkan instance `tasks` dari
 *               `task_templates` aktif, HARI INI SAJA (F-100, tidak backfill).
 *               Dijadwalkan HARIAN (F-81 — cron recurring SAH, F-38 tetap
 *               melarang scheduler untuk COUNTER, beda subjek sama sekali).
 * DIPANGGIL   : routes/console.php (Schedule::command), manual (php artisan tasks:generate-recurring)
 * MEMANGGIL   : TaskTemplate, WorkSchedule, Holiday, TaskStatus, Task, ActivityLog,
 *               TaskTemplateChecklistItem (F-123, copy-on-generate)
 * DATA MASUK  : task_templates.is_active=true + recurrence_config + last_generated_date,
 *               work_schedules & holidays organisasi (F-66/F-43, dimuat SEKALI per
 *               organisasi -- F-85, bukan query per template), checklistItems
 *               template (F-123, dimuat SEKALI via eager load, sama alasannya)
 * DATA KELUAR : tasks baru (task_template_id terisi), task_checklist_items instance
 *               (disalin dari blueprint, is_done=false, F-123/F-127), task_templates.
 *               last_generated_date, activity_logs (via TaskObserver/TaskUserObserver
 *               otomatis + 1 event manual untuk assignee yang di-drop, F-86),
 *               notifications (assigned)
 * RISIKO      : SUMBER : urutan clamp (F-101) -> shift/skip libur (F-102) -> cek
 *               "hari ini" (F-100) -> idempotency (F-61) WAJIB PERSIS begini,
 *               dibalik = generate tanggal yang salah atau task duplikat. daily
 *               TIDAK PERNAH digeser (F-102 — geser daily = backfill terselubung,
 *               melanggar F-100, sudah diputuskan Boss, JANGAN "diperbaiki").
 *               weekly/monthly WAJIB dicek di 2 periode (periode ini + periode
 *               sebelumnya) -- shift libur bisa melompati batas minggu/bulan (mis.
 *               28 Feb Sabtu -> geser ke 2 Maret Senin); cek 1 periode saja membuat
 *               occurrence yang lahir dari periode sebelumnya tidak pernah ketemu
 *               begitu tanggal berjalan menyeberang ke periode baru.
 * ==========================================================
 */

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Holiday;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateRecurringTasksCommand extends Command
{
    protected $signature = 'tasks:generate-recurring';

    protected $description = 'Generate instance task dari task_templates aktif untuk hari ini (F-46/F-100/F-101/F-102)';

    /**
     * GUARD: batas maksimum hari geser mencari hari kerja berikutnya (F-102).
     * Kalau tidak ketemu dalam rentang ini, work_schedules/holidays organisasi
     * kemungkinan korup (mis. tidak ada satu pun hari kerja terdaftar) -- berhenti
     * cari daripada diam-diam menggeser tak berhingga (pola guard sama seperti
     * BusinessHoursCalculator::MAX_SPAN_DAYS).
     */
    private const MAX_SHIFT_DAYS = 30;

    public function handle(): int
    {
        $today = Carbon::today();

        // F-85: dikelompokkan per organisasi supaya work_schedules/holidays dimuat
        // SEKALI per organisasi (bukan per template) -- organisasi bisa punya
        // puluhan template aktif sekaligus.
        // F-85/F-123: checklistItems dimuat SEKALI di sini (eager load) — tanpa ini,
        // generateInstance() lazy-load per template saat menyalin checklist ke
        // instance, meledak kalau Model::preventLazyLoading() aktif (non-produksi).
        $templatesByOrganization = TaskTemplate::where('is_active', true)->with('checklistItems')->get()->groupBy('organization_id');

        $generated = 0;
        $skipped = 0;

        foreach ($templatesByOrganization as $organizationId => $templates) {
            $schedules = WorkSchedule::where('organization_id', $organizationId)->get();
            $holidays = Holiday::where('organization_id', $organizationId)->get();

            foreach ($templates as $template) {
                $effectiveDate = $this->resolveEffectiveDate($template, $today, $schedules, $holidays);

                // F-100: cuma generate kalau tanggal efektif == hari ini. F-61:
                // last_generated_date sudah = tanggal efektif ini -> sudah pernah
                // digenerate, skip (cron 2x hari sama tidak menggandakan).
                if ($effectiveDate === null || $template->last_generated_date?->isSameDay($effectiveDate)) {
                    $skipped++;

                    continue;
                }

                $this->generateInstance($template, $effectiveDate, $schedules);
                $generated++;
            }
        }

        $totalTemplates = $templatesByOrganization->flatten()->count();
        $this->info("Template aktif diperiksa: {$totalTemplates}. Digenerate: {$generated}. Skip: {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * KONTRAK: urutan operasi PERSIS 1-3 dari 02-DATA-MODEL/prompt Hari-4 —
     * (1) tanggal natural, (2) clamp bulan (F-101, dilebur ke dalam natural
     * monthly), (3) shift/skip libur (F-102). Return NULL kalau tidak ada
     * occurrence yang jatuh di $today untuk template ini.
     */
    private function resolveEffectiveDate(TaskTemplate $template, Carbon $today, Collection $schedules, Collection $holidays): ?Carbon
    {
        if ($template->task_type === 'daily') {
            // F-102: daily TIDAK PERNAH digeser. Libur/weekend -> SKIP TOTAL,
            // titik. Tumpukan yang terasa "hilang" muncul lagi otomatis lewat
            // instance kemarin yang belum selesai (F-60 carryover).
            return $this->isWorkday($today, $schedules, $holidays) ? $today->copy() : null;
        }

        $dayOfWeek = (int) ($template->recurrence_config['day_of_week'] ?? 0);
        $dayOfMonth = (int) ($template->recurrence_config['day_of_month'] ?? 0);

        // F-102: shift boleh melompati batas periode, jadi occurrence hari ini
        // bisa berasal dari periode SEKARANG (natural date jatuh di hari ini
        // sendiri, tidak digeser) ATAU periode SEBELUMNYA (natural date di
        // periode itu digeser maju sampai menyentuh hari ini). Dicek dua-duanya.
        $periodAnchors = match ($template->task_type) {
            'weekly' => [$today->copy(), $today->copy()->subWeek()],
            'monthly' => [$today->copy(), $today->copy()->subMonthNoOverflow()],
            default => [],
        };

        foreach ($periodAnchors as $anchor) {
            $natural = $template->task_type === 'weekly'
                ? $this->naturalWeeklyDate($anchor, $dayOfWeek)
                : $this->naturalMonthlyDate($anchor, $dayOfMonth); // F-101 clamp di dalam sini

            $effective = $this->shiftToWorkday($natural, $schedules, $holidays);

            if ($effective !== null && $effective->isSameDay($today)) {
                return $effective;
            }
        }

        return null;
    }

    private function naturalWeeklyDate(Carbon $periodAnchor, int $dayOfWeek): Carbon
    {
        // ISO weekday 1=Senin..7=Minggu (F-44, cocok Carbon::isoWeekday()).
        return $periodAnchor->copy()->startOfWeek(Carbon::MONDAY)->addDays($dayOfWeek - 1);
    }

    /**
     * BUSINESS RULE F-101: day_of_month > jumlah hari bulan ini -> clamp ke hari
     * TERAKHIR bulan itu (31 Jan -> 28/29 Feb). Clamp dilakukan DI SINI, sebelum
     * shiftToWorkday() (F-102) — urutan clamp-dulu-baru-shift wajib persis begini.
     */
    private function naturalMonthlyDate(Carbon $periodAnchor, int $dayOfMonth): Carbon
    {
        $day = min($dayOfMonth, $periodAnchor->daysInMonth);

        return $periodAnchor->copy()->startOfMonth()->addDays($day - 1);
    }

    /**
     * KONTRAK: kalau $natural sudah hari kerja, kembalikan apa adanya. Kalau
     * tidak (libur/weekend), geser maju hari-per-hari sampai ketemu hari kerja
     * (F-102, HANYA dipanggil untuk weekly/monthly — daily tidak pernah lewat sini).
     */
    private function shiftToWorkday(Carbon $natural, Collection $schedules, Collection $holidays): ?Carbon
    {
        if ($this->isWorkday($natural, $schedules, $holidays)) {
            return $natural->copy();
        }

        $candidate = $natural->copy();

        for ($i = 0; $i < self::MAX_SHIFT_DAYS; $i++) {
            $candidate->addDay();

            if ($this->isWorkday($candidate, $schedules, $holidays)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * KONTRAK: hari kerja = BUKAN tanggal holiday (F-43) DAN isoWeekday()-nya ada
     * di days_of_week WorkSchedule yang berlaku pada tanggal itu (F-40 versioned,
     * F-44 — flag/angka, bukan nama hari hardcode). $schedule null (tidak ada
     * config berlaku) -> dianggap BUKAN hari kerja, bukan ditebak.
     */
    private function isWorkday(Carbon $date, Collection $schedules, Collection $holidays): bool
    {
        $isHoliday = $holidays->contains(fn (Holiday $h) => $h->date->isSameDay($date));

        if ($isHoliday) {
            return false;
        }

        $schedule = $schedules
            ->filter(fn (WorkSchedule $s) => $s->effective_from->lessThanOrEqualTo($date))
            ->sortByDesc('effective_from')
            ->first();

        return $schedule !== null && in_array($date->isoWeekday(), $schedule->days_of_week, true);
    }

    /**
     * KONTRAK: lahirkan 1 instance task dari template, atomic dengan update
     * last_generated_date (F-61 — kalau task gagal dibuat, guard idempotency
     * TIDAK ikut ter-update, supaya percobaan berikutnya masih dianggap belum
     * pernah generate untuk tanggal efektif ini).
     */
    private function generateInstance(TaskTemplate $template, Carbon $effectiveDate, Collection $schedules): void
    {
        DB::transaction(function () use ($template, $effectiveDate, $schedules) {
            $project = $template->project;

            // BUSINESS RULE F-44/F-45/D7: status task baru = position TERKECIL
            // project ini — pola identik TaskController::store().
            $statusId = TaskStatus::where('project_id', $template->project_id)->orderBy('position')->value('id');

            $schedule = $schedules
                ->filter(fn (WorkSchedule $s) => $s->effective_from->lessThanOrEqualTo($effectiveDate))
                ->sortByDesc('effective_from')
                ->first();

            // SUMBER: 03-BUSINESS-FLOW §3 — due_date = tanggal efektif pada jam
            // end_time work_schedule. Tidak ada config berlaku (organisasi baru
            // tanpa work_schedule) -> akhir hari, bukan 00:00 (00:00 bisa
            // disalahartikan "sudah lewat" begitu tanggalnya tiba).
            $dueDate = $schedule
                ? $effectiveDate->copy()->setTimeFromTimeString((string) $schedule->end_time)
                : $effectiveDate->copy()->endOfDay();

            // F-86: default_assignees divalidasi ULANG sebagai member project SAAT
            // INI (member bisa berubah sejak template dibuat) — assignee yang
            // sudah bukan member DI-DROP, bukan tetap di-attach (state mustahil).
            $memberIds = $project->members()->pluck('users.id');
            $assigneeIds = collect($template->default_assignees)->intersect($memberIds)->values();
            $droppedIds = collect($template->default_assignees)->diff($memberIds)->values();

            $task = Task::create([
                'organization_id' => $template->organization_id,
                'project_id' => $template->project_id,
                'task_template_id' => $template->id,
                'task_status_id' => $statusId,
                'title' => $template->title,
                'description' => $template->description,
                'task_type' => $template->task_type,
                'priority' => $template->priority,
                'points' => $template->points,
                'estimated_minutes' => $template->estimated_minutes,
                'due_date' => $dueDate,
                // SUMBER: task_templates TIDAK punya kolom created_by (skema F-46
                // Hari-1 tidak menyimpannya). owner_id project dipakai sebagai
                // pemilik akuntabel instance recurring -- project.owner_id sudah
                // berperan "admin/reviewer project ini" sejak F-28.
                'created_by' => $project->owner_id,
            ]);

            // F-51: sync() (bukan query manual ke task_user) supaya
            // TaskUserObserver menangkap event 'assigned' + notifikasi trigger #1
            // untuk tiap assignee yang MASIH member.
            $task->assignees()->sync($assigneeIds->all());

            // F-123/F-127: salin blueprint checklist template -> instance baru,
            // is_done=false (FRESH tiap instance — instance kemarin yang sudah
            // dicentang TIDAK memengaruhi instance hari ini, F-46 instance independen).
            foreach ($template->checklistItems as $templateItem) {
                $task->checklistItems()->create([
                    'organization_id' => $template->organization_id,
                    'text' => $templateItem->text,
                    'position' => $templateItem->position,
                ]);
            }

            if ($droppedIds->isNotEmpty()) {
                // F-86/F-51: assignee ini TIDAK PERNAH di-attach (bukan
                // 'unassigned' -- tidak pernah jadi assignee di instance ini sama
                // sekali), jadi TaskUserObserver tidak menangkapnya. Dicatat manual
                // di sini supaya log tidak bolong (F-51) walau event-nya bukan
                // salah satu dari daftar wajib 02-DATA-MODEL §3.14 -- event baru
                // yang jujur menggambarkan kejadiannya, bukan dipaksa masuk
                // vocabulary lama yang tidak cocok.
                ActivityLog::create([
                    'organization_id' => $template->organization_id,
                    'user_id' => null, // pelaku = sistem (scheduler), bukan user login
                    'subject_type' => Task::class,
                    'subject_id' => $task->id,
                    'event' => 'recurring_assignee_dropped',
                    'properties' => [
                        'old' => null,
                        'new' => [
                            'task_template_id' => $template->id,
                            'dropped_user_ids' => $droppedIds->all(),
                        ],
                    ],
                ]);
            }

            $template->update(['last_generated_date' => $effectiveDate->toDateString()]);
        });
    }
}
