<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_06_115131_add_content_type_link_text_to_attachments_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Revisi Boss 2026-08-06 item 4 — Lampiran Output & Evidence
 *               SEBELUM ini file-only. Kolom baru izinkan 3 mode: upload file
 *               (perilaku lama), sematkan link (URL eksternal, mis. Drive/Figma),
 *               atau tulis teks panjang langsung.
 * DIPANGGIL   : Attachment::storeUploadedFile()/storeLink()/storeText()
 * MEMANGGIL   : attachments.file_path/file_name/file_size/mime_type (raw ALTER —
 *               doctrine/dbal TIDAK terpasang di project ini, ->change() Blueprint
 *               butuh dependency itu; pola SAMA PERSIS migrasi AE-3 reason->TEXT)
 * DATA MASUK  : -
 * DATA KELUAR : attachments.content_type/url/body (baru, aditif) + 4 kolom file
 *               lama jadi nullable (link/text tak punya file fisik)
 * RISIKO      : content_type default 'file' -- SELURUH baris lama (upload file
 *               existing) otomatis benar tanpa backfill manual (F-121 spirit,
 *               aditif murni). down() mengembalikan 4 kolom file ke NOT NULL --
 *               HANYA aman kalau rollback SEBELUM ada baris type=link/text
 *               tersimpan (kalau ada, MySQL menolak downgrade dengan data
 *               truncation error, bukan diam-diam merusak data — pola sama
 *               peringatan migrasi AE-3).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->enum('content_type', ['file', 'link', 'text'])->default('file')->after('type');
            $table->string('url', 2048)->nullable()->after('content_type');
            $table->longText('body')->nullable()->after('url');
        });

        DB::statement('ALTER TABLE attachments MODIFY file_path VARCHAR(255) NULL');
        DB::statement('ALTER TABLE attachments MODIFY file_name VARCHAR(255) NULL');
        DB::statement('ALTER TABLE attachments MODIFY file_size INT NULL');
        DB::statement('ALTER TABLE attachments MODIFY mime_type VARCHAR(100) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attachments MODIFY file_path VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE attachments MODIFY file_name VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE attachments MODIFY file_size INT NOT NULL');
        DB::statement('ALTER TABLE attachments MODIFY mime_type VARCHAR(100) NOT NULL');

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['content_type', 'url', 'body']);
        });
    }
};
