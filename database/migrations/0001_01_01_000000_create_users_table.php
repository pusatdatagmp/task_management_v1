<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000000_create_users_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Tabel user internal tim (±10 orang, F-5 tenant-aware). Diedit dari
 *               stub bawaan starter kit untuk menambah kolom bisnis (role, kapasitas,
 *               status aktif) — bukan file baru, supaya urutan 17-migration tetap sesuai
 *               02-DATA-MODEL §6 posisi ke-4.
 * DIPANGGIL   : LoginRequest::authenticate() (cek is_active), seluruh relasi assignee/reviewer
 * MEMANGGIL   : organizations (FK)
 * DATA MASUK  : Seeder Hari-1, form CRUD user (Hari-2, belum dibuat)
 * DATA KELUAR : role/employment_type dipakai matriks permission (03-BUSINESS-FLOW §6),
 *               daily_capacity_minutes dipakai rumus KAPASITAS (02-DATA-MODEL §5)
 * RISIKO      : organization_id salah/kosong = bug keamanan (F-15) — user bisa
 *               menembus data organisasi lain lewat query tanpa scope.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5

            $table->string('name', 120);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // BUSINESS RULE: role dipakai matriks permission F-29 (member tidak bisa
            // buat task / geser due_date / approve). employment_type = hook v3.0 freelance.
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->enum('employment_type', ['internal', 'freelance'])->default('internal');

            // SUMBER : 02-DATA-MODEL §3.4 — NULL berarti pakai default work_schedules.
            // DIPAKAI: rumus KAPASITAS (02-DATA-MODEL §5) sebagai override per user.
            $table->smallInteger('daily_capacity_minutes')->nullable();

            // F-16: nonaktifkan user TIDAK boleh hapus baris (data KPI). is_active=false
            // hanya memblokir login (03-BUSINESS-FLOW §7), riwayat task tetap utuh.
            $table->boolean('is_active')->default(true);

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); // F-16
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
