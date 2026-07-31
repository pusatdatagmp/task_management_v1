<?php

/**
 * ==========================================================
 * MODUL       : BelongsToOrganization
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Trait wajib di semua model bisnis (F-5/F-15) — pasang global scope
 *               tenant-isolation dan otomatis isi organization_id saat record dibuat
 *               dari request yang sudah login, supaya controller tidak perlu ingat
 *               menulisnya manual di setiap tempat (rawan lupa = bug keamanan).
 * DIPANGGIL   : Semua model bisnis (Project, Task, TaskStatus, dll — bukan Organization sendiri)
 * MEMANGGIL   : App\Models\Scopes\OrganizationScope, App\Models\Organization
 * DATA MASUK  : Auth::user()->organization_id saat model::creating
 * DATA KELUAR : Kolom organization_id terisi otomatis + relasi organization()
 * RISIKO      : Kalau trait ini tidak dipasang di model bisnis baru, model itu LOLOS
 *               dari OrganizationScope — bug keamanan F-15, bukan sekadar bug fungsional.
 * ==========================================================
 */

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Support\Facades\Auth;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            // SUMBER: konsisten dengan OrganizationScope — pakai hasUser(), bukan
            // check()/user(), supaya tidak memicu recursion saat model ini adalah User.
            if (! $model->organization_id && Auth::hasUser()) {
                $model->organization_id = Auth::user()->organization_id;
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
