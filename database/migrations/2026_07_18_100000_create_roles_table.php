<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_18_100000_create_roles_table
 * KLASIFIKASI : DATA
 * TUJUAN      : RBAC (F-88) — role per organisasi. `admin`/`member` PENSIUN dari
 *               enum `users.role` dan jadi BARIS di sini (`is_system=true`) — satu
 *               sumber kebenaran izin, bukan enum + RBAC berdampingan (F-90).
 * DIPANGGIL   : Role model, User::role() (Fase B), UserService::onboardNewUser (Fase C)
 * MEMANGGIL   : organizations, users (created_by — lihat RISIKO soal circular FK)
 * DATA MASUK  : Migration backfill (2026_07_18_100400), seeder (F1), UI Role Management (Fase E)
 * DATA KELUAR : role_id di users, role_permission
 * RISIKO      : SUMBER : F-5 — organization_id WAJIB, role TIDAK boleh lintas tenant.
 *               UNIQUE(organization_id, role_name) — nama role boleh sama di organisasi
 *               BEDA (mis. dua tenant sama-sama punya "Supervisor"), tapi tidak boleh
 *               dobel DALAM satu organisasi yang sama.
 *               `created_by` REFERENCES users(id) — BUKAN circular FK ala F-54
 *               (work_schedules.created_by): `users` sudah ada sejak Hari-1
 *               (migration 0001_01_01_000000), jauh SEBELUM migration ini, jadi FK
 *               langsung aman di sini. Circular yang sebenarnya ada di arah lain
 *               (users.role_id -> roles.id) — itu sebabnya kolom itu ditambah di
 *               migration TERPISAH setelah tabel ini (2026_07_18_100300), bukan di sini.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5

            $table->string('role_name', 60);

            // BUSINESS RULE: F-88 — role sistem (admin/member) TIDAK BISA dihapus atau
            // di-rename lewat UI Role Management (Fase E). Perilakunya identik dengan
            // enum hari ini, cuma mekanismenya pindah ke baris tabel.
            $table->boolean('is_system')->default(false);

            // BUSINESS RULE: F-74-style — "tepat 1 default per organisasi" ditegakkan
            // di APPLICATION layer (transaction: set semua false, set 1 true), BUKAN
            // partial unique index — pola sama task_statuses.is_completed
            // (TaskStatusController::updateFlags()). MySQL tidak punya partial unique
            // index senyaman constraint biasa, dan pola aplikasi ini sudah terbukti.
            $table->boolean('is_default')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['organization_id', 'role_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
