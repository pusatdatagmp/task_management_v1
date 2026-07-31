<?php

/**
 * ==========================================================
 * MODUL       : LogsActivity
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Helper bersama semua Observer supaya format baris activity_logs
 *               konsisten (F-22) — satu tempat yang tahu cara menulis log, bukan
 *               tiap observer menulis ActivityLog::create() dengan bentuk beda-beda.
 * DIPANGGIL   : TaskObserver, ProjectObserver, DeadlineExtensionObserver, AttachmentObserver, TaskUserObserver
 * MEMANGGIL   : ActivityLog
 * DATA MASUK  : Model subjek + event + snapshot old/new
 * DATA KELUAR : Baris baru di activity_logs (immutable, F-23)
 * RISIKO      : user_id NULL berarti aksi sistem (mis. dijalankan seeder/console tanpa
 *               user login) — bukan bug, memang begitu desainnya (F-51 tetap tercatat).
 * ==========================================================
 */

namespace App\Observers\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    protected function logActivity(Model $subject, string $event, ?array $old, ?array $new): void
    {
        ActivityLog::create([
            'organization_id' => $subject->organization_id,
            'user_id' => Auth::id(),
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'properties' => ['old' => $old, 'new' => $new],
        ]);
    }
}
