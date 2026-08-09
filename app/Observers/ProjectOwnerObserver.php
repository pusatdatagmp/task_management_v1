<?php

/**
 * ==========================================================
 * MODUL       : ProjectOwnerObserver
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Log assigned/unassigned (F-51) saat pivot project_owners dibuat/
 *               dihapus lewat Project::owners()->sync(). Pola IDENTIK
 *               ProjectUserObserver (F-71) — SENGAJA pakai nama event yang SAMA
 *               (bukan 'owner_assigned' baru) supaya reuse label F-106
 *               (ActivityLogPresenter) apa adanya, nol mapping baru diperlukan.
 *               "Owner" tetap "di-assign ke project", true secara harfiah.
 * DIPANGGIL   : Laravel (event Eloquent) via #[ObservedBy] di App\Models\ProjectOwner
 * MEMANGGIL   : ActivityLog, Project
 * DATA MASUK  : project_id, user_id, position dari baris pivot yang dibuat/dihapus
 * DATA KELUAR : activity_logs (subject = Project, konsisten pola ProjectUserObserver)
 * RISIKO      : F-51 — kalau observer ini tidak terpasang, sync owner jadi lubang log.
 * ==========================================================
 */

namespace App\Observers;

use App\Models\Project;
use App\Models\ProjectOwner;
use App\Observers\Concerns\LogsActivity;

class ProjectOwnerObserver
{
    use LogsActivity;

    public function created(ProjectOwner $pivot): void
    {
        $project = Project::find($pivot->project_id);

        if ($project) {
            $this->logActivity($project, 'assigned', null, ['user_id' => $pivot->user_id, 'as_owner' => true, 'position' => $pivot->position]);
        }
    }

    public function deleted(ProjectOwner $pivot): void
    {
        $project = Project::find($pivot->project_id);

        if ($project) {
            $this->logActivity($project, 'unassigned', ['user_id' => $pivot->user_id, 'as_owner' => true, 'position' => $pivot->position], null);
        }
    }
}
