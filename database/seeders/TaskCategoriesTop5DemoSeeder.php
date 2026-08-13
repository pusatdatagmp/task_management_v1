<?php

/**
 * ==========================================================
 * MODUL       : TaskCategoriesTop5DemoSeeder
 * KLASIFIKASI : DATA (seeder, BUKAN wajib jalan otomatis)
 * TUJUAN      : Permintaan Boss (2026-08-13) -- data uji manual untuk widget
 *               "Kategori Tugas Berulang" Command Center (A4) setelah dibatasi
 *               TOP-5 (revisi 2026-08-13, DashboardController::taskCategories()).
 *               Bikin 8 template AKTIF dengan jumlah task ALL-TIME beda-beda
 *               supaya Boss bisa lihat LANGSUNG di browser: cuma 5 terbesar yang
 *               tampil di widget, 3 sisanya TIDAK -- sambil tombol "Show More"
 *               (-> task-templates.all) tetap menampilkan semuanya. TIDAK
 *               didaftarkan di DatabaseSeeder::run() -- jalankan manual saat butuh:
 *                   php artisan db:seed --class=TaskCategoriesTop5DemoSeeder
 *               Hapus lagi lewat project "Demo Kategori Tugas Berulang" (folder
 *               proyek terpisah, gampang dihapus) kalau sudah selesai dicek.
 * DIPANGGIL   : php artisan db:seed --class=... (manual, Boss)
 * MEMANGGIL   : Organization::first() (single-tenant, F-5), User (is_active),
 *               DashboardController::taskCategories() (KONTRAK dibaca, TIDAK
 *               dipanggil langsung -- seeder cuma bikin data, sortByDesc()->take(5)
 *               tetap murni tugas controller)
 * DATA MASUK  : -
 * DATA KELUAR : 1 project baru ("Demo Kategori Tugas Berulang") + 8 TaskTemplate
 *               aktif + task per template (jumlah 12,9,7,5,3,2,1,0 -- urutan
 *               SENGAJA turun supaya batas potong top-5 [12,9,7,5,3] vs
 *               tersembunyi [2,1,0] gampang dibedakan di layar)
 * RISIKO      : SUMBER -- taskCategories() hitung SEMUA Task.task_template_id
 *               milik template itu TANPA filter status (App\Models\TaskTemplate::
 *               tasks(), hasMany polos). Task demo di sini SENGAJA dibuat
 *               LANGSUNG berstatus DONE (is_completed=true, position=3 hasil
 *               TaskStatus::seedDefaults()) -- supaya tetap kehitung di widget
 *               ini TAPI TIDAK ikut mencemari widget lain yang filter
 *               is_completed=false (beban/heatmap/top-10 task/overdue count).
 * ==========================================================
 */

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class TaskCategoriesTop5DemoSeeder extends Seeder
{
    public function run(): void
    {
        // F-5: single-tenant sampai v3.0 -- ambil organisasi SATU-SATUNYA yang
        // ada, pola sama CalendarWorkloadDemoSeeder.
        $organization = Organization::firstOrFail();

        $activeUsers = User::where('organization_id', $organization->id)->where('is_active', true)->get();

        if ($activeUsers->isEmpty()) {
            $this->command?->error('Nol user aktif di organisasi -- seeder ini butuh minimal 1 user aktif untuk jadi assignee task demo.');

            return;
        }

        $owner = $activeUsers->first();

        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Demo Kategori Tugas Berulang '.now()->format('Y-m-d H:i'),
            'owner_id' => $owner->id,
        ]);
        $project->members()->sync($activeUsers->pluck('id')->all());
        TaskStatus::seedDefaults($project);
        $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

        // 8 template, jumlah task turun -- 5 TERBESAR (12,9,7,5,3) WAJIB tampil
        // di widget, 3 TERKECIL (2,1,0) WAJIB TIDAK tampil (revisi 2026-08-13).
        // interval_unit divariasi (day/week/month) murni biar schedule_label
        // di layar tidak seragam, tidak memengaruhi hitungan cap.
        $blueprint = [
            ['total' => 12, 'unit' => 'day', 'value' => 1],
            ['total' => 9, 'unit' => 'day', 'value' => 3],
            ['total' => 7, 'unit' => 'week', 'value' => 1],
            ['total' => 5, 'unit' => 'week', 'value' => 2],
            ['total' => 3, 'unit' => 'month', 'value' => 1],
            ['total' => 2, 'unit' => 'day', 'value' => 5],
            ['total' => 1, 'unit' => 'week', 'value' => 3],
            ['total' => 0, 'unit' => 'month', 'value' => 2],
        ];

        foreach ($blueprint as $i => $row) {
            $template = TaskTemplate::create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'title' => sprintf('Demo Kategori #%d (%d task)', $i + 1, $row['total']),
                'task_type' => 'daily',
                'estimated_minutes' => 30,
                'points' => 1,
                'recurrence_config' => [],
                'default_assignees' => [],
                'is_active' => true,
                'anchor_strategy' => 'time_based',
                'interval_value' => $row['value'],
                'interval_unit' => $row['unit'],
            ]);

            $this->createDemoTasks($project, $done, $organization, $activeUsers, $owner, $template, $row['total']);
        }

        $this->command?->info("Demo kategori tugas berulang dibuat di project '{$project->name}' (8 template, jumlah task 12/9/7/5/3/2/1/0).");
        $this->command?->info('Cek: widget "Kategori Tugas Berulang" Command Center cuma nampilin 5 template pertama (total 12,9,7,5,3) -- 3 sisanya (2,1,0) cuma muncul lewat tombol "Show More".');
    }

    /**
     * @param  Collection<int, User>  $activeUsers  assignee tunggal (owner) cukup -- fokus demo
     *                                              ini JUMLAH template yang tampil, bukan beban per-assignee.
     */
    private function createDemoTasks(
        Project $project,
        TaskStatus $doneStatus,
        Organization $organization,
        Collection $activeUsers,
        User $owner,
        TaskTemplate $template,
        int $count,
    ): void {
        for ($i = 0; $i < $count; $i++) {
            $task = Task::create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'task_status_id' => $doneStatus->id,
                'task_template_id' => $template->id,
                'title' => "{$template->title} -- task #{$i}",
                'task_type' => 'daily',
                'estimated_minutes' => 30,
                'due_date' => now()->subDays($i + 1)->setTime(17, 0),
                'created_by' => $owner->id,
            ]);

            $task->assignees()->sync([$activeUsers->first()->id]);
        }
    }
}
