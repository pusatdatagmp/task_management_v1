<?php

/**
 * ==========================================================
 * MODUL       : User
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Tim internal (F-5 tenant-aware). Diedit dari stub starter kit untuk
 *               menambah kolom bisnis (role, kapasitas, status aktif) + relasi ERD.
 *               F-88/F-90 — enum `role` PENSIUN, diganti `role_id` -> Role (RBAC).
 * DIPANGGIL   : LoginRequest, seluruh relasi assignee/reviewer/approver, Gate (Fase B4)
 * MEMANGGIL   : Organization, Project, Task, TaskTimeSegment, ActivityLog, Role
 * DATA MASUK  : Seeder Hari-1, UserService::onboardNewUser (Fase C)
 * DATA KELUAR : role_id -> Role::permissions() dipakai matriks permission (F-90)
 * RISIKO      : F-15 — model ini pakai BelongsToOrganization juga. Saat Auth::attempt()
 *               di proses login, Auth::check() masih false sehingga scope TIDAK memfilter
 *               query pencarian kredensial (aman, tidak memblokir login).
 * ==========================================================
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SerializesDatesInAppTimezone;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToOrganization, HasFactory, Notifiable, SerializesDatesInAppTimezone, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
        'role_id',
        'employment_type',
        'daily_capacity_minutes',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function approvedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'approved_by');
    }

    public function timeSegments(): HasMany
    {
        return $this->hasMany(TaskTimeSegment::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * KONTRAK: true kalau role user ini punya permission tertentu (F-90 — SATU-
     * SATUNYA cara resmi cek izin, dipakai Gate (Fase B4) lewat $user->can(...)).
     *
     * GUARD: loadMissing('role.permissions') di baris pertama, BUKAN opsional —
     * F-85 (preventLazyLoading aktif non-produksi) akan MELEMPAR exception kalau
     * $this->role diakses tanpa eager-load lebih dulu, dan method ini dipanggil
     * BERKALI-KALI per request (Gate, FormRequest::authorize(), props Inertia).
     * loadMissing() no-op kalau relasi sudah ter-load (mis. dari middleware/Gate
     * yang sudah load duluan) — jadi aman dipanggil berulang tanpa N+1.
     */
    public function hasPermission(string $permissionName): bool
    {
        $this->loadMissing('role.permissions');

        return (bool) $this->role?->hasPermission($permissionName);
    }
}
