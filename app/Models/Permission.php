<?php

/**
 * ==========================================================
 * MODUL       : Permission
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : RBAC (F-88) — kamus izin GLOBAL sistem (mis. `task.manage`).
 * DIPANGGIL   : Role::permissions(), seeder (Fase F1), UI Role Management (Fase E)
 * MEMANGGIL   : Role (many-to-many via role_permission)
 * DATA MASUK  : Seeder — kamus ini ditulis SEKALI, bukan dibuat lewat UI (beda
 *               dari Role yang bisa dibuat admin kapan saja)
 * DATA KELUAR : Gate::check() (Fase B4)
 * RISIKO      : SUMBER : TIDAK pakai BelongsToOrganization/OrganizationScope
 *               (F-5/F-15) SENGAJA — permission adalah kamus sistem, sama untuk
 *               semua tenant, BUKAN data milik satu organisasi. Lihat komentar
 *               lengkap di migration 2026_07_18_100100_create_permissions_table.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'permission_name',
        'module',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
