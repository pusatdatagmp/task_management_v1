<?php

/**
 * ==========================================================
 * MODUL       : Attachment
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Dua jenis lampiran (F-49) — output kerja & evidence perpanjangan deadline.
 * DIPANGGIL   : AttachmentController (store/download/destroy, v0.8 H5),
 *               DeadlineExtensionController (evidence, v0.8 H6),
 *               AttachmentObserver (log attachment_uploaded/deleted)
 * MEMANGGIL   : Organization, Task, DeadlineExtension (nullable), User (uploaded_by)
 * DATA MASUK  : Upload file member/admin, storage lokal storage/app/private (batas v0.5)
 * DATA KELUAR : file_path dipakai render lampiran di UI (Hari-2+)
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Observers\AttachmentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

#[ObservedBy(AttachmentObserver::class)]
class Attachment extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'organization_id',
        'task_id',
        'deadline_extension_id',
        'type',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    /**
     * KONTRAK: simpan file fisik + baris DB dalam satu langkah — dipakai
     * AttachmentController::store() (type=output, v0.8 H5) DAN
     * DeadlineExtensionController::store() (type=evidence, v0.8 H6) supaya
     * aturan keamanan A1-A4 (storage privat, nama fisik UUID dari ISI file
     * bukan klaim klien, file_size/mime_type dari file nyata) TIDAK diduplikasi
     * dua tempat yang bisa drift.
     *
     * @param  array<string, mixed>  $attributes  Kolom selain file_path/file_name/
     *                                            file_size/mime_type (task_id, type,
     *                                            uploaded_by, deadline_extension_id, dst).
     */
    public static function storeUploadedFile(UploadedFile $file, array $attributes): self
    {
        // A3: nama fisik = UUID + ekstensi HASIL TEBAKAN DARI ISI FILE (extension(),
        // Symfony Mime — sama mekanisme dengan rule mimes: di StoreAttachmentRequest),
        // BUKAN nama/ekstensi klaim klien. Tidak ada satu karakter pun dari input user
        // yang masuk ke path fisik (cegah path traversal/collision, A3).
        $storedName = Str::uuid()->toString().'.'.$file->extension();
        $path = $file->storeAs('attachments', $storedName, 'local');

        return static::create([
            ...$attributes,
            'file_path' => $path,
            'file_name' => Str::limit(strip_tags($file->getClientOriginalName()), 255, ''), // sanitasi nama tampil (A3)
            'file_size' => $file->getSize(), // A4: dari file nyata, bukan klaim klien
            'mime_type' => $file->getMimeType(), // A4: idem
        ]);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function deadlineExtension(): BelongsTo
    {
        return $this->belongsTo(DeadlineExtension::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
