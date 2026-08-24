<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_22_100000_add_nickname_to_users_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Permintaan Boss (2026-08-22) — nama lengkap user sering kepanjangan
 *               di tampilan sempit (sidebar, badge assignee). Kolom OPSIONAL supaya
 *               admin bisa isi "nama panggilan" pendek per user; kalau kosong,
 *               tampilan TETAP pakai `name` penuh (fallback, lihat User::display_name).
 * DIPANGGIL   : (belum — User model/UserController/frontend disambungkan di
 *               perubahan yang sama)
 * MEMANGGIL   : users (harus sudah ada)
 * DATA MASUK  : -
 * DATA KELUAR : -
 * RISIKO      : NULLABLE, TANPA default — user lama otomatis NULL (fallback ke
 *               `name`, bukan string kosong yang tampil sebagai nama blank).
 *               Aditif murni (F-121), tidak mengubah/menghapus kolom `name` lama.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname', 60)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nickname');
        });
    }
};
