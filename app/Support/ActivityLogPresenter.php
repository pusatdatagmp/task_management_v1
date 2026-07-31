<?php

/**
 * ==========================================================
 * MODUL       : ActivityLogPresenter
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Terjemahkan baris `activity_logs` MENTAH (event string + properties
 *               JSON) jadi kalimat Indonesia manusiawi (v1.0 H4, F-106) — SATU
 *               sumber label, dipakai log global (ActivityLogController) DAN
 *               timeline per-task (TaskController::show()) supaya tidak ada dua
 *               peta label yang bisa drift (pola F-72/F-76).
 * DIPANGGIL   : ActivityLogController::index(), TaskController::show()
 * MEMANGGIL   : TaskStatus, User (batch lookup, F-85 — lihat RISIKO)
 * DATA MASUK  : Collection<ActivityLog> — WAJIB sudah eager-load relasi 'user' dan
 *               'subject' (+ nested 'subject.task' untuk Attachment/DeadlineExtension)
 *               sebelum dibungkus presenter ini, presenter TIDAK query relasi itu sendiri.
 * DATA KELUAR : string kalimat per baris log
 * RISIKO      : SUMBER F-85 — event `status_changed` butuh NAMA TaskStatus dari
 *               task_status_id di properties (bisa jadi status yang SEKARANG sudah
 *               dihapus/ganti nama), dan `assigned`/`unassigned`/`recurring_assignee_
 *               dropped`/`approved` butuh NAMA User dari user_id di properties (BUKAN
 *               dari relasi 'user' pelaku — user_id di properties itu OBJEK aksinya,
 *               beda orang). Constructor mem-BATCH SEMUA id itu jadi MAKSIMAL 2 query
 *               tambahan (satu utk TaskStatus, satu utk User) untuk SELURUH halaman,
 *               bukan query per baris — kalau describe() dipanggil TANPA lewat
 *               constructor ini (mis. dibuat manual per baris), N+1 kembali muncul.
 *               `deleted` TIDAK bisa menyebut nama task/project/file — observer
 *               menyimpan properties null,null untuk event itu (lihat TaskObserver/
 *               ProjectObserver/AttachmentObserver), dan subject sudah soft/hard
 *               delete jadi tidak bisa diambil dari relasi juga. Fallback ke "#id".
 * ==========================================================
 */

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Collection;

class ActivityLogPresenter
{
    /** @var Collection<int, string> id => name */
    private Collection $statusNames;

    /** @var Collection<int, string> id => name */
    private Collection $userNames;

    /**
     * @param  Collection<int, ActivityLog>  $logs
     */
    public function __construct(Collection $logs)
    {
        $statusIds = collect();
        $userIds = collect();

        foreach ($logs as $log) {
            $old = $log->properties['old'] ?? [];
            $new = $log->properties['new'] ?? [];

            foreach ([$old, $new] as $props) {
                if (isset($props['task_status_id'])) {
                    $statusIds->push($props['task_status_id']);
                }
                if (isset($props['user_id'])) {
                    $userIds->push($props['user_id']);
                }
                if (isset($props['approved_by'])) {
                    $userIds->push($props['approved_by']);
                }
                if (isset($props['dropped_user_ids']) && is_array($props['dropped_user_ids'])) {
                    $userIds = $userIds->merge($props['dropped_user_ids']);
                }
            }
        }

        // F-85: DUA query di sini, TOTAL, untuk berapa pun baris di $logs — bukan
        // per baris. whereIn kosong (collection kosong) aman, Eloquent kembalikan
        // collection kosong tanpa query dieksekusi percuma. F-15: TETAP lewat
        // OrganizationScope normal (bukan withoutGlobalScopes) — id yang dicari
        // selalu berasal dari log organisasi yang sama, tidak ada alasan membuka
        // akses lintas-organisasi di sini.
        $this->statusNames = $statusIds->isEmpty()
            ? collect()
            : TaskStatus::whereIn('id', $statusIds->unique()->filter())->pluck('name', 'id');

        $this->userNames = $userIds->isEmpty()
            ? collect()
            : User::whereIn('id', $userIds->unique()->filter())->pluck('name', 'id');
    }

