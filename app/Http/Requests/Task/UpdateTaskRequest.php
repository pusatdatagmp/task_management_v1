<?php

/**
 * ==========================================================
 * MODUL       : UpdateTaskRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi edit task (admin only, F-29). Field yang sama dengan Store,
 *               ditambah guard supaya task tidak bisa jadi subtask dirinya sendiri
 *               atau subtask dari task yang sudah punya subtask sendiri.
 * DIPANGGIL   : TaskController::update()
 * MEMANGGIL   : Task::booted() guard subtask 1-level
 * DATA MASUK  : Form edit task — project & task dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskController::update()
 * RISIKO      : SUMBER : F-20 — Task::booted() cuma cek parent DARI CALON PARENT.
 *               Kalau task ini SENDIRI sudah punya children dan dijadikan subtask
 *               task lain, children-nya diam-diam jadi 2 level (celah yang tidak
 *               ditangkap guard model) — makanya ada rule 'tidak boleh sudah punya
 *               children' di bawah.
 *               BUG FIX (2026-08-08, permintaan Boss): task_type task HASIL
 *               RECURRING (task_template_id terisi) BERNILAI daily/weekly/monthly
 *               (disalin dari jadwal Template) -- tapi rule ['tentative','project']
 *               di bawah cuma mengizinkan 2 nilai itu. SEBELUM fix ini, edit task
 *               recurring APA PUN (termasuk cuma ganti judul) SELALU gagal validasi
 *               `task_type` -- Laravel menolak SELURUH request sekali salah 1 field,
 *               jadi judul pun ikut tidak tersimpan. Field ini SEKARANG dikunci
 *               read-only di frontend utk task recurring (tasks/edit.tsx) dan
 *               DIABAIKAN TOTAL di TaskController::update() (bukan sekadar
 *               dilonggarkan validasinya) -- rule 'nullable' di sini murni supaya
 *               request TIDAK GAGAL kalau field ini tidak dikirim/dikirim apa saja,
 *               nilai aslinya di DB tidak pernah tersentuh oleh form ini lagi.
 * ==========================================================
 */

namespace App\Http\Requests\Task;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('task.manage'); // F-90
    }

    public function rules(): array
    {
        $project = $this->route('project');
        $task = $this->route('task');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'task_type' => $task->task_template_id
                ? ['nullable']
                : ['required', Rule::in(['tentative', 'project'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            // F-122/F-126: lihat StoreTaskRequest — nullable, jangan paksa p4.
            'priority_quadrant' => ['nullable', Rule::in(['p1', 'p2', 'p3', 'p4'])],
            'estimated_minutes' => ['required', 'integer', 'min:1'],
            'points' => ['required', 'integer', 'min:0'],
            'due_date' => ['required', 'date'],
            'assignees' => ['nullable', 'array'],
            'assignees.*' => [Rule::exists('project_user', 'user_id')->where('project_id', $project->id)],
            'parent_task_id' => [
                'nullable',
                Rule::exists('tasks', 'id')->where('project_id', $project->id)->whereNull('deleted_at'),
                Rule::notIn([$task->id]),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $parentId = $this->input('parent_task_id');
            $task = $this->route('task');

            if (! $parentId) {
                return;
            }

            $parentIsSubtask = Task::whereKey($parentId)->whereNotNull('parent_task_id')->exists();

            if ($parentIsSubtask) {
                $validator->errors()->add('parent_task_id', 'Subtask maksimal 1 level (F-20) — task yang dipilih sudah jadi subtask.');
            }

            if ($task->children()->exists()) {
                $validator->errors()->add('parent_task_id', 'Task ini sudah punya subtask sendiri — tidak bisa dijadikan subtask task lain (F-20).');
            }
        });
    }
}
