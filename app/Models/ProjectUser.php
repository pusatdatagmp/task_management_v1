<?php

/**
 * ==========================================================
 * MODUL       : ProjectUser
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Pivot project_user dijadikan Model eksplisit (bukan pivot default)
 *               SATU-SATUNYA alasan: supaya attach/detach/sync member memicu event
 *               Eloquent (created/deleted) yang ditangkap ProjectUserObserver -> log
 *               members_synced (F-71). Pivot default Laravel tidak fire event.
 * DIPANGGIL   : Project::members() (->using(ProjectUser::class))
 * MEMANGGIL   : -
 * DATA MASUK  : attach()/detach()/sync() dari ProjectController (assign member)
 * DATA KELUAR : ProjectUserObserver
 * RISIKO      : Tabel project_user WAJIB punya kolom id sendiri (bukan composite key)
 *               supaya Eloquent memperlakukan tiap baris pivot sebagai model utuh dan
 *               event created/deleted benar-benar terpicu — sama seperti TaskUser
 *               (lihat 02-DATA-MODEL §3.6, migration sudah punya $table->id()).
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Observers\ProjectUserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[ObservedBy(ProjectUserObserver::class)]
class ProjectUser extends Pivot
{
    use SerializesDatesInAppTimezone;

    public $incrementing = true;

    protected $table = 'project_user';
}
