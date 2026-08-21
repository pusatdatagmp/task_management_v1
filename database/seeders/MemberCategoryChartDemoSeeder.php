<?php

/**
 * ==========================================================
 * MODUL       : MemberCategoryChartDemoSeeder
 * KLASIFIKASI : DATA (seeder, BUKAN wajib jalan otomatis)
 * TUJUAN      : Permintaan Boss (2026-08-21) -- data uji manual untuk widget
 *               "Beban per Kategori" Command Center (stacked bar chart per
 *               member, DashboardController::memberCategoryChart()), supaya
 *               Boss bisa lihat LANGSUNG di browser variasi 3 kategori (menit)
 *               longgar/to-do/achievement per user. TIDAK didaftarkan di
 *               DatabaseSeeder::run() -- jalankan manual saat butuh:
 *                   php artisan db:seed --class=MemberCategoryChartDemoSeeder
 *               Hapus lagi lewat project "Demo Beban per Kategori" (folder
 *               proyek terpisah, gampang dihapus) kalau sudah selesai dicek.
 * DIPANGGIL   : php artisan db:seed --class=... (manual, Boss)
 * MEMANGGIL   : Organization::first() (single-tenant, F-5), User (is_active),
 *               WorkSchedule::active() (kapasitas & jendela jam kerja),
 *               DashboardController::memberCategoryChart() (KONTRAK dibaca,
 *               TIDAK dipanggil langsung -- seeder cuma bikin data mentah,
 *               hitungan longgar/todo/achievement tetap murni tugas controller).
 * DATA MASUK  : -
 * DATA KELUAR : 1 project baru ("Demo Beban per Kategori") + task/segment TODO
 *               HARI INI per active user, 4 profil bergantian (lihat PROFILES).
 * RISIKO      : SUMBER -- "longgar" (idle_real, DashboardService::forUsers())
 *               HANYA berkurang kalau task_time_segments TERTUTUP DI DALAM
 *               jendela jam kerja WorkSchedule HARI INI (F-57/BusinessHoursCalculator::
 *               overlapMinutes()) -- kalau HARI INI bukan hari kerja
 *               (days_of_week) atau libur (Holiday), segmen yang diseed di
 *               sini TETAP dibuat tapi overlap-nya 0, jadi kolom "longgar"
 *               tidak berkurang seperti dimaksud (kolom "todo"/"achievement"
 *               TIDAK kena guard ini -- keduanya murni due_date/completed_at
 *               = hari ini, bukan jendela jam kerja). Seeder MENDETEKSI kasus
 *               ini dan cuma MEMPERINGATKAN (bukan abort) -- profil todo/
 *               achievement tetap valid untuk didemokan.
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

class MemberCategoryChartDemoSeeder extends Seeder
{
    /**
     * 4 profil bergantian per user (index % 4) -- kombinasi realisasi (menit
     * kerja HARI INI, mengurangi "longgar")/todo (menit due HARI INI)/
     * achievement (menit task selesai HARI INI) SENGAJA beda supaya batang
     * stacked bar tiap orang kelihatan variatif di layar, bukan seragam.
     */
    private const PROFILES = [
        ['label' => 'Padat Kerja', 'realisasi' => 240, 'todo' => 90, 'achievement' => 30],
        ['label' => 'Banyak To Do', 'realisasi' => 60, 'todo' => 240, 'achievement' => 0],
        ['label' => 'Achiever', 'realisasi' => 120, 'todo' => 40, 'achievement' => 250],
        ['label' => 'Santai (Full Longgar)', 'realisasi' => 0, 'todo' => 0, 'achievement' => 0],
    ];

    public function run(): void
    {
        // F-5: single-tenant sampai v3.0 -- ambil organisasi SATU-SATUNYA yang
        // ada, pola sama CalendarWorkloadDemoSeeder/TaskCategoriesTop5DemoSeeder.
        $organization = Organization::firstOrFail();

        $activeUsers = User::where('organization_id', $organization->id)->where('is_active', true)->get();

        if ($activeUsers->isEmpty()) {
            $this->command?->error('Nol user aktif di organisasi -- seeder ini butuh minimal 1 user aktif untuk jadi anggota tim demo.');

            return;
        }

        $today = Carbon::now();
        $schedule = WorkSchedule::active($organization->id, $today);

        // GUARD (pola sama CalendarWorkloadDemoSeeder): TANPA WorkSchedule aktif,
        // kapasitas SEMUA user = 0 -- widget tidak ada artinya buat didemokan.
        if ($schedule === null) {
            $this->command?->error('Organisasi ini belum punya Jam Kerja (WorkSchedule) aktif -- seeder ini butuh minimal 1 WorkSchedule aktif supaya kapasitas & jendela realisasi bisa dikenali. Setel dulu lewat Pengaturan > Jam Kerja, baru jalankan seeder ini lagi.');

            return;
        }

        // PERINGATAN (bukan abort, lihat RISIKO header): kolom "longgar" cuma
        // valid kalau HARI INI hari kerja & bukan libur -- todo/achievement
        // tetap valid apa pun harinya.
        $isHoliday = Holiday::where('organization_id', $organization->id)->whereDate('date', $today)->exists();
        $isBusinessDay = in_array($today->isoWeekday(), $schedule->days_of_week, true) && ! $isHoliday;
        if (! $isBusinessDay) {
            $this->command?->warn('Peringatan: hari ini ('.$today->toDateString().') BUKAN hari kerja (atau libur) menurut Jam Kerja organisasi -- kolom "Waktu Longgar" tidak akan berkurang sesuai profil realisasi di bawah (tetap tampil penuh = kapasitas). Kolom "To Do"/"Achievement" TIDAK terpengaruh, tetap valid didemokan.');
        }

        $owner = $activeUsers->first();

        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Demo Beban per Kategori '.$today->format('Y-m-d H:i'),
            'owner_id' => $owner->id,
        ]);
        $project->members()->sync($activeUsers->pluck('id')->all());
        TaskStatus::seedDefaults($project);
        $todoStatus = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
        $doneStatus = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

        $windowStart = $today->copy()->setTimeFromTimeString((string) $schedule->start_time);
        $windowEnd = $today->copy()->setTimeFromTimeString((string) $schedule->end_time);

        foreach ($activeUsers as $i => $user) {
            $profile = self::PROFILES[$i % count(self::PROFILES)];

            if ($profile['realisasi'] > 0) {
                $this->createRealisasi($project, $todoStatus, $organization, $user, $owner, $profile['label'], $profile['realisasi'], $windowStart, $windowEnd);
            }

            if ($profile['todo'] > 0) {
                $this->createTodo($project, $todoStatus, $organization, $user, $owner, $profile['label'], $profile['todo'], $today);
            }

            if ($profile['achievement'] > 0) {
                $this->createAchievement($project, $todoStatus, $doneStatus, $organization, $user, $owner, $profile['label'], $profile['achievement']);
            }

            $this->command?->info("- {$user->name}: profil \"{$profile['label']}\" (realisasi={$profile['realisasi']}m, todo={$profile['todo']}m, achievement={$profile['achievement']}m).");
        }

        $this->command?->info("Demo beban per kategori dibuat di project '{$project->name}' untuk tanggal {$today->toDateString()}.");
        $this->command?->info('Cek: widget "Beban per Kategori" Command Center (buka /dashboard/overview) -- 4 profil bergantian per user.');
    }

    /**
     * KONTRAK: simulasi "sudah kerja X menit hari ini" -- task_time_segments
     * DITUTUP (ended_at terisi) DI DALAM jendela jam kerja hari ini, supaya
     * BusinessHoursCalculator::overlapMinutes() (F-57) menghitungnya PENUH,
     * bukan ditebak/di-mock. Task pembungkus SEKADAR FK valid (task_id wajib
     * di task_time_segments) -- statusnya tidak relevan buat "longgar".
     */
    private function createRealisasi(Project $project, TaskStatus $todoStatus, Organization $organization, User $user, User $owner, string $label, int $minutes, Carbon $windowStart, Carbon $windowEnd): void
    {
        $task = Task::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'task_status_id' => $todoStatus->id,
            'title' => "Demo Realisasi -- {$label} ({$user->name})",
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

    /**
     * KONTRAK: task TODO (flag F-44) dengan due_date HARI INI -- sumber
     * "todo_minutes" (DashboardController::memberCategoryChart()).
     */
    private function createTodo(Project $project, TaskStatus $todoStatus, Organization $organization, User $user, User $owner, string $label, int $minutes, Carbon $today): void
    {
        $task = Task::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'task_status_id' => $todoStatus->id,
            'title' => "Demo To Do -- {$label} ({$user->name})",
            'task_type' => 'tentative',
            'estimated_minutes' => $minutes,
            'due_date' => $today->copy()->setTime(17, 0),
            'created_by' => $owner->id,
        ]);
        $task->assignees()->sync([$user->id]);
    }

    /**
     * KONTRAK: task dibuat TODO dulu, BARU dipindah ke DONE -- memicu
     * TaskObserver::updating() (F-21: completed_at = now() = hari ini),
     * SATU-SATUNYA jalan completed_at terisi benar (create langsung berstatus
     * DONE TIDAK memicu observer itu, lihat TaskCategoriesTop5DemoSeeder
     * RISIKO). Sumber "achievement_minutes".
     */
    private function createAchievement(Project $project, TaskStatus $todoStatus, TaskStatus $doneStatus, Organization $organization, User $user, User $owner, string $label, int $minutes): void
    {
        $task = Task::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'task_status_id' => $todoStatus->id,
            'title' => "Demo Achievement -- {$label} ({$user->name})",
            'task_type' => 'tentative',
            'estimated_minutes' => $minutes,
            'due_date' => Carbon::now()->addDays(3),
            'created_by' => $owner->id,
        ]);
        $task->assignees()->sync([$user->id]);
        $task->update(['task_status_id' => $doneStatus->id]);
    }
}
