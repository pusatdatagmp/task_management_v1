<?php

/**
 * ==========================================================
 * MODUL       : CalendarWorkloadDemoSeeder
 * KLASIFIKASI : DATA (seeder, BUKAN wajib jalan otomatis)
 * TUJUAN      : Permintaan Boss (2026-08-10) -- data uji manual untuk widget
 *               heatmap kalender Command Center (F-131), supaya Boss bisa lihat
 *               LANGSUNG di browser apakah 3 kategori beban (Longgar/Sedang/
 *               Overload, F-128) sudah sesuai atau belum. TIDAK didaftarkan di
 *               DatabaseSeeder::run() -- jalankan manual saat butuh:
 *                   php artisan db:seed --class=CalendarWorkloadDemoSeeder
 *               Hapus lagi task-nya lewat project "Demo Beban Kalender" (folder
 *               proyek terpisah, gampang dihapus) kalau sudah selesai dicek.
 * DIPANGGIL   : php artisan db:seed --class=... (manual, Boss)
 * MEMANGGIL   : Organization::first() (single-tenant, F-5), User (is_active),
 *               DashboardService::dailyLoadTotals()/DashboardController::heatmap()
 *               (KONTRAK dibaca, TIDAK dipanggil langsung -- seeder cuma bikin
 *               data, perhitungan level tetap murni tugas controller/service)
 * DATA MASUK  : -
 * DATA KELUAR : 1 project baru ("Demo Beban Kalender") + 3 task belum-selesai,
 *               due_date beda hari di BULAN DEPAN dari kapan seeder dijalankan
 * RISIKO      : SUMBER -- rumus beban (DashboardService::workloadSpread()/
 *               dailyLoadTotals(), F-118) MENYEBAR estimated_minutes SATU task
 *               ke SEMUA hari dari "hari ini" s/d due_date-nya (porsi mengecil
 *               makin jauh dari due_date), DAN begitu due_date LEWAT relatif ke
 *               suatu tanggal, task itu "dianggap overdue relatif ke tanggal
 *               itu" -- porsi PENUH (bukan pecahan) ikut nempel ke SEMUA
 *               tanggal SESUDAHNYA juga (kasus tepi A3, lihat komentar
 *               workloadSpread()). Makanya 3 task di sini SENGAJA due_date-nya
 *               berurutan naik (awal/tengah/akhir bulan) dengan estimasi makin
 *               besar -- meniru progresi NYATA (beban menumpuk makin lama makin
 *               berat), BUKAN 3 hari terisolasi murni. Kalau Boss mau demo 3
 *               kategori BENAR-BENAR terisolasi (tanpa efek tumpuk), jalankan
 *               seeder ini, screenshot, HAPUS task, ulangi dengan due_date
 *               tunggal berbeda tiap kali -- lapor kalau itu yang dibutuhkan.
 * ==========================================================
 */

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CalendarWorkloadDemoSeeder extends Seeder
{
    public function run(): void
    {
        // F-5: single-tenant sampai v3.0 -- ambil organisasi SATU-SATUNYA yang
        // ada, pola sama F-169 (HandleInertiaRequests fallback guest).
        $organization = Organization::firstOrFail();

        $activeUsers = User::where('organization_id', $organization->id)->where('is_active', true)->get();

        if ($activeUsers->isEmpty()) {
            $this->command?->error('Nol user aktif di organisasi -- seeder ini butuh minimal 1 user aktif untuk dianggap "tim" oleh heatmap.');

            return;
        }

        // GUARD (ketemu saat verifikasi 2026-08-10): TANPA WorkSchedule terdaftar,
        // BusinessHoursCalculator::isBusinessDay() SELALU false (nol hari
        // "kerja" dikenali) -- countBusinessDays() SELALU 0, dipaksa hariKerja=1
        // di SEMUA tanggal (bukan cuma due_date), jadi SEMUA task dianggap
        // "lewat tenggat" di SETIAP hari sekaligus -> demo jadi flat Overload
        // total, BUKAN progresi Longgar->Sedang->Overload yang dimaksud. Gagal
        // TEGAS di sini (bukan diam-diam menghasilkan demo yang salah) --
        // pola sama BusinessHoursCalculator melempar RuntimeException utk data korup.
        if (! WorkSchedule::where('organization_id', $organization->id)->exists()) {
            $this->command?->error('Organisasi ini belum punya Jam Kerja (WorkSchedule) -- seeder ini butuh minimal 1 WorkSchedule aktif supaya "hari kerja" bisa dikenali. Setel dulu lewat Pengaturan > Jam Kerja, baru jalankan seeder ini lagi.');

            return;
        }

        // SUMBER: siapa pun user aktif cukup jadi owner/creator demo -- seeder
        // ini tak butuh IZIN admin sungguhan (bukan alur HTTP tervalidasi),
        // cuma FK valid (organization_id/created_by/owner_id).
        $owner = $activeUsers->first();

        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Demo Beban Kalender '.now()->format('Y-m-d H:i'),
            'owner_id' => $owner->id,
        ]);
        $project->members()->sync($activeUsers->pluck('id')->all());
        TaskStatus::seedDefaults($project);
        $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

        // F-128 (DashboardController::heatmap()): ambang AGREGAT = ambang per-
        // user (aman<210/tengah<420/overload>=420 menit) DIKALI jumlah user aktif.
        $activeUserCount = $activeUsers->count();
        $tengahFloor = 210 * $activeUserCount;
        $overloadFloor = 420 * $activeUserCount;

        // Bulan DEPAN (bukan bulan berjalan) -- kanvas bersih, semua tanggalnya
        // otomatis "masa depan" (F-131: hari lewat selalu netral, tak terhitung),
        // dan Boss tinggal klik panah bulan berikutnya di widget buat lihatnya.
        $targetMonth = now()->addMonthNoOverflow()->startOfMonth();
        $dayLonggar = $targetMonth->copy()->day(3);
        $daySedang = $targetMonth->copy()->day(15);
        $dayOverload = $targetMonth->copy()->day(27);

        // Estimasi per task dihitung SUPAYA total KUMULATIF (bukan per-task
        // sendirian) jatuh di tengah tiap pita kategori -- due_date lebih awal
        // TETAP "menempel penuh" ke hari-hari sesudahnya (lihat RISIKO header),
        // jadi hari Sedang/Overload otomatis mewarisi beban hari sebelumnya.
        $estLonggar = (int) round($tengahFloor * 0.3);                 // hari 1-2: cuma task ini (fraksi kecil) -> Longgar.
        $targetSedangCumulative = (int) round(($tengahFloor + $overloadFloor) / 2);
        $estSedang = max(1, $targetSedangCumulative - $estLonggar);    // hari 3-14: Longgar penuh + Sedang menumpuk -> Sedang.
        $targetOverloadCumulative = (int) round($overloadFloor * 1.5);
        $estOverload = max(1, $targetOverloadCumulative - $targetSedangCumulative); // hari 15+: dua-duanya penuh + task ini -> Overload.

        $this->createDemoTask($project, $todo, $organization, $activeUsers, $owner, 'Demo Beban -- LONGGAR', $dayLonggar, $estLonggar);
        $this->createDemoTask($project, $todo, $organization, $activeUsers, $owner, 'Demo Beban -- SEDANG', $daySedang, $estSedang);
        $this->createDemoTask($project, $todo, $organization, $activeUsers, $owner, 'Demo Beban -- OVERLOAD', $dayOverload, $estOverload);

        $this->command?->info("Demo beban kalender dibuat di project '{$project->name}' untuk bulan {$targetMonth->format('F Y')}.");
        $this->command?->info('Cek: hari ~1-2 = Longgar, ~3-14 = Sedang, ~15+ = Overload (buka Command Center, panah bulan berikutnya).');
        $this->command?->info("Ambang (user aktif={$activeUserCount}): tengah>={$tengahFloor} menit, overload>={$overloadFloor} menit.");
    }

    /**
     * @param  Collection<int, User>  $activeUsers  SEMUA di-assign -- estimated_minutes
     *                                              otomatis kembali ~utuh saat dijumlah lintas assignee (F-96 bagi rata, lalu dijumlah lagi di agregat heatmap).
     */
    private function createDemoTask(
        Project $project,
        TaskStatus $status,
        Organization $organization,
        Collection $activeUsers,
        User $owner,
        string $title,
        Carbon $dueDate,
        int $estimatedMinutes,
    ): void {
        $task = Task::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'task_status_id' => $status->id,
            'title' => $title,
            'task_type' => 'tentative',
            'estimated_minutes' => $estimatedMinutes,
            'due_date' => $dueDate->copy()->setTime(17, 0),
            'created_by' => $owner->id,
        ]);

        $task->assignees()->sync($activeUsers->pluck('id')->all());
    }
}
