<?php

/**
 * ==========================================================
 * MODUL       : FilterTaskRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi query string filter/sort task per project (Hari-5 §C).
 *               Semua nilai di-whitelist supaya query builder di controller tidak
 *               pernah menerima input bebas (mis. kolom sort sembarangan).
 * DIPANGGIL   : TaskController::index()
 * MEMANGGIL   : -
 * DATA MASUK  : Query string GET (status[], assignee[], priority[], due, sort, direction)
 * DATA KELUAR : Data tervalidasi -> TaskController::index() (dipakai bangun query + di-echo balik ke URL)
 * RISIKO      : `sort` WAJIB whitelist kolom yang benar-benar ada & aman di-ORDER BY —
 *               tanpa ini, query string bisa dipakai untuk ORDER BY kolom sembarang.
 * ==========================================================
 */

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'array'],
            'status.*' => ['integer'],
            'assignee' => ['nullable', 'array'],
            'assignee.*' => ['integer'],
            'priority' => ['nullable', 'array'],
            'priority.*' => [Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due' => ['nullable', Rule::in(['today', 'this_week', 'overdue', 'all'])],
            'sort' => ['nullable', Rule::in(['due_date', 'priority', 'points', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
