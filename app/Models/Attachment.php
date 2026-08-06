<?php

/**
 * ==========================================================
 * MODUL       : Attachment
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Dua jenis lampiran (F-49) — output kerja & evidence perpanjangan deadline.
 *               Revisi 2026-08-06 item 4: TIGA mode ISI per lampiran (content_type) —
 *               file (perilaku lama), link (URL eksternal), text (teks panjang
 *               langsung) — ORTOGONAL dari `type` (output/evidence, F-49 TIDAK berubah).
 * DIPANGGIL   : AttachmentController (store/download/destroy, v0.8 H5),
 *               DeadlineExtensionController (evidence, v0.8 H6),
 *               AttachmentObserver (log attachment_uploaded/deleted)
 * MEMANGGIL   : Organization, Task, DeadlineExtension (nullable), User (uploaded_by)
 * DATA MASUK  : Upload file ATAU URL ATAU teks bebas, member/admin (F-95)
 * DATA KELUAR : file_path (content_type=file) / url (=link) / body (=text) —
 *               PERSIS SATU dari ketiganya terisi per baris, dua lainnya null
 * RISIKO      : SUMBER — download() (AttachmentController) HANYA valid untuk
 *               content_type=file (file_path null utk link/text, Storage::download()
 *               akan meledak kalau dipanggil ke baris bukan file — controller WAJIB
 *               guard content_type SEBELUM panggil Storage). `url` divalidasi
 *               scheme http/https SAJA di StoreAttachmentRequest/StoreDeadlineExtensionRequest
 *               (bukan di sini) — `javascript:` URI di href bisa dieksekusi browser
 *               saat diklik kalau lolos tervalidasi sebagai "url" biasa.
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
        'content_type',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'url',
        'body',
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
            'content_type' => 'file',
            'file_path' => $path,
            'file_name' => Str::limit(strip_tags($file->getClientOriginalName()), 255, ''), // sanitasi nama tampil (A3)
            'file_size' => $file->getSize(), // A4: dari file nyata, bukan klaim klien
            'mime_type' => $file->getMimeType(), // A4: idem
        ]);
    }

    /**
     * KONTRAK: revisi 2026-08-06 item 4 — lampiran berupa URL eksternal (mis. link
     * Google Drive/Figma), TANPA file fisik. $url WAJIB sudah divalidasi scheme
     * http/https di FormRequest pemanggil (StoreAttachmentRequest/
     * StoreDeadlineExtensionRequest) — method ini TIDAK validasi ulang, cuma simpan.
     *
     * @param  array<string, mixed>  $attributes  Kolom selain content_type/url (task_id,
     *                                            type, uploaded_by, deadline_extension_id, dst).
     */
    public static function storeLink(string $url, array $attributes): self
    {
        return static::create([
            ...$attributes,
            'content_type' => 'link',
            'url' => $url,
        ]);
    }

    /**
     * KONTRAK: revisi 2026-08-06 item 4 — lampiran berupa teks panjang langsung
     * (mis. catatan/hasil kerja tanpa file terpisah), TANPA file fisik. $body
     * disimpan APA ADANYA (plain text) — frontend WAJIB render sebagai teks biasa
     * (bukan dangerouslySetInnerHTML), body BUKAN rich text/HTML seperti
     * Task::description (F-82), jadi nol sanitasi HTML diperlukan di sini.
     *
     * @param  array<string, mixed>  $attributes  Kolom selain content_type/body.
     */
    public static function storeText(string $body, array $attributes): self
    {
        return static::create([
            ...$attributes,
            'content_type' => 'text',
            'body' => $body,
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
