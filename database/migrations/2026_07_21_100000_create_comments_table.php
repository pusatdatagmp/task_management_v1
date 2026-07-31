<?php

/**
 * ==========================================================
 * MODUL       : 2026_07_21_100000_create_comments_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Diskusi per task (v1.0 H3, F-113) — komentar user + @mention.
 *               TABEL SENDIRI, terpisah dari `activity_logs` (F-113): log adalah
 *               jejak audit murni (F-51, sumber 4/6 metrik KPI), isi obrolan user
 *               BUKAN data audit — mencampurnya mencemari sumber KPI.
 * DIPANGGIL   : CommentController, CommentObserver (F-114 notif mention)
 * MEMANGGIL   : organizations, tasks, users (user_id penulis)
 * DATA MASUK  : Form komentar member/admin (project member, F-95)
 * DATA KELUAR : `mentioned_user_ids` dipakai CommentObserver kirim notifikasi
 *               kolaborasi (F-114) + diff saat edit (C4)
 * RISIKO      : `deleted_at` (F-115) — hapus komentar WAJIB soft delete (penulis
 *               sendiri saja, dijaga di controller). Hard delete DILARANG: komentar
 *               adalah percakapan kerja, riwayatnya tetap berharga untuk audit
 *               walau bukan sumber KPI seperti activity_log.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5
            $table->foreignId('task_id')->constrained();
            $table->foreignId('user_id')->constrained(); // penulis

            $table->text('body'); // teks biasa, v1.0 (A3 — rich text komentar ditunda v1.1)
            // SUMBER: hasil parse token @[Nama](id) di body SAAT SIMPAN (CommentController),
            // sudah difilter HANYA user_id yang benar-benar member project (C1). Dipakai
            // CommentObserver kirim notif (F-114) tanpa parsing ulang tiap kali dibaca,
            // dan untuk diff mention lama-vs-baru saat edit (C4).
            $table->json('mentioned_user_ids')->nullable();

            $table->softDeletes(); // F-115: hapus = tandai, bukan buang baris
            $table->timestamps(); // updated_at != created_at -> UI tandai "diedit"

            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
