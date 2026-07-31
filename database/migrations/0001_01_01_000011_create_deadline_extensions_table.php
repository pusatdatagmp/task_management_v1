<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000011_create_deadline_extensions_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Alur pengajuan perpanjangan deadline (F-50) — satu-satunya jalan legal
 *               menggeser due_date, karena F-29 melarang member ubah due_date langsung.
 * DIPANGGIL   : DeadlineExtensionObserver, notifikasi trigger #9/#10 (F-35)
 * MEMANGGIL   : organizations, tasks, users (requested_by, reviewed_by)
 * DATA MASUK  : Form pengajuan member (alasan wajib + evidence attachment)
 * DATA KELUAR : Saat approve -> tasks.original_due_date/due_date/estimated_minutes (F-47)
 * RISIKO      : Menutup celah Goodhart terbesar (F-4) — kalau alur ini dilewati/dibuat
 *               pintasan lain untuk ubah due_date, metrik on-time jadi bisa dimanipulasi.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deadline_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('task_id')->constrained();
            $table->foreignId('requested_by')->constrained('users');

            $table->dateTime('old_due_date');
            $table->dateTime('requested_due_date');
            $table->smallInteger('additional_minutes')->default(0); // tambahan budget waktu
            $table->text('reason'); // wajib diisi

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deadline_extensions');
    }
};
