<?php

/**
 * ==========================================================
 * MODUL       : OrganizationScope
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Global scope tenant-isolation (F-15). Membatasi SEMUA query model
 *               bisnis hanya ke organization_id milik user yang sedang login.
 * DIPANGGIL   : App\Models\Concerns\BelongsToOrganization (dipasang via bootBelongsToOrganization)
 * MEMANGGIL   : Auth facade
 * DATA MASUK  : Auth::user()->organization_id
 * DATA KELUAR : Constraint WHERE organization_id = ... ditambahkan ke query builder
 * RISIKO      : SUMBER : 02-DATA-MODEL §1 F-15 — query tanpa scope ini adalah BUG
 *               KEAMANAN, bukan optimasi. Kalau scope ini bolong, user org A bisa
 *               membaca data org B (jangkar v3.0 freelance marketplace multi-tenant).
 *               Saat tidak ada user login (console/seeder), scope TIDAK memfilter apa
 *               pun — proses seeder/queue harus set organization_id sendiri secara eksplisit.
 *
 *               WORKAROUND KRITIS: pakai Auth::hasUser() (cek cache guard), BUKAN
 *               Auth::check()/Auth::user() (bisa memicu resolusi ulang). Model User
 *               sendiri PAKAI trait ini juga — kalau scope ini memanggil Auth::user()
 *               saat SessionGuard sedang di tengah retrieveById() (User::find($id)),
 *               query itu masuk lagi ke scope ini, minta Auth::user() lagi, yang belum
 *               selesai di-resolve -> resolve ulang -> RECURSION TAK BERHENTI sampai
 *               memory habis. hasUser() hanya cek properti cache tanpa memicu query.
 * ==========================================================
 */

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::hasUser()) {
            $builder->where($model->qualifyColumn('organization_id'), Auth::user()->organization_id);
        }
    }
}
