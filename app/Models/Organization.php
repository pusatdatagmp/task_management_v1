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

class Organization extends Model
{
    use HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'name',
        'slug',
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
}
