<?php

/**
 * ==========================================================
 * MODUL       : ProjectUserObserver
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Log assigned/unassigned (F-71) saat pivot project_user dibuat/dihapus
 *               lewat Project::members()->attach()/detach()/sync(). Menutup lubang
 *               audit trail F-71: sync member sebelumnya tidak tercatat sama sekali.
 *               Event dipilih SAMA dengan TaskUserObserver (bukan nama baru
 *               'members_synced') supaya konsisten dengan daftar "Event wajib"
 *               02-DATA-MODEL §3.14 yang sudah punya assigned/unassigned generik.
 * DIPANGGIL   : Laravel (event Eloquent) via #[ObservedBy] di App\Models\ProjectUser
 * MEMANGGIL   : ActivityLog, Project
 * DATA MASUK  : project_id, user_id dari baris pivot yang dibuat/dihapus
 * DATA KELUAR : activity_logs (subject = Project, bukan pivot-nya sendiri — konsisten
 *               dengan pola TaskUserObserver, riwayat muncul di histori project)
 * RISIKO      : F-51 — kalau observer ini tidak terpasang (mis. ProjectUser dibuat
 *               tanpa #[ObservedBy]), sync member jadi lubang log lagi seperti F-71.
 * ==========================================================
 */

namespace App\Observers;

use App\Models\Project;
use App\Models\ProjectUser;
use App\Observers\Concerns\LogsActivity;

class ProjectUserObserver
{
    use LogsActivity;

    public function created(ProjectUser $pivot): void
    {
        $project = Project::find($pivot->project_id);

        if ($project) {
            $this->logActivity($project, 'assigned', null, ['user_id' => $pivot->user_id]);
        }
    }

    public function deleted(ProjectUser $pivot): void
    {
        $project = Project::find($pivot->project_id);

        if ($project) {
            $this->logActivity($project, 'unassigned', ['user_id' => $pivot->user_id], null);
        }
    }
}
