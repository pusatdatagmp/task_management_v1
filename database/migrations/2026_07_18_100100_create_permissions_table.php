<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_18_100100_create_permissions_table
 * KLASIFIKASI : DATA
 * TUJUAN      : RBAC (F-88) — kamus izin GLOBAL sistem (mis. `task.manage`,
 *               `task.approve`). Dipakai lintas semua tenant lewat role_permission.
 * DIPANGGIL   : Permission model, Role::permissions() (Fase B), Gate (Fase B4)
 * MEMANGGIL   : -
 * DATA MASUK  : Seeder (Fase F1) — kamus permission ditulis SEKALI, tidak dibuat user
 * DATA KELUAR : role_permission.permission_id
 * RISIKO      : SUMBER : F-5/F-15 biasanya wajib `organization_id` di SEMUA tabel
 *               bisnis — tabel ini SENGAJA DIKECUALIKAN. Permission adalah KAMUS
 *               SISTEM ("apa saja yang BISA diberikan"), sama untuk semua organisasi,
 *               bukan DATA milik satu tenant. Yang tenant-specific adalah SIAPA yang
 *               PUNYA permission itu (lewat role_permission -> roles.organization_id).
 *               Beri organization_id di sini = duplikasi kamus per tenant tanpa
 *               guna, dan permission baru (rilis fitur) harus di-insert ulang ke
 *               SETIAP organisasi alih-alih sekali untuk semua.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            // SUMBER: format "module.aksi" (mis. 'task.manage', 'task.approve') —
            // konsisten dengan penamaan yang dikonfirmasi Boss (RBAC §D1).
            $table->string('permission_name', 100)->unique();
            $table->string('module', 40);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
