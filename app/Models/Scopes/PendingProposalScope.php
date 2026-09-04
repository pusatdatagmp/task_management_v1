<?php

/**
 * ==========================================================
 * MODUL       : PendingProposalScope
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-186 — sembunyikan task berstatus proposal_status='pending' dari
 *               SEMUA query Task secara default (keputusan Boss: pending proposal
 *               "disembunyikan total" dari List/Board/Semua Tugas/Dashboard/
 *               Leaderboard/Search sampai admin memutuskan).
 * DIPANGGIL   : App\Models\Task::booted() (addGlobalScope)
 * MEMANGGIL   : -
 * DATA MASUK  : tasks.proposal_status
 * DATA KELUAR : Constraint WHERE ditambahkan ke SEMUA query builder Task
 * RISIKO      : SUMBER : dipilih GLOBAL SCOPE (pola sama OrganizationScope, F-15)
 *               alih-alih menambah ->where() manual di tiap titik query (index/all/
 *               myTasks/board/dashboard/leaderboard/search — 10+ tempat) — SATU
 *               tempat lupa pasang saja = task mentah bocor ke listing. Aman-secara-
 *               default: dua halaman yang SENGAJA butuh lihat pending (antrean admin
 *               & riwayat pengajuan member) WAJIB withoutGlobalScope() eksplisit,
 *               lihat TaskProposalController.
 *               WORKAROUND: dibungkus SATU closure where() supaya jadi satu grup
 *               AND — kalau ->orWhere() dipasang telanjang di top-level query, dia
 *               OR dengan SELURUH constraint lain yang sudah ada (termasuk
 *               OrganizationScope!), bisa membocorkan task org lain.
 * ==========================================================
 */

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class PendingProposalScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $column = $model->qualifyColumn('proposal_status');

        $builder->where(function (Builder $query) use ($column) {
            $query->whereNull($column)->orWhere($column, '!=', 'pending');
        });
    }
}
