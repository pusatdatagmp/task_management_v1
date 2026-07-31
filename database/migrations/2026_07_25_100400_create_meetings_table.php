<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_25_100400_create_meetings_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Fitur baru F-124 (integrasi mockup v1.7, ikon meeting di kalender).
 *               Admin buat, invite member sbg peserta (lihat meeting_user), project
 *               OPSIONAL (rapat lintas-proyek tetap sah). Notifikasi undangan masuk
 *               kategori KOLABORASI (keluarga F-114), BUKAN trigger lifecycle ke-11
 *               — F-35 "10 trigger" tetap utuh. CRUD/notif/integrasi kalender = H6,
 *               hari ini cuma skema.
 * DIPANGGIL   : (belum — MeetingController dibangun H6)
 * MEMANGGIL   : organizations, projects (nullable), users (created_by)
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : project_id NULLABLE secara sengaja — kalau di-constrained NOT NULL,
 *               memaksa tiap meeting terikat 1 proyek, padahal rapat lintas-tim/
 *               non-proyek adalah kasus yang eksplisit disebut Boss.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('project_id')->nullable()->constrained(); // F-124: opsional

            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();

            $table->index(['organization_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
