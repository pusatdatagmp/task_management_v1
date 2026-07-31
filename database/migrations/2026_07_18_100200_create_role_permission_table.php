<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_18_100200_create_role_permission_table
 * KLASIFIKASI : DATA
 * TUJUAN      : RBAC (F-88) — pivot role<->permission. Menentukan izin KONKRET
 *               yang dimiliki satu role (tenant-scoped lewat role_id -> roles.organization_id).
 * DIPANGGIL   : Role::permissions() (Fase B), User::hasPermission() (Fase B3)
 * MEMANGGIL   : roles, permissions
 * DATA MASUK  : Seeder (Fase F1 — admin dapat SEMUA permission, member subset),
 *               UI Role Management (Fase E — admin centang/hapus permission per role)
 * DATA KELUAR : Gate::check() (Fase B4)
 * RISIKO      : cascadeOnDelete() di role_id — kalau role dihapus, baris pivot ikut
 *               hilang (aman, bukan pelanggaran F-16: pivot BUKAN data KPI, cuma
 *               konfigurasi akses). Role sistem sendiri TIDAK BISA dihapus (Fase E1),
 *               jadi cascade ini hanya berlaku untuk role custom.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
    }
};
