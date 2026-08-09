<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_08_100000_create_project_owners_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Keputusan Boss 2026-08-08 — form Project boleh pilih LEBIH DARI 1
 *               Owner (reviewer, F-28). `projects.owner_id` (FK tunggal) DIPERTAHANKAN
 *               apa adanya — sekarang cerminan otomatis owner `position=0` (owner
 *               "utama", ditentukan dari urutan pilih di form) supaya automation
 *               (GenerateRecurringTasksCommand/GenerateTaskAction, `created_by`) dan
 *               relasi Project::owner()/User::ownedProjects() TIDAK perlu berubah
 *               sama sekali.
 * DIPANGGIL   : Project::owners(), ProjectController::store()/update()
 * MEMANGGIL   : projects, users
 * DATA MASUK  : Form Project Baru/Edit (checklist multi-select Owner)
 * DATA KELUAR : ProjectController sync ke tabel ini + tulis owner position=0 ke
 *               projects.owner_id dalam transaksi yang sama
 * RISIKO      : UNIQUE(project_id, user_id) — user yang sama tidak boleh jadi owner
 *               dobel di project yang sama. `position` SENGAJA TIDAK diberi UNIQUE
 *               constraint di DB (walau logicnya harus unik per project, dijamin
 *               ProjectController::ownerSyncPayload() dari index array) -- sync()
 *               Laravel meng-INSERT baris baru SEBELUM meng-UPDATE posisi baris lama
 *               saat reorder, jadi constraint unik di sini akan bentrok sesaat di
 *               tengah satu sync() (mis. tukar posisi 2 owner). Konsistensi posisi
 *               cukup dijamin di level aplikasi, bukan DB.
 *
 * CATATAN F-5 : Sama seperti project_user (lihat migration-nya) — sengaja TIDAK
 *               punya organization_id sendiri, tenant ikut project_id.
 *
 * BACKFILL    : Project yang sudah ada SEBELUM migrasi ini otomatis dapat 1 baris
 *               project_owners (owner_id lama, position=0) — supaya tidak ada project
 *               yang tiba-tiba nol owner di UI baru.
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
        Schema::create('project_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->smallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        // BACKFILL: project existing -> 1 baris project_owners dari owner_id lama.
        DB::table('projects')->whereNotNull('owner_id')->orderBy('id')->get(['id', 'owner_id'])
            ->each(function ($project) {
                DB::table('project_owners')->insert([
                    'project_id' => $project->id,
                    'user_id' => $project->owner_id,
                    'position' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_owners');
    }
};
