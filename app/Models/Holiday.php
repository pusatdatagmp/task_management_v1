<?php

/**
 * ==========================================================
 * MODUL       : Holiday
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Kalender libur. Model disiapkan Hari-1, tabel dibiarkan KOSONG
 *               sampai v0.8 (F-43).
 * DIPANGGIL   : (belum dipakai — v0.8)
 * MEMANGGIL   : Organization
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'organization_id',
        'date',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
