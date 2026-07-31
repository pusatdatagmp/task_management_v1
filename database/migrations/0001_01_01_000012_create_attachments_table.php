<?php

/**
 * ==========================================================
 * MODUL       : 0001_01_01_000012_create_attachments_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Dua jenis lampiran (F-49) — output kerja saat submit REVIEW, dan evidence
 *               pendukung pengajuan perpanjangan deadline.
 * DIPANGGIL   : AttachmentObserver (log attachment_uploaded), form submit task/extension
 * MEMANGGIL   : organizations, tasks, deadline_extensions (nullable), users (uploaded_by)
 * DATA MASUK  : Upload file member/admin, storage lokal storage/app/private (batas v0.5)
 * DATA KELUAR : file_path dipakai render lampiran di UI task/extension (Hari-2+)
 * RISIKO      : deadline_extension_id hanya terisi bila type=evidence — kalau tertukar,
 *               bukti perpanjangan bisa nyasar tampil sebagai output kerja biasa.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('task_id')->constrained();
            $table->foreignId('deadline_extension_id')->nullable()->constrained(); // terisi bila type=evidence

            $table->enum('type', ['output', 'evidence']); // F-49
            $table->string('file_path', 255);
            $table->string('file_name', 255); // nama asli
            $table->integer('file_size'); // bytes
            $table->string('mime_type', 100);
            $table->foreignId('uploaded_by')->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
