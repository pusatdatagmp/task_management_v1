<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000013_create_activity_logs_table
 * KLASIFIKASI : DATA
 * TUJUAN      : TULANG PUNGGUNG sistem (F-22/F-23/F-51) — satu-satunya sumber untuk
 *               4 dari 6 metrik KPI Boss. IMMUTABLE selamanya, tanpa update/delete.
 * DIPANGGIL   : TaskObserver/ProjectObserver/DeadlineExtensionObserver/AttachmentObserver
 * MEMANGGIL   : organizations, users (pelaku, nullable untuk aksi sistem)
 * DATA MASUK  : SETIAP perubahan Task/Project/Extension/Attachment via Eloquent Observer
 * DATA KELUAR : v1.5 Scoring Engine (derived KPI), audit trail
 * RISIKO      : SUMBER : F-51 — satu transisi lolos tidak tercatat = lubang permanen,
 *               tidak bisa direkonstruksi mundur. Kolom updated_at SENGAJA tidak dibuat
 *               (F-23: immutable, tidak ada mekanisme update baris log).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('user_id')->nullable()->constrained(); // pelaku, null = sistem

            $table->string('subject_type', 100); // morph
            $table->unsignedBigInteger('subject_id');
            $table->string('event', 50); // created/updated/status_changed/dst (02-DATA-MODEL §3.14)
            $table->json('properties')->nullable(); // WAJIB {"old":{...},"new":{...}}

            $table->timestamp('created_at')->useCurrent(); // F-23: tanpa updated_at

            $table->index(['subject_type', 'subject_id']);
            $table->index(['organization_id', 'user_id', 'created_at']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
