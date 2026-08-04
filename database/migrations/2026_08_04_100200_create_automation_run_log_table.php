<?php

/**
 * ==========================================================
 * MODUL       : 2026_08_04_100200_create_automation_run_log_table
 * KLASIFIKASI : DATA
 * TUJUAN      : Backbone observability Automation Engine (F-159 poin 3, §6 SPEK v1.3) —
 *               tiap keputusan Guard/Strategy (GENERATE/SKIP/SHIFT/ERROR) dicatat
 *               queryable, bukan cuma log file. Tabel dulu, DIISI baru di AE-2.
 * DIPANGGIL   : (belum dipakai — AE-2 pipeline Trigger->Guard->Strategy->Resolver->Action
 *               akan INSERT 1 baris per evaluasi template per run)
 * MEMANGGIL   : organizations (F-5), task_templates
 * DATA MASUK  : -
 * DATA KELUAR : (AE-2) baris Decision object per evaluasi. (AE-4) UI riwayat automation.
 * RISIKO      : SUMBER F-5 — "organization_id di SETIAP tabel bisnis sejak baris
 *               pertama, retrofit = bongkar DB total". Draf SPEK v1.3 §2 TIDAK
 *               menyebut kolom ini secara eksplisit di daftar automation_run_log,
 *               tapi F-5 aturan permanen TIDAK BISA DINEGO berlaku ke SEMUA tabel
 *               bisnis baru tanpa perlu approval ulang per tabel. Tanpa ini,
 *               retrofit run_log lintas organisasi nanti = pola F-5 pahit lagi.
 *               task_template_id nullOnDelete (BUKAN cascade) — log riwayat run tetap
 *               ada walau template-nya suatu saat dihapus (audit trail, semangat F-23).
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_run_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained(); // F-5

            // Nullable: template bisa dihapus setelah log-nya tercatat (F-23 semangat
            // audit — log tak boleh ikut lenyap saat sumbernya dihapus).
            $table->foreignId('task_template_id')->nullable()->constrained('task_templates')->nullOnDelete();

            $table->dateTime('run_at'); // F-69: WIB eksplisit, diisi Carbon::now('Asia/Jakarta') oleh AE-2
            $table->enum('action', ['generate', 'skip', 'shift', 'error']);
            $table->string('reason')->nullable(); // mis. "belum-waktunya", "sebelumnya-belum-selesai"
            $table->date('target_date')->nullable();
            $table->integer('delta_days')->nullable(); // hasil DateDiff Time-Delta guard
            $table->json('meta')->nullable(); // detail bebas per guard/strategy, non-kontrak

            $table->timestamps();

            $table->index(['task_template_id', 'run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_run_log');
    }
};
