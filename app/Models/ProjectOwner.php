<?php

/**
 * ==========================================================
 * MODUL       : ProjectOwner
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Pivot project_owners dijadikan Model eksplisit (bukan pivot default)
 *               SATU-SATUNYA alasan: supaya attach/detach/sync owner memicu event
 *               Eloquent (created/deleted) yang ditangkap ProjectOwnerObserver -> log
 *               (F-51) — pola IDENTIK ProjectUser/ProjectUserObserver (F-71).
 * DIPANGGIL   : Project::owners() (->using(ProjectOwner::class))
 * MEMANGGIL   : -
 * DATA MASUK  : sync() dari ProjectController (store()/update())
 * DATA KELUAR : ProjectOwnerObserver
 * RISIKO      : Tabel project_owners WAJIB punya kolom id sendiri (bukan composite
 *               key) supaya event created/deleted benar-benar terpicu — sama seperti
 *               ProjectUser (migration sudah punya $table->id()).
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Observers\ProjectOwnerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[ObservedBy(ProjectOwnerObserver::class)]
class ProjectOwner extends Pivot
{
    use SerializesDatesInAppTimezone;

    public $incrementing = true;

    protected $table = 'project_owners';

    protected $fillable = [
        'project_id',
        'user_id',
        'position',
    ];
}
