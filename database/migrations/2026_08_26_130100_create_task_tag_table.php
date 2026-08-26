<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_26_130100_create_task_tag_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Pivot multi-tag per Task (permintaan Boss 2026-08-26, pola PERSIS
 *               task_user — lihat header migration itu untuk rationale F-5).
 * DIPANGGIL   : Task::tags(), Tag::tasks()
 * MEMANGGIL   : tasks, tags
 * DATA MASUK  : Form Task CRUD (tags[] dipilih dari katalog Tag organisasi)
 * DATA KELUAR : -
 * RISIKO      : SUMBER : keputusan Boss 2026-08-26 — hapus Tag (TagController::destroy())
 *               otomatis melepas tag itu dari SEMUA task yang memakainya (BUKAN
 *               ditolak seperti TaskStatus::destroy()) — cascadeOnDelete() di
 *               tag_id yang menegakkan ini di level DB, bukan query manual
 *               detach() terpisah yang bisa lupa dipanggil.
 *
 * CATATAN F-5 : SENGAJA tidak punya organization_id — tenant ikut task_id (yang
 *               sudah scoped organization_id sendiri), pola sama task_user.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_tag');
    }
};
