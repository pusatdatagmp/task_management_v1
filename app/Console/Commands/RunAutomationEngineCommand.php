<?php

/**
 * ==========================================================
 * MODUL       : RunAutomationEngineCommand
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : CronTrigger + ManualTrigger (F-161 §8.1) Automation Engine v1.3 —
 *               evaluasi SEMUA task_templates aktif lewat Pipeline, tulis 1
 *               Decision per template ke automation_run_log. Command BARU,
 *               BERDAMPINGAN dengan `tasks:generate-recurring` (engine lama, F-160
 *               -- cutover ganti total baru di AE-3, BUKAN sekarang).
 * DIPANGGIL   : routes/console.php (Schedule::command, dailyAt 00:01 WIB),
 *               manual `php artisan automation:run` (ManualTrigger, sweep miss-run)
 * MEMANGGIL   : Pipeline::default(), AutomationContext (dibangun per-organisasi),
 *               AutomationRunLog
 * DATA MASUK  : task_templates.is_active=true (chunk F-85), WorkSchedule/Holiday/
 *               Task organisasi (preload SEKALI per organisasi per chunk)
 * DATA KELUAR : tasks baru (via GenerateTaskAction), automation_run_log (1 baris/template)
 * RISIKO      : SUMBER F-160 -- try/catch PER TEMPLATE, WAJIB: 1 template gagal
 *               TIDAK BOLEH menghentikan template lain dalam run yang sama. now_WIB
 *               (F-69) dihitung SEKALI di awal handle(), dipakai SELURUH template --
 *               run tengah malam yang berjalan lama tidak boleh "melompat hari" di
 *               tengah jalan.
 * ==========================================================
 */

namespace App\Console\Commands;

use App\Models\AutomationRunLog;
use App\Models\Holiday;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\WorkSchedule;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;
use App\Services\Automation\Pipeline;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class RunAutomationEngineCommand extends Command
{
    protected $signature = 'automation:run';

    protected $description = 'Evaluasi task_templates aktif lewat pipa Trigger->Guard->Strategy->Resolver->Action (F-151/158)';

    public function handle(): int
    {
        // F-69: WIB eksplisit, SEKALI untuk seluruh run -- JANGAN Carbon::now() UTC.
        $nowWib = Carbon::now('Asia/Jakarta');
        $pipeline = Pipeline::default();

        $counts = ['generate' => 0, 'shift' => 0, 'skip' => 0, 'error' => 0];

        // F-85: chunkById supaya organisasi dengan banyak template tidak memuat
        // semua ke memori sekaligus. checklistItems (F-123) + project dimuat
        // SEKALI per chunk -- tanpa ini GenerateTaskAction lazy-load per template,
        // meledak kalau Model::preventLazyLoading() aktif (non-produksi).
        TaskTemplate::where('is_active', true)
            ->with(['checklistItems', 'project'])
            ->chunkById(100, function (Collection $templates) use ($nowWib, $pipeline, &$counts) {
                foreach ($templates->groupBy('organization_id') as $organizationId => $orgTemplates) {
                    $ctx = $this->buildContext((int) $organizationId, $orgTemplates, $nowWib);

                    foreach ($orgTemplates as $template) {
                        // F-160: isolasi PER TEMPLATE -- 1 gagal, Decision ERROR,
                        // template lain dalam chunk/run yang sama TETAP diproses.
                        try {
                            $decision = $pipeline->run($template, $ctx);
                        } catch (Throwable $e) {
                            // Str::limit: automation_run_log.reason varchar(255) --
                            // QueryException::getMessage() menyertakan SQL PENUH,
                            // bisa jauh melebihi itu. Tanpa potong, INSERT log ini
                            // sendiri gagal (F-160 gagal total, bukan cuma 1 template).
                            $decision = Decision::error(Str::limit($e->getMessage(), 250, ''));
                        }

                        $this->logDecision((int) $organizationId, $template, $decision, $nowWib);
                        $counts[$decision->action] = ($counts[$decision->action] ?? 0) + 1;
                    }
                }
            });

        $this->info(sprintf(
            'Automation run selesai. Generate: %d. Shift: %d. Skip: %d. Error: %d.',
            $counts['generate'],
            $counts['shift'],
            $counts['skip'],
            $counts['error'],
        ));

        return self::SUCCESS;
    }

    /**
     * KONTRAK: preload SEKALI per organisasi (F-85) -- schedules/holidays (F-40/F-43),
     * task terakhir per template (Opsi B, CompletionBasedStrategy), dan jumlah
     * instance belum-selesai per template (QuotaGuard). Guard/Strategy TIDAK
     * boleh query DB sendiri -- semua data lewat AutomationContext ini.
     *
     * @param  Collection<int, TaskTemplate>  $templates  subset template organisasi ini DALAM chunk berjalan
     */
    private function buildContext(int $organizationId, Collection $templates, Carbon $nowWib): AutomationContext
    {
        $templateIds = $templates->pluck('id');

        $schedules = WorkSchedule::where('organization_id', $organizationId)->get();
        $holidays = Holiday::where('organization_id', $organizationId)->get();

        // Opsi B: task TERBARU (period_key terbesar) per template -- diurutkan
        // DESC dulu baru groupBy, supaya first() tiap grup = periode paling akhir.
        $latestTaskByTemplateId = Task::query()
            ->whereIn('task_template_id', $templateIds)
            ->whereNotNull('period_key')
            ->with('taskStatus')
            ->orderByDesc('period_key')
            ->get()
            ->groupBy('task_template_id')
            ->map(fn (Collection $group) => $group->first())
            ->all();

        $activeInstanceCounts = Task::query()
            ->whereIn('task_template_id', $templateIds)
            ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false))
            ->selectRaw('task_template_id, count(*) as cnt')
            ->groupBy('task_template_id')
            ->pluck('cnt', 'task_template_id')
            ->all();

        return new AutomationContext($nowWib, $schedules, $holidays, $latestTaskByTemplateId, $activeInstanceCounts);
    }

    /**
     * KONTRAK: tulis Decision APA PUN hasilnya (generate/skip/shift/error) --
     * F-159 poin 3, log TIDAK BOLEH bolong satu evaluasi pun, beda semangat
     * dengan F-51 (activity log) tapi tujuan sama: observability lengkap.
     */
    private function logDecision(int $organizationId, TaskTemplate $template, Decision $decision, Carbon $nowWib): void
    {
        AutomationRunLog::create([
            'organization_id' => $organizationId,
            'task_template_id' => $template->id,
            'run_at' => $nowWib,
            'action' => $decision->action,
            'reason' => $decision->reason,
            'target_date' => $decision->targetDate,
            'delta_days' => $decision->deltaDays,
            'meta' => $decision->meta,
        ]);
    }
}
