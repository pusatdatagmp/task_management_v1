<?php

/**
 * ==========================================================
 * MODUL       : TaskUser
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Pivot task_user dijadikan Model eksplisit (bukan pivot default)
 *               SATU-SATUNYA alasan: supaya attach/detach assignee memicu event
 *               Eloquent (created/deleted) yang ditangkap TaskUserObserver -> log
 *               assigned/unassigned (F-22). Pivot default Laravel tidak fire event.
 * DIPANGGIL   : Task::assignees() (->using(TaskUser::class))
 * MEMANGGIL   : -
 * DATA MASUK  : attach()/detach()/sync() dari fitur assign task (Hari-3, belum dibuat)
 * DATA KELUAR : TaskUserObserver
 * RISIKO      : Tabel task_user WAJIB punya kolom id sendiri (bukan composite key)
 *               supaya Eloquent memperlakukan tiap baris pivot sebagai model utuh dan
 *               event created/deleted benar-benar terpicu (lihat migration §3.11).
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Observers\TaskUserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[ObservedBy(TaskUserObserver::class)]
class TaskUser extends Pivot
{
    use SerializesDatesInAppTimezone;

    public $incrementing = true;

    protected $table = 'task_user';
}
