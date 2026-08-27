<?php

/**
 * ==========================================================
 * MODUL       : SummaryCardsChartDemoSeeder
 * KLASIFIKASI : DATA (seeder, BUKAN wajib jalan otomatis)
 * TUJUAN      : Permintaan Boss -- data uji manual untuk 6 kartu ringkas
 *               Command Center (Beban Harian/To Do/In Progress/Review/
 *               Selesai/Overdue) supaya line chart bergradasi di tiap kartu
 *               (SummaryStatCard/MiniLineGauge, command-center.tsx) kelihatan
 *               VARIATIF di browser -- bukan semua 0/rata (chart datar tidak
 *               ada gunanya buat diperiksa Boss). TIDAK didaftarkan di
 *               DatabaseSeeder::run() -- jalankan manual saat butuh:
 *                   php artisan db:seed --class=SummaryCardsChartDemoSeeder
 *               Hapus lagi lewat project "Demo Summary Cards ..." (folder
 *               proyek terpisah, gampang dihapus) kalau sudah selesai dicek.
 * DIPANGGIL   : php artisan db:seed --class=... (manual, Boss)
 * MEMANGGIL   : Organization::first() (single-tenant, F-5), User (is_active),
 *               WorkSchedule::active() (kapasitas & jendela jam kerja),
 *               TaskStatus::seedDefaults() (4 status default proyek),
 *               DashboardController::summaryCards()/overdueCount() (KONTRAK
 *               dibaca, TIDAK dipanggil langsung -- seeder cuma bikin data
 *               mentah, angka kartu TETAP murni dihitung controller).
 * DATA MASUK  : -
 * DATA KELUAR : 1 project baru ("Demo Summary Cards ...") + task tersebar di
 *               4 status (jumlah beda per status, lihat STATUS_COUNTS) +
 *               beberapa task overdue (due_date lewat, belum selesai) +
 *               task_time_segments HARI INI untuk kartu "Beban Harian".
 * RISIKO      : SUMBER -- 'todo'/'in_progress'/'review'/'selesai' di
 *               DashboardController::summaryCards() itu COUNT GLOBAL org
 *               (nol filter tanggal/proyek, lihat progressDistribution(null,
 *               null, ...)) -- task demo ini IKUT KETAMBAH ke angka real yang
 *               sudah ada di database, BUKAN pengganti/reset. Kalau organisasi
 *               sudah py task banyak, efek visual seeder ini bisa kecil
 *               (proporsi tertutup data asli) -- itu bukan bug seeder, murni
 *               konsekuensi COUNT GLOBAL yang sudah begitu sejak awal.
 * ==========================================================
 */

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SummaryCardsChartDemoSeeder extends Seeder
{
    /**
     * Jumlah task per status -- SENGAJA TIDAK SERAGAM supaya proporsi
     * (summaryPct(), command-center.tsx) tiap kartu beda tinggi garisnya di
     * layar. 'overdue' TERPISAH (bukan status tersendiri, F-44 -- flag due_date
     * lewat + belum selesai, lihat createOverdue()).
     */
    private const STATUS_COUNTS = [
        'todo' => 7,
        'in_progress' => 4,
        'review' => 2,
        'selesai' => 11,
    ];

    private const OVERDUE_COUNT = 5;

    /** Menit realisasi HARI INI per user (bergantian, lihat createRealisasi()) -- basis kartu "Beban Harian". */
    private const REALISASI_PROFILES = [180, 90, 300];

    public function run(): void
    {
        // F-5: single-tenant sampai v3.0 -- ambil organisasi SATU-SATUNYA yang
        // ada, pola sama MemberCategoryChartDemoSeeder/CalendarWorkloadDemoSeeder.
        $organization = Organization::firstOrFail();

        $activeUsers = User::where('organization_id', $organization->id)->where('is_active', true)->get();

        if ($activeUsers->isEmpty()) {
            $this->command?->error('Nol user aktif di organisasi -- seeder ini butuh minimal 1 user aktif untuk jadi assignee task demo.');

            return;
        }

        $today = Carbon::now();
        $owner = $activeUsers->first();

        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Demo Summary Cards '.$today->format('Y-m-d H:i'),
            'owner_id' => $owner->id,
        ]);
        $project->members()->sync($activeUsers->pluck('id')->all());
        TaskStatus::seedDefaults($project);

        $todoStatus = TaskStatus::where('project_id', $project->id)->where('is_work_state', false)->where('is_review', false)->where('is_completed', false)->firstOrFail();
        $inProgressStatus = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
        $reviewStatus = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
        $doneStatus = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

        $this->createTasksInStatus($project, $organization, $owner, $activeUsers, $todoStatus, self::STATUS_COUNTS['todo'], 'To Do', $today->copy()->addDays(2));
        $this->createTasksInStatus($project, $organization, $owner, $activeUsers, $inProgressStatus, self::STATUS_COUNTS['in_progress'], 'In Progress', $today->copy()->addDays(3));
        $this->createTasksInStatus($project, $organization, $owner, $activeUsers, $reviewStatus, self::STATUS_COUNTS['review'], 'Review', $today->copy()->addDay());
        $this->createDoneTasks($project, $organization, $owner, $activeUsers, $todoStatus, $doneStatus, self::STATUS_COUNTS['selesai']);
        $this->createOverdueTasks($project, $organization, $owner, $activeUsers, $todoStatus, self::OVERDUE_COUNT);

        $schedule = WorkSchedule::active($organization->id, $today);
        if ($schedule === null) {
            $this->command?->warn('Organisasi ini belum punya Jam Kerja (WorkSchedule) aktif -- kartu "Beban Harian" akan tampil 0/0 (kapasitas 0). Task 4 kartu lain (To Do/In Progress/Review/Selesai/Overdue) TETAP valid.');
        } else {
            $isHoliday = Holiday::where('organization_id', $organization->id)->whereDate('date', $today)->exists();
            $isBusinessDay = in_array($today->isoWeekday(), $schedule->days_of_week, true) && ! $isHoliday;
            if (! $isBusinessDay) {
                $this->command?->warn('Peringatan: hari ini ('.$today->toDateString().') BUKAN hari kerja (atau libur) -- kartu "Beban Harian" tidak akan berkurang sesuai profil realisasi di bawah (tetap tampil penuh = kapasitas).');
            }

            $windowStart = $today->copy()->setTimeFromTimeString((string) $schedule->start_time);
            $windowEnd = $today->copy()->setTimeFromTimeString((string) $schedule->end_time);

            foreach ($activeUsers as $i => $user) {
                $minutes = self::REALISASI_PROFILES[$i % count(self::REALISASI_PROFILES)];
                $this->createRealisasi($project, $todoStatus, $organization, $user, $owner, $minutes, $windowStart, $windowEnd);
            }
        }

        $this->command?->info("Demo summary cards dibuat di project '{$project->name}':");
        $this->command?->info('- To Do: '.self::STATUS_COUNTS['todo'].', In Progress: '.self::STATUS_COUNTS['in_progress'].', Review: '.self::STATUS_COUNTS['review'].', Selesai: '.self::STATUS_COUNTS['selesai'].', Overdue: '.self::OVERDUE_COUNT);
        $this->command?->info('Cek: 6 kartu ringkas + line chart Command Center (buka /dashboard/overview).');
    }

    /**
     * KONTRAK: $count task dibuat LANGSUNG di $status (task_type='tentative',
     * due_date MASA DEPAN supaya nol tercampur ke hitungan overdue). Sumber
     * kartu To Do/In Progress/Review (DashboardController::progressDistribution()).
     */
    private function createTasksInStatus(Project $project, Organization $organization, User $owner, Collection $users, TaskStatus $status, int $count, string $label, Carbon $dueDate): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $task = Task::create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'task_status_id' => $status->id,
                'title' => "Demo {$label} #{$i}",
                'task_type' => 'tentative',
                'estimated_minutes' => 60,
                'due_date' => $dueDate,
                'created_by' => $owner->id,
            ]);
            $task->assignees()->sync([$users[($i - 1) % $users->count()]->id]);
        }
    }

    /**
     * KONTRAK: task dibuat TODO dulu, BARU dipindah ke DONE -- memicu
     * TaskObserver::updating() (F-21: completed_at = now()), pola SAMA
     * MemberCategoryChartDemoSeeder::createAchievement() supaya completed_at
     * konsisten terisi (bukan langsung create dengan status selesai, yang bisa
     * bikin completed_at null di widget lain yang membacanya).
     */
    private function createDoneTasks(Project $project, Organization $organization, User $owner, Collection $users, TaskStatus $todoStatus, TaskStatus $doneStatus, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $task = Task::create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'task_status_id' => $todoStatus->id,
                'title' => "Demo Selesai #{$i}",
                'task_type' => 'tentative',
                'estimated_minutes' => 60,
                'due_date' => Carbon::now()->subDays(1),
                'created_by' => $owner->id,
            ]);
            $task->assignees()->sync([$users[($i - 1) % $users->count()]->id]);
            $task->update(['task_status_id' => $doneStatus->id]);
        }
    }

    /**
     * KONTRAK: due_date SUDAH LEWAT + status BELUM selesai (TODO) -- pola
     * IDENTIK definisi overdue DashboardController::overdueCount() (F-44:
     * due_date < now() DAN is_completed=false). Task ini SEKALIGUS ikut
     * terhitung ke kartu "To Do" (status-nya TODO) -- itu memang perilaku
     * asli aplikasi (satu task bisa overdue DAN todo bersamaan), bukan bug.
     */
    private function createOverdueTasks(Project $project, Organization $organization, User $owner, Collection $users, TaskStatus $todoStatus, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $task = Task::create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'task_status_id' => $todoStatus->id,
                'title' => "Demo Overdue #{$i}",
                'task_type' => 'tentative',
                'estimated_minutes' => 60,
                'due_date' => Carbon::now()->subDays($i),
                'created_by' => $owner->id,
            ]);
            $task->assignees()->sync([$users[($i - 1) % $users->count()]->id]);
        }
    }

    /**
     * KONTRAK: simulasi "sudah kerja X menit hari ini" -- task_time_segments
     * DITUTUP (ended_at terisi) DI DALAM jendela jam kerja hari ini, supaya
     * BusinessHoursCalculator::overlapMinutes() (F-57) menghitungnya PENUH.
     * Pola SALIN PERSIS MemberCategoryChartDemoSeeder::createRealisasi() --
     * SATU sumber realisasi yang sudah diverifikasi Boss, nol rumus baru.
     */
    private function createRealisasi(Project $project, TaskStatus $todoStatus, Organization $organization, User $user, User $owner, int $minutes, Carbon $windowStart, Carbon $windowEnd): void
    {
        $task = Task::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'task_status_id' => $todoStatus->id,
            'title' => "Demo Realisasi Beban Harian ({$user->name})",
            'task_type' => 'tentative',
            'estimated_minutes' => $minutes,
            'due_date' => $windowStart->copy()->addDays(3),
            'created_by' => $owner->id,
        ]);
        $task->assignees()->sync([$user->id]);

        $started = $windowStart->copy();
        // GUARD: cap ke jendela jam kerja -- kapasitas harian bisa lebih kecil
        // dari $minutes kalau schedule dipendekkan Boss, jangan sampai segmen
        // "kerja" nongkrong DI LUAR jendela (overlap jadi 0, salah demo).
        $ended = $started->copy()->addMinutes($minutes)->min($windowEnd);

        $task->timeSegments()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'started_at' => $started,
            'ended_at' => $ended,
        ]);
    }
}
