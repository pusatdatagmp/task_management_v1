<?php

/**
 * ==========================================================
 * MODUL       : Role
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : RBAC (F-88) — role per organisasi. `admin`/`member` adalah baris
 *               `is_system=true` di sini, perilakunya identik dengan enum lama —
 *               yang berubah cuma mekanismenya.
 * DIPANGGIL   : User::role(), UserService::onboardNewUser (Fase C), UI Role
 *               Management (Fase E), Gate (Fase B4)
 * MEMANGGIL   : Organization, Permission (many-to-many via role_permission), User
 * DATA MASUK  : Migration backfill (2026_07_18_100400), seeder (Fase F1), UI Role Management
 * DATA KELUAR : User::role_id, Gate::check() lewat permissions()
 * RISIKO      : SUMBER : F-5/F-15 — pakai BelongsToOrganization, WAJIB tenant-scoped
 *               (role organisasi A tidak boleh terlihat/kepakai organisasi B). BEDA
 *               dari Permission (kamus GLOBAL, lihat App\Models\Permission) — Role
 *               adalah DATA tenant (siapa yang boleh apa DI organisasi ini),
 *               Permission adalah KAMUS sistem (apa saja yang BISA diberikan).
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'organization_id',
        'role_name',
        'is_system',
        'is_default',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * KONTRAK: true kalau role ini punya permission tertentu. DIPAKAI:
     * User::hasPermission() (Fase B3) — satu tempat cek, bukan
     * `$role->permissions->contains(...)` berulang di banyak file.
     *
     * WORKAROUND F-85: pakai `$this->permissions` (koleksi, ter-cache di model
     * setelah load pertama), BUKAN `$this->permissions()->where(...)->exists()`
     * (query fresh ke DB tiap panggil). Satu request Inertia biasa memanggil
     * hasPermission()/can() berkali-kali (props, gating tombol, middleware) —
     * versi query akan jadi N+1 kalau tidak di-cache di collection.
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions->contains('permission_name', $permissionName);
    }

    /**
     * KONTRAK: true kalau MENGECUALIKAN permission tertentu dari $excludingRoleId
     * akan membuat organisasi ini kehilangan SEMUA pemegang permission itu.
     * DIPAKAI: RoleController::update() (E1 — role sistem 'admin' tidak boleh
     * dilucuti user.manage kalau dia satu-satunya pemegang, org terkunci
     * selamanya dari halaman kelola user/role). Pola sama
     * TaskStatus::wouldLeaveNoWorkState() (Hari-5).
     */
    public static function wouldLeaveNoHolderOfPermission(int $organizationId, string $permissionName, int $excludingRoleId): bool
    {
        return ! static::where('organization_id', $organizationId)
            ->whereKeyNot($excludingRoleId)
            ->whereHas('permissions', fn ($q) => $q->where('permission_name', $permissionName))
            ->exists();
    }
}
