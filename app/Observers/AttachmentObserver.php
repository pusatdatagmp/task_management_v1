<?php

/**
 * ==========================================================
 * MODUL       : AttachmentObserver
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Log attachment_uploaded (F-22) saat file output/evidence diunggah
 *               (F-49), dan 'deleted' (katalog 12 event DM §3.14) saat admin hapus
 *               attachment (F-105 — hard delete, bukan soft delete, F-16 hanya
 *               wajib untuk users/projects/tasks).
 * DIPANGGIL   : Laravel (event Eloquent) via #[ObservedBy] di App\Models\Attachment
 * MEMANGGIL   : ActivityLog
 * DATA MASUK  : Attachment baru/dihapus (type output/evidence)
 * DATA KELUAR : activity_logs
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Observers;

use App\Models\Attachment;
use App\Observers\Concerns\LogsActivity;

class AttachmentObserver
{
    use LogsActivity;

    public function created(Attachment $attachment): void
    {
        $this->logActivity($attachment, 'attachment_uploaded', null, $attachment->only([
            'task_id', 'type', 'file_name',
        ]));
    }

    public function deleted(Attachment $attachment): void
    {
        $this->logActivity($attachment, 'deleted', null, null);
    }
}
