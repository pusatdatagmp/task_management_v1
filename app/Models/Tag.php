<?php

/**
 * ==========================================================
 * MODUL       : Tag
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Katalog tag per-organisasi (permintaan Boss 2026-08-26) — dikelola
 *               terpusat di halaman Setelan, dipilih multi saat buat/edit Task.
 * DIPANGGIL   : TagController, Task::tags()
 * MEMANGGIL   : Organization, Task (many-to-many via task_tag)
 * DATA MASUK  : Form tab "Tag" halaman Setelan
 * DATA KELUAR : Picker tag di form Task, badge warna di halaman daftar/detail task
 * RISIKO      : SUMBER : F-5/F-15 — pakai BelongsToOrganization, WAJIB tenant-scoped
 *               (tag organisasi A tidak boleh terlihat/kepakai organisasi B). Hapus
 *               tag (TagController::destroy()) MELEPAS OTOMATIS dari semua task
 *               yang memakainya (cascadeOnDelete di migration task_tag, keputusan
 *               Boss 2026-08-26) — BUKAN ditolak seperti TaskStatus::destroy().
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use BelongsToOrganization, HasFactory, SerializesDatesInAppTimezone;

    protected $fillable = [
        'organization_id',
        'name',
        'color',
    ];

    public function tasks(): BelongsToMany
    {
        // SUMBER: lihat komentar Task::tags() -- nama tabel pivot dipaksa
        // eksplisit 'task_tag', bukan tebakan alfabetis default Eloquent.
        return $this->belongsToMany(Task::class, 'task_tag');
    }
}