    public function describe(ActivityLog $log): string
    {
        $actor = $log->user?->name ?? 'Sistem';
        $old = $log->properties['old'] ?? [];
        $new = $log->properties['new'] ?? [];
        $subjectType = class_basename($log->subject_type);
        $key = "{$subjectType}:{$log->event}";

        return match ($key) {
            'Task:created' => "{$actor} membuat task \"{$this->taskTitle($log)}\".",
            'Task:updated' => "{$actor} mengubah task \"{$this->taskTitle($log)}\".",
            'Task:status_changed' => "{$actor} mengubah status task \"{$this->taskTitle($log)}\" dari \"".
                $this->statusName($old['task_status_id'] ?? null).'" ke "'.$this->statusName($new['task_status_id'] ?? null).'".',
            'Task:assigned' => "{$actor} menambahkan ".$this->userName($new['user_id'] ?? null)." sebagai assignee task \"{$this->taskTitle($log)}\".",
            'Task:unassigned' => "{$actor} melepas ".$this->userName($old['user_id'] ?? null)." dari assignee task \"{$this->taskTitle($log)}\".",
            'Task:rejected' => "{$actor} menolak task \"{$this->taskTitle($log)}\" (penolakan ke-".($new['rejection_count'] ?? '?').').',
            'Task:completed' => "{$actor} menandai task \"{$this->taskTitle($log)}\" selesai.",
            'Task:approved' => "{$actor} meng-approve task \"{$this->taskTitle($log)}\" (rating ".($new['quality_rating'] ?? '?').'/5).',
            'Task:anomaly_flagged' => "Sistem menandai ANOMALI pada task \"{$this->taskTitle($log)}\" — realisasi ".
                ($new['actual_minutes'] ?? '?').' menit vs estimasi '.($new['estimated_minutes'] ?? '?').' menit.',
            'Task:deleted' => "{$actor} menghapus task #{$log->subject_id}.",
            // F-106: label manusiawi WAJIB untuk event non-standar ini juga.
            'Task:recurring_assignee_dropped' => 'Assignee '.
                collect($new['dropped_user_ids'] ?? [])->map(fn ($id) => $this->userName($id))->implode(', ').
                " dilepas dari tugas berulang \"{$this->taskTitle($log)}\" (bukan member project lagi).",

            'Project:created' => "{$actor} membuat project \"{$this->projectName($log)}\".",
            'Project:updated' => "{$actor} mengubah project \"{$this->projectName($log)}\".",
            'Project:assigned' => "{$actor} menambahkan ".$this->userName($new['user_id'] ?? null)." sebagai member project \"{$this->projectName($log)}\".",
            'Project:unassigned' => "{$actor} melepas ".$this->userName($old['user_id'] ?? null)." dari member project \"{$this->projectName($log)}\".",
            'Project:deleted' => "{$actor} menghapus project #{$log->subject_id}.",

            'Attachment:attachment_uploaded' => "{$actor} mengunggah lampiran \"".($new['file_name'] ?? '?').'" ('.
                ($new['type'] ?? '?').") ke task \"{$this->attachmentTaskTitle($log)}\".",
            'Attachment:deleted' => "{$actor} menghapus lampiran #{$log->subject_id}.",

            'DeadlineExtension:extension_requested' => "{$actor} mengajukan perpanjangan deadline untuk task \"{$this->extensionTaskTitle($log)}\" (+".
                ($new['additional_minutes'] ?? 0).' menit).',
            'DeadlineExtension:extension_approved' => "{$actor} menyetujui perpanjangan deadline task \"{$this->extensionTaskTitle($log)}\".",
            'DeadlineExtension:extension_rejected' => "{$actor} menolak perpanjangan deadline task \"{$this->extensionTaskTitle($log)}\"".
                (($new['review_note'] ?? null) ? ": {$new['review_note']}" : '.'),

            default => "{$actor} melakukan aksi \"{$log->event}\" pada {$subjectType} #{$log->subject_id}.",
        };
    }

    private function taskTitle(ActivityLog $log): string
    {
        return $log->subject?->title ?? "#{$log->subject_id}";
    }

    private function projectName(ActivityLog $log): string
    {
        return $log->subject?->name ?? "#{$log->subject_id}";
    }

    private function attachmentTaskTitle(ActivityLog $log): string
    {
        return $log->subject?->task?->title ?? '?';
    }

    private function extensionTaskTitle(ActivityLog $log): string
    {
        return $log->subject?->task?->title ?? '?';
    }

    private function statusName(?int $id): string
    {
        if ($id === null) {
            return '?';
        }

        return $this->statusNames->get($id, 'status yang sudah dihapus');
    }

    private function userName(?int $id): string
    {
        if ($id === null) {
            return 'seseorang';
        }

        return $this->userNames->get($id, 'user yang sudah dihapus');
    }

    /**
     * KONTRAK: label PENDEK generik per event (untuk dropdown filter, BUKAN
     * kalimat lengkap describe()) — tidak butuh subject_type karena dropdown
     * filter cuma perlu satu label per NAMA event, tidak peduli objeknya apa.
     */
    public static function eventLabel(string $event): string
    {
        return match ($event) {
            'created' => 'Dibuat',
            'updated' => 'Diubah',
            'status_changed' => 'Status berubah',
            'assigned' => 'Ditambahkan/di-assign',
            'unassigned' => 'Dilepas',
            'rejected' => 'Ditolak',
            'completed' => 'Selesai',
            'approved' => 'Disetujui',
            'anomaly_flagged' => 'Anomali terdeteksi',
            'deleted' => 'Dihapus',
            'extension_requested' => 'Perpanjangan diajukan',
            'extension_approved' => 'Perpanjangan disetujui',
            'extension_rejected' => 'Perpanjangan ditolak',
            'attachment_uploaded' => 'Lampiran diunggah',
            'recurring_assignee_dropped' => 'Assignee berulang dilepas',
            default => $event,
        };
    }
}
