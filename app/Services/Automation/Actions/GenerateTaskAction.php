<?php

/**
 * ==========================================================
 * MODUL       : GenerateTaskAction
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Action terakhir pipa (§8.5) — lahirkan instance `tasks` dari
 *               template, ATOMIC dengan mutasi template.last_generated_date
 *               (F-152 catch-up-satu). Struktur field REUSE pola
 *               GenerateRecurringTasksCommand::generateInstance() (ground truth
 *               yang sudah disetujui Boss), ditambah period_key (F-159 poin 1).
 * DIPANGGIL   : Pipeline (HANYA setelah seluruh Guard Pass + Resolver hasilkan target_date)
 * MEMANGGIL   : Task, TaskStatus, TaskChecklistItem (via relasi), ActivityLog
 *               (assignee di-drop, F-86/F-51), TaskTemplate::update()
 * DATA MASUK  : TaskTemplate, target_date (hasil HolidayShiftResolver), now_WIB,
 *               schedules+holidays organisasi (F-85, revisi 2026-08-06 item 7 —
 *               due_offset_days butuh keduanya buat addBusinessDays())
 * DATA KELUAR : tasks baru + task_checklist_items instance + template.last_generated_date
 * RISIKO      : SUMBER F-61 idempotency -- cek unique (task_template_id, period_key)
 *               SEBELUM insert, DAN tangkap UniqueConstraintViolationException
 *               sebagai jaring kedua (race dua proses cron bersamaan) -- keduanya
 *               WAJIB ada, cek-lalu-insert saja rentan race, tangkap-exception saja
 *               boros satu percobaan insert gagal per skip normal.
 *               last_generated_date diisi now_WIB (evaluasi), BUKAN target_date
 *               (bisa lebih maju karena shift libur) -- F-152, dasar perhitungan
 *               TimeDeltaGuard run berikutnya.
 * ==========================================================
 */

namespace App\Services\Automation\Actions;

use App\Models\ActivityLog;
use App\Models\Holiday;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\WorkSchedule;
use App\Services\Automation\Decision;
use App\Services\BusinessHoursCalculator;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateTaskAction
{
    /**
     * @param  Collection<int, WorkSchedule>  $schedules  organisasi ini (F-85, dimuat command)
     * @param  Collection<int, Holiday>  $holidays  organisasi ini (F-85, dimuat command) —
     *                                              revisi 2026-08-06 item 7, dipakai due_offset_days.
     */
    public function execute(TaskTemplate $template, Carbon $targetDate, Carbon $nowWib, Collection $schedules, Collection $holidays): Decision
    {
        $periodKey = $targetDate->toDateString();

        if (Task::where('task_template_id', $template->id)->where('period_key', $periodKey)->exists()) {
            return Decision::skip('sudah-ada', $targetDate);
        }

        try {
            return DB::transaction(function () use ($template, $targetDate, $nowWib, $schedules, $holidays, $periodKey) {
                $project = $template->project;

                $statusId = TaskStatus::where('project_id', $template->project_id)->orderBy('position')->value('id');

                // Revisi 2026-08-06 item 7: due_offset_days terisi -> tenggat MAJU N
                // hari KERJA dari target_date (BusinessHoursCalculator::addBusinessDays(),
                // F-72/76 reuse — SATU sumber "hari kerja" sama dipakai HolidayShiftResolver).
                // null (default, template lama SEBELUM kolom ini ada) -> perilaku LAMA
                // TIDAK BERUBAH sama sekali (F-78): due_date = target_date, sama hari.
                $dueDateBase = $targetDate;
                if ($template->due_offset_days !== null) {
                    $shifted = (new BusinessHoursCalculator)->addBusinessDays($targetDate, $template->due_offset_days, $schedules, $holidays);
                    // GUARD: null (organisasi nol WorkSchedule hari kerja terdaftar,
                    // config korup) -> fallback diam-diam ke perilaku lama, BUKAN
                    // gagalkan seluruh generate task cuma karena deadline tak terhitung.
                    $dueDateBase = $shifted ?? $targetDate;
                }

                $schedule = $schedules
                    ->filter(fn ($s) => $s->effective_from->lessThanOrEqualTo($dueDateBase))
                    ->sortByDesc('effective_from')
                    ->first();

                // SUMBER: pola identik GenerateRecurringTasksCommand -- due_date =
                // $dueDateBase pada jam end_time work_schedule berlaku; tanpa config,
                // akhir hari (BUKAN 00:00, supaya tidak disalahartikan "sudah lewat").
                $dueDate = $schedule
                    ? $dueDateBase->copy()->setTimeFromTimeString((string) $schedule->end_time)
                    : $dueDateBase->copy()->endOfDay();

                $memberIds = $project->members()->pluck('users.id');
                $assigneeIds = collect($template->default_assignees)->intersect($memberIds)->values();
                $droppedIds = collect($template->default_assignees)->diff($memberIds)->values();

                $task = Task::create([
                    'organization_id' => $template->organization_id,
                    'project_id' => $template->project_id,
                    'task_template_id' => $template->id,
                    'period_key' => $periodKey,
                    'task_status_id' => $statusId,
                    'title' => $template->title,
                    'description' => $template->description,
                    'task_type' => $template->task_type,
                    'priority' => $template->priority,
                    'points' => $template->points,
                    'estimated_minutes' => $template->estimated_minutes,
                    'due_date' => $dueDate,
                    'created_by' => $project->owner_id,
                ]);

                $task->assignees()->sync($assigneeIds->all());

                // F-123/F-127: salin blueprint checklist -> instance baru, is_done=false.
                foreach ($template->checklistItems as $templateItem) {
                    $task->checklistItems()->create([
                        'organization_id' => $template->organization_id,
                        'text' => $templateItem->text,
                        'position' => $templateItem->position,
                    ]);
                }

                if ($droppedIds->isNotEmpty()) {
                    // F-51: assignee ini TIDAK PERNAH di-attach, jadi TaskUserObserver
                    // tidak menangkapnya -- dicatat manual supaya log tidak bolong.
                    ActivityLog::create([
                        'organization_id' => $template->organization_id,
                        'user_id' => null,
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

                $template->update(['last_generated_date' => $nowWib->toDateString()]);

                $meta = ['task_id' => $task->id];

                return $targetDate->isSameDay($nowWib)
                    ? Decision::generated($targetDate, $meta)
                    : Decision::shifted($targetDate, $meta);
            });
        } catch (UniqueConstraintViolationException) {
            return Decision::skip('sudah-ada-idempotency-race', $targetDate);
        }
    }
}
