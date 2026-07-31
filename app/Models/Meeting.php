<?php

/**
 * ==========================================================
 * MODUL       : Meeting
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Fitur baru F-124 (integrasi mockup v1.7). Admin buat, invite member
 *               sbg peserta, project OPSIONAL. Notifikasi undangan = kategori
 *               KOLABORASI (keluarga F-114), BUKAN trigger lifecycle F-35. Hari ini
 *               (v1.2 H2) cuma model+relasi — CRUD/notif/kalender dibangun H6.
 * DIPANGGIL   : (belum — MeetingController H6)
 * MEMANGGIL   : Organization, Project (nullable), User (creator + participants)
 * DATA MASUK  : Form meeting admin (H6)
 * DATA KELUAR : Ikon di Master Calendar dashboard (H6)
 * RISIKO      : Belum ada observer/notifikasi di sini — undang peserta hari ini
 *               TIDAK memicu apa pun (H6 yang menyambungkan). participants() pakai
 *               pivot default (bukan model custom) untuk sekarang; kalau H6 butuh
 *               event attach/detach (pola TaskUser/ProjectUser), tabel meeting_user
 *               SUDAH punya kolom id sendiri (lihat migration) supaya tidak perlu
 *               migrasi ulang.
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Meeting extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'organization_id',
        'project_id',
        'title',
        'description',
        'start_at',
        'end_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_user');
    }
}
