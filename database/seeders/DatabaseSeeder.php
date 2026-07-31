<?php

/**
 * ==========================================================
 * MODUL       : DatabaseSeeder
 * KLASIFIKASI : DATA
 * TUJUAN      : Data realistis Hari-1 (F-26) — tanpa ini rumus dashboard §5 dan
 *               observer F-39/F-41/F-51 tidak bisa diuji, dan bug baru ketemu saat
 *               Boss demo ke tim. Struktur & jumlah data mengikuti 02-DATA-MODEL §8 PERSIS.
 * DIPANGGIL   : php artisan migrate:fresh --seed
 * MEMANGGIL   : Organization, User, WorkSchedule, Holiday, Project, TaskStatus,
 *               TaskTemplate, Task, TaskTimeSegment, DeadlineExtension, Attachment,
 *               RolePermissionSeeder
 * DATA MASUK  : -
 * DATA KELUAR : Database lokal (development only)
 * RISIKO      : 3 task "frozen" (F-39) SENGAJA dibuat langsung dengan task_status_id
 *               DONE + actual_minutes terisi dalam satu create() — ini tidak memicu
 *               kalkulasi ulang TaskObserver (yang hanya jalan saat transisi status).
 *               actual_minutes tetap dihitung via Task::calculateActualMinutes()
 *               (rumus F-57 yang sama dengan TaskObserver, BUKAN dihitung manual
 *               terpisah), hanya pemicunya manual bukan live transition status.
 *               F-86 — SELURUH member yang jadi assignee task WAJIB di-attach ke
 *               project->members() DULU. StoreTaskRequest (jalur HTTP normal)
 *               menolak assignee yang bukan project_user, tapi seeder ini menulis
 *               langsung ke Eloquent (lewat validasi itu) — kalau lupa attach,
 *               data seeder jadi TIDAK MUNGKIN terjadi lewat UI, bikin bug lain
 *               (mis. dashboard project) tersembunyi karena diuji pakai data cacat.
 * ==========================================================
 */

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\DeadlineExtension;
use App\Models\Holiday;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\TaskTimeSegment;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::create([
            'name' => 'DEEVATECH',
            'slug' => 'deevatech',
        ]);

        // SUMBER: RBAC §F — role 'admin'/'member' pindah dari kolom enum (dihapus,
        // migrasi 100500) ke tabel roles/permissions. WAJIB dibuat SEBELUM user
        // (role_id NOT NULL FK).
        $roles = RolePermissionSeeder::seedSystemRolesForOrganization($organization);

        $admin = User::create([
            'organization_id' => $organization->id,
            'name' => 'Admin Boss',
            'email' => 'admin@deevatech.test',
            'password' => bcrypt('password'),
            'role_id' => $roles['admin']->id,
            'employment_type' => 'internal',
            'is_active' => true,
        ]);

        $members = collect(range(1, 9))->map(fn (int $i) => User::create([
            'organization_id' => $organization->id,
            'name' => "Member {$i}",
            'email' => "member{$i}@deevatech.test",
            'password' => bcrypt('password'),
            'role_id' => $roles['member']->id,
            'employment_type' => 'internal',
            'is_active' => true,
        ]));

        // SUMBER: 01-PRD §... keputusan Boss — Sen-Jum 08:00-17:00, kapasitas 480 menit (F-40).
        WorkSchedule::create([
            'organization_id' => $organization->id,
            'effective_from' => now()->subMonths(6)->toDateString(),
            'days_of_week' => [1, 2, 3, 4, 5],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'daily_capacity_minutes' => 480,
            'created_by' => $admin->id,
        ]);

        // F-43 matang (HARDEN): beberapa contoh libur nasional 2026 — BUKAN kalender
        // nasional lengkap (over-scope, F-D4). Admin isi/lengkapi sendiri lewat UI
        // Pengaturan > Hari Libur (Fase D).
        collect([
            ['date' => '2026-01-01', 'name' => 'Tahun Baru Masehi'],
            ['date' => '2026-05-01', 'name' => 'Hari Buruh Internasional'],
            ['date' => '2026-08-17', 'name' => 'Hari Kemerdekaan RI'],
            ['date' => '2026-12-25', 'name' => 'Hari Raya Natal'],
        ])->each(fn (array $holiday) => Holiday::create([
            'organization_id' => $organization->id,
            ...$holiday,
        ]));

        $statusBlueprint = [
            ['name' => 'TODO', 'color' => '#94a3b8', 'position' => 0, 'is_work_state' => false, 'is_review' => false, 'is_completed' => false],
            ['name' => 'IN PROGRESS', 'color' => '#3b82f6', 'position' => 1, 'is_work_state' => true, 'is_review' => false, 'is_completed' => false],
            ['name' => 'REVIEW', 'color' => '#f59e0b', 'position' => 2, 'is_work_state' => false, 'is_review' => true, 'is_completed' => false],
            ['name' => 'DONE', 'color' => '#22c55e', 'position' => 3, 'is_work_state' => false, 'is_review' => false, 'is_completed' => true],
        ];

        $projectStatuses = []; // project_id => Collection<TaskStatus>, dipakai lookup di bawah

        $projects = collect(['Website Revamp', 'Operasional Harian'])->map(function (string $name) use ($organization, $admin, $members, $statusBlueprint, &$projectStatuses) {
            $project = Project::create([
                'organization_id' => $organization->id,
                'name' => $name,
                'description' => fake()->sentence(),
                'owner_id' => $admin->id,
                'is_archived' => false,
            ]);

            // F-86: seluruh member SENGAJA diikutkan di KEDUA project — task
            // reguler di bawah menyebar assignee lintas project (i%2/i%9), jadi
            // member manapun bisa jadi assignee di project manapun. Kalau hanya
            // sebagian di-attach, sebagian task hasil seeder jadi data yang TIDAK
            // MUNGKIN terjadi lewat form Task asli (StoreTaskRequest menolak
            // assignee bukan project_user).
            $project->members()->attach($admin->id);
            $project->members()->attach($members->pluck('id'));

            $projectStatuses[$project->id] = collect($statusBlueprint)->map(fn (array $status) => TaskStatus::create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                ...$status,
            ]));

            return $project;
        });

        // BUSINESS RULE: F-46 — template BUKAN task, belum digenerate (last_generated_date null).
        TaskTemplate::create([
            'organization_id' => $organization->id,
            'project_id' => $projects[1]->id,
            'title' => 'Rekap absensi harian',
            'task_type' => 'daily',
            'estimated_minutes' => 30,
            'points' => 2,
            'priority' => 'normal',
            'recurrence_config' => ['due_time' => '17:00'],
            'default_assignees' => [$members[0]->id],
            'is_active' => true,
            'last_generated_date' => null,
        ]);

        TaskTemplate::create([
            'organization_id' => $organization->id,
            'project_id' => $projects[1]->id,
            'title' => 'Laporan mingguan tim',
            'task_type' => 'weekly',
            'estimated_minutes' => 90,
            'points' => 5,
            'priority' => 'normal',
            'recurrence_config' => ['day_of_week' => 5],
            'default_assignees' => [$members[1]->id, $members[2]->id],
            'is_active' => true,
            'last_generated_date' => null,
        ]);

        TaskTemplate::create([
            'organization_id' => $organization->id,
            'project_id' => $projects[1]->id,
            'title' => 'Tutup buku bulanan',
            'task_type' => 'monthly',
            'estimated_minutes' => 180,
            'points' => 8,
            'priority' => 'high',
            'recurrence_config' => ['day_of_month' => 25],
            'default_assignees' => [$members[3]->id],
            'is_active' => true,
            'last_generated_date' => null,
        ]);

        // 27 task reguler tersebar di 5 task_type x TODO/IN_PROGRESS/REVIEW (DONE
        // dikhususkan untuk 3 task frozen di bawah, F-39).
        $taskTypes = ['daily', 'weekly', 'monthly', 'tentative', 'project'];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        $estimateOptions = [30, 60, 90, 120, 180, 240, 360, 480];

        $regularTasks = collect();

        for ($i = 0; $i < 27; $i++) {
            $project = $projects[$i % 2];
            $status = $projectStatuses[$project->id][$i % 3]; // TODO / IN PROGRESS / REVIEW
            $assignee = $members[$i % 9];
            $overdue = $i % 2 === 0;

            $task = Task::create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'task_status_id' => $status->id,
                'title' => fake()->sentence(4),
                'description' => fake()->paragraph(),
                'task_type' => $taskTypes[$i % 5],
                'priority' => $priorities[$i % 4],
                'points' => fake()->numberBetween(1, 10),
                'estimated_minutes' => $estimateOptions[$i % count($estimateOptions)],
                'due_date' => $overdue ? now()->subDays(fake()->numberBetween(1, 10)) : now()->addDays(fake()->numberBetween(1, 14)),
                'created_by' => $admin->id,
            ]);

            $task->assignees()->attach($assignee->id);
            $regularTasks->push($task);
        }

        // 3 task DONE+approved dengan actual_minutes & rejection_count FROZEN (F-39).
        // Segmen dibuat DULU, lalu actual_minutes dihitung via
        // Task::calculateActualMinutes() — RUMUS YANG SAMA PERSIS dipakai TaskObserver
        // saat approve beneran (F-57, cap jendela kerja) — supaya seeder tidak
        // menghitung manual terpisah yang bisa drift dari rumus asli. task_status_id
        // TIDAK berubah di update terakhir, jadi TaskObserver TIDAK mencoba menghitung
        // ulang (cocok F-39: sekali frozen, selamanya).
        //
        // Task index 1 SENGAJA punya 1 segmen yang menyeberang weekend penuh (Jumat
        // 16:00 -> Senin 09:00) — raw durasi 65 jam, tapi realisasi yang dibekukan
        // HARUS 2 jam (contoh persis 01-PRD §6/F-57). Ini bukti F-57 bekerja di data
        // nyata, bukan cuma di unit test.
        // GUARD TANGGAL: JANGAN pakai now()->subDays(N) polos untuk hari kerja — N hari
        // ke belakang dari "hari ini" bisa jatuh di Sabtu/Minggu tergantung kapan seeder
        // dijalankan (won't reproduce di local Boss kalau run beda hari), dan hasilnya
        // actual_minutes = 0 (bukan bug F-57, tapi bug tanggal seeder). Semua anchor di
        // bawah diturunkan dari Senin-minggu-ini supaya SELALU jatuh di hari kerja.
        $fridayLastWeek = now()->subWeek()->startOfWeek()->addDays(4)->setTime(16, 0); // Jumat pekan lalu 16:00
        $mondayThisWeek = $fridayLastWeek->copy()->startOfDay()->addDays(3); // Senin minggu ini, 00:00
        $tuesdayThisWeek = $mondayThisWeek->copy()->addDay();
        $wednesdayThisWeek = $mondayThisWeek->copy()->addDays(2);

        $frozenSpecs = [
            [
                'rejection_count' => 0,
                'quality_rating' => 5,
                'completed_at' => $tuesdayThisWeek->copy()->setTime(15, 30),
                'segments' => [
                    [$tuesdayThisWeek->copy()->setTime(9, 0), $tuesdayThisWeek->copy()->setTime(12, 0)],
                    [$tuesdayThisWeek->copy()->setTime(13, 0), $tuesdayThisWeek->copy()->setTime(15, 0)],
                ],
            ],
            [
                'rejection_count' => 1,
                'quality_rating' => 4,
                'completed_at' => $mondayThisWeek->copy()->setTime(16, 30),
                'segments' => [
                    [$fridayLastWeek, $mondayThisWeek->copy()->setTime(9, 0)], // F-57: cap ke 2 jam
                    [$mondayThisWeek->copy()->setTime(13, 0), $mondayThisWeek->copy()->setTime(16, 0)],
                ],
            ],
            [
                'rejection_count' => 2,
                'quality_rating' => 3,
                'completed_at' => $wednesdayThisWeek->copy()->setTime(15, 0),
                'segments' => [
                    [$wednesdayThisWeek->copy()->setTime(9, 0), $wednesdayThisWeek->copy()->setTime(11, 0)],
                    [$wednesdayThisWeek->copy()->setTime(13, 0), $wednesdayThisWeek->copy()->setTime(15, 0)],
                ],
            ],
        ];

        $frozenTasks = collect();

        foreach ($frozenSpecs as $index => $spec) {
            $project = $projects[$index % 2];
            $doneStatus = $projectStatuses[$project->id]->firstWhere('is_completed', true);
            $assignee = $members[$index];
            $completedAt = $spec['completed_at'];

            $task = Task::create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'task_status_id' => $doneStatus->id,
                'title' => fake()->sentence(4),
                'description' => fake()->paragraph(),
                'task_type' => 'tentative',
                'priority' => 'normal',
                'points' => 5,
                'estimated_minutes' => 300,
                'actual_minutes' => 0, // placeholder, ditimpa di bawah setelah segmen dibuat
                'rejection_count' => $spec['rejection_count'],
                'quality_rating' => $spec['quality_rating'],
                'due_date' => $completedAt,
                'completed_at' => $completedAt,
                'approved_at' => $completedAt,
                'approved_by' => $admin->id,
                'created_by' => $admin->id,
            ]);

            $task->assignees()->attach($assignee->id);

            foreach ($spec['segments'] as [$started, $ended]) {
                TaskTimeSegment::create([
                    'organization_id' => $organization->id,
                    'task_id' => $task->id,
                    'user_id' => $assignee->id,
                    'started_at' => $started,
                    'ended_at' => $ended,
                ]);
            }

            $task->update(['actual_minutes' => $task->calculateActualMinutes()]);
            $frozenTasks->push($task);
        }

        // 5 subtask, maks 1 level (F-20) — parent diambil dari task reguler di atas.
        for ($i = 0; $i < 5; $i++) {
            $parent = $regularTasks[$i];

            $subtask = Task::create([
                'organization_id' => $organization->id,
                'project_id' => $parent->project_id,
                'task_status_id' => $parent->task_status_id,
                'parent_task_id' => $parent->id,
                'title' => 'Subtask: '.fake()->sentence(3),
                'task_type' => 'tentative',
                'priority' => 'normal',
                'points' => 2,
                'estimated_minutes' => $estimateOptions[$i % 3],
                'due_date' => $parent->due_date,
                'created_by' => $admin->id,
            ]);

            $subtask->assignees()->attach($members[$i]->id);
        }

        // 4 task_time_segments tambahan (total 6 + 4 = 10, F-41). 2 di antaranya
        // MENYEBERANG malam/weekend (F-57 — belum dihitung capping-nya di Hari-1,
        // tapi datanya harus ada supaya v0.8 punya kasus uji nyata).
        $inProgressTasks = $regularTasks->filter(fn (Task $t) => $t->taskStatus->is_work_state)->values();

        // Kasus 1: menyeberang MALAM (Kamis 22:00 -> Jumat 03:00).
        $overnightStart = now()->subWeek()->startOfWeek()->addDays(3)->setTime(22, 0); // Kamis
        TaskTimeSegment::create([
            'organization_id' => $organization->id,
            'task_id' => $inProgressTasks[0]->id,
            'user_id' => $members[0]->id,
            'started_at' => $overnightStart,
            'ended_at' => $overnightStart->copy()->addHours(5), // Jumat 03:00
        ]);

        // Kasus 2: menyeberang WEEKEND penuh (Jumat 16:00 -> Senin 09:00).
        $weekendStart = now()->subWeek()->startOfWeek()->addDays(4)->setTime(16, 0); // Jumat
        TaskTimeSegment::create([
            'organization_id' => $organization->id,
            'task_id' => $inProgressTasks[1]->id,
            'user_id' => $members[1]->id,
            'started_at' => $weekendStart,
            'ended_at' => $weekendStart->copy()->addDays(3)->setTime(9, 0), // Senin 09:00
        ]);

        // 2 segmen biasa (1 hari sama), 1 di antaranya SEDANG BERJALAN (ended_at NULL, F-38).
        TaskTimeSegment::create([
            'organization_id' => $organization->id,
            'task_id' => $inProgressTasks[2]->id,
            'user_id' => $members[2]->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);

        TaskTimeSegment::create([
            'organization_id' => $organization->id,
            'task_id' => $inProgressTasks[3]->id,
            'user_id' => $members[3]->id,
            'started_at' => now()->subMinutes(45),
            'ended_at' => null, // sedang berjalan
        ]);

        // 1 deadline_extension pending + 1 approved (F-50).
        $taskForPendingExtension = $regularTasks[10];
        DeadlineExtension::create([
            'organization_id' => $organization->id,
            'task_id' => $taskForPendingExtension->id,
            'requested_by' => $members[4]->id,
            'old_due_date' => $taskForPendingExtension->due_date,
            'requested_due_date' => $taskForPendingExtension->due_date->copy()->addDays(3),
            'additional_minutes' => 60,
            'reason' => 'Menunggu data dari vendor pihak ketiga.',
            'status' => 'pending',
        ]);

        $taskForApprovedExtension = $regularTasks[11];
        $approvedExtension = DeadlineExtension::create([
            'organization_id' => $organization->id,
            'task_id' => $taskForApprovedExtension->id,
            'requested_by' => $members[5]->id,
            'old_due_date' => $taskForApprovedExtension->due_date,
            'requested_due_date' => $taskForApprovedExtension->due_date->copy()->addDays(2),
            'additional_minutes' => 120,
            'reason' => 'Scope tambahan diminta admin di tengah pengerjaan.',
            'status' => 'pending',
        ]);

        // Update terpisah supaya DeadlineExtensionObserver::updated() benar-benar
        // memproses alur approve (F-47/F-50), bukan cuma insert baris berstatus approved.
        $approvedExtension->update([
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // 2 attachment: 1 output (hasil kerja), 1 evidence (bukti pengajuan extension, F-49).
        Attachment::create([
            'organization_id' => $organization->id,
            'task_id' => $frozenTasks[0]->id,
            'type' => 'output',
            'file_path' => 'attachments/output/laporan-hasil.pdf',
            'file_name' => 'laporan-hasil.pdf',
            'file_size' => 245_760,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $frozenTasks[0]->assignees()->first()->id,
        ]);

        Attachment::create([
            'organization_id' => $organization->id,
            'task_id' => $taskForApprovedExtension->id,
            'deadline_extension_id' => $approvedExtension->id,
            'type' => 'evidence',
            'file_path' => 'attachments/evidence/bukti-scope-tambahan.png',
            'file_name' => 'bukti-scope-tambahan.png',
            'file_size' => 98_304,
            'mime_type' => 'image/png',
            'uploaded_by' => $members[5]->id,
        ]);
    }
}
