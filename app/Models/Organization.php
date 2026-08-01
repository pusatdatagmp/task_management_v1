<?php

/**
 * ==========================================================
 * MODUL       : Organization
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Akar tenant (F-5). SATU-SATUNYA model bisnis yang TIDAK memakai
 *               BelongsToOrganization — dia sendiri yang jadi jangkar organization_id.
 * DIPANGGIL   : Seluruh model bisnis via relasi organization()
 * MEMANGGIL   : -
 * DATA MASUK  : Seeder Hari-1 (1 organization)
 * DATA KELUAR : id dipakai sebagai organization_id di semua tabel bisnis
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Organization extends Model
{
    use HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'name',
        'slug',
        // F-142 (v1.2 DS-2): custom branding, org-scoped (F-5) sejak tabel akar —
        // company_name SENGAJA terpisah dari `name` (identitas tenant internal,
        // dipakai Role::firstOrCreate dkk, tak pernah tampil di UI mana pun).
        'logo_path',
        'company_name',
        'address',
        'wa_number',
        'facebook_url',
        'instagram_url',
        'linkedin_url',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * KONTRAK: satu sumber derivasi URL logo (F-142) -- dipakai SettingsController
     * (form edit) DAN HandleInertiaRequests (share global tiap halaman, F-85),
     * supaya aturan "disk public, null kalau belum upload" tidak drift di 2 tempat.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
