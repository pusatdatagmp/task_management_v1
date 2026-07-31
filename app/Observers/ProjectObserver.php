<?php

/**
 * ==========================================================
 * MODUL       : ProjectObserver
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Log created/updated/deleted generik untuk Project (F-51: tidak boleh
 *               ada mutasi data bisnis yang lolos tanpa jejak, walau Project bukan
 *               sumber langsung 6 metrik KPI).
 * DIPANGGIL   : Laravel (event Eloquent) via #[ObservedBy] di App\Models\Project
 * MEMANGGIL   : ActivityLog
 * DATA MASUK  : Perubahan Project (CRUD Hari-2, belum dibuat)
 * DATA KELUAR : activity_logs
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Observers;

use App\Models\Project;
use App\Observers\Concerns\LogsActivity;

class ProjectObserver
{
    use LogsActivity;

    public function created(Project $project): void
    {
        $this->logActivity($project, 'created', null, $project->only(['name', 'owner_id']));
    }

    public function updated(Project $project): void
    {
        $this->logActivity($project, 'updated', array_intersect_key($project->getOriginal(), $project->getChanges()), $project->getChanges());
    }

    public function deleted(Project $project): void
    {
        $this->logActivity($project, 'deleted', null, null);
    }
}
