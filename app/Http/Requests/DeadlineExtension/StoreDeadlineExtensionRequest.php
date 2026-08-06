<?php

/**
 * ==========================================================
 * MODUL       : StoreDeadlineExtensionRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi pengajuan perpanjangan deadline (F-50) — assignee task
 *               ATAU admin (F-95), evidence opsional lewat infra Attachment H5.
 *               Revisi 2026-08-06 item 4: evidence_type (nullable) pilih MODE
 *               (file/link/text) — TETAP opsional keseluruhan (beda dari Lampiran
 *               Output yang content_type WAJIB), null = tidak melampirkan apa pun.
 * DIPANGGIL   : DeadlineExtensionController::store()
 * MEMANGGIL   : -
 * DATA MASUK  : Form "Perpanjangan Saya" — task dipilih dari dropdown (task_id di
 *               body, BUKAN URL — halaman ini flat lintas project seperti my-tasks)
 * DATA KELUAR : Data tervalidasi -> DeadlineExtensionController::store()
 * RISIKO      : SUMBER : authorize() SENGAJA hanya cek "sudah login" — gating
 *               assignee/admin (F-95) ada di controller, pola sama
 *               StoreAttachmentRequest/UpdateTaskStatusRequest (satu tempat saja
 *               yang menegakkan). withValidator() menolak task yang SUDAH selesai
 *               (tidak ada gunanya perpanjang deadline task yang sudah DONE) dan
 *               requested_due_date yang MUNDUR dari due_date task SAAT INI
 *               (F-108, keputusan Boss H6): tenggat baru WAJIB >= due_date
 *               sekarang — SAMA DIIZINKAN (kasus "cuma nambah additional_minutes
 *               tanpa geser tenggat"), cuma MUNDUR yang ditolak.
 * ==========================================================
 */

namespace App\Http\Requests\DeadlineExtension;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDeadlineExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', Rule::exists('tasks', 'id')->whereNull('deleted_at')],
            'requested_due_date' => ['required', 'date'],
            'additional_minutes' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'],
            // Revisi 2026-08-06 item 4: evidence_type NULLABLE (beda dari
            // StoreAttachmentRequest::content_type yang WAJIB) -- evidence
            // TETAP seluruhnya opsional (F-49), null = tidak melampirkan.
            'evidence_type' => ['nullable', Rule::in(['file', 'link', 'text'])],
            'evidence_file' => ['required_if:evidence_type,file', 'file', 'mimes:pdf,jpg,jpeg,png,docx,xlsx,zip', 'max:10240'],
            'evidence_url' => ['required_if:evidence_type,link', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'evidence_text' => ['required_if:evidence_type,text', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $task = Task::with('taskStatus')->find($this->input('task_id'));

            if (! $task) {
                return;
            }

            if ($task->taskStatus->is_completed) {
                $validator->errors()->add('task_id', 'Task ini sudah selesai — tidak bisa diajukan perpanjangan.');
            }

            $requestedDueDate = $this->input('requested_due_date');

            // F-108: tolak MUNDUR (<), izinkan SAMA (=) — beda dari draf awal H6
            // yang keliru menolak keduanya lewat <=. Diperbaiki H7 setelah
            // dikonfrontasi ulang dengan keputusan Boss di penutup catatan H6.
            if ($requestedDueDate && $task->due_date && strtotime($requestedDueDate) < $task->due_date->timestamp) {
                $validator->errors()->add('requested_due_date', 'Tenggat baru tidak boleh mundur dari tenggat task saat ini (F-108).');
            }
        });
    }
}
