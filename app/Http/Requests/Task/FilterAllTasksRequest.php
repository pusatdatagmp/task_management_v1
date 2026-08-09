<?php

/**
 * ==========================================================
 * MODUL       : FilterAllTasksRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi query string filter/sort halaman "Semua Tugas" (F-140/F-144,
 *               v1.2 H7b) — lintas SEMUA project, beda dari FilterTaskRequest yang
 *               scope-nya 1 project. `status_flag` SENGAJA bukan task_status_id mentah
 *               (F-44) — task_statuses per-project (beda project bisa beda nama/jumlah
 *               status), jadi filter "status" lintas-project dipetakan lewat FLAG
 *               (is_work_state/is_review/is_completed) yang seragam di semua project.
 * DIPANGGIL   : TaskController::all()
 * MEMANGGIL   : -
 * DATA MASUK  : Query string GET (project_id, status_flag[], assignee[], task_type[],
 *               priority_quadrant[], due, sort, direction)
 * DATA KELUAR : Data tervalidasi -> TaskController::all()
 * RISIKO      : `sort`/`status_flag`/`task_type`/`priority_quadrant` WAJIB whitelist —
 *               sama alasan FilterTaskRequest, mencegah ORDER BY/WHERE kolom sembarang.
 *               2026-08-08 (permintaan Boss): 'title'/'project'/'assignee' ditambah
 *               ke whitelist sort -- 'project'/'assignee' diimplementasikan via
 *               subquery korelasi di TaskController::all() (bukan kolom langsung
 *               tasks.*), lihat komentar di sana untuk alasan pemilihan pendekatannya.
 * ==========================================================
 */

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterAllTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // SUMBER: nilai mentah dipercaya begitu saja ke where('project_id', ...) —
            // aman walau project_id milik organisasi lain, karena Task sudah di-scope
            // OrganizationScope (F-15), hasilnya cuma nol baris, bukan bocor data.
            'project_id' => ['nullable', 'integer'],
            'status_flag' => ['nullable', 'array'],
            'status_flag.*' => [Rule::in(['todo', 'in_progress', 'review', 'completed'])],
            'assignee' => ['nullable', 'array'],
            'assignee.*' => ['integer'],
            // Revisi 2026-08-07 (permintaan Boss): daily/weekly/monthly dicabut
            // dari whitelist -- checkbox filter untuk 3 itu sudah dihapus di FE
            // (tasks/all.tsx), nilai task_type task hasil generate sekarang teks
            // bebas ringkasan jadwal (mis. "Tiap 3 hari", TaskTemplate::scheduleLabel())
            // yang tidak ada UI filter-nya. tentative/project TETAP kategori tetap
            // untuk task manual (bukan hasil generate).
            'task_type' => ['nullable', 'array'],
            'task_type.*' => [Rule::in(['tentative', 'project'])],
            // F-139: quadrant BARU, gantikan enum priority lama di filter/sort halaman ini.
            'priority_quadrant' => ['nullable', 'array'],
            'priority_quadrant.*' => [Rule::in(['p1', 'p2', 'p3', 'p4'])],
            'due' => ['nullable', Rule::in(['today', 'this_week', 'overdue', 'all'])],
            'sort' => ['nullable', Rule::in(['due_date', 'priority_quadrant', 'points', 'created_at', 'title', 'project', 'assignee'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
