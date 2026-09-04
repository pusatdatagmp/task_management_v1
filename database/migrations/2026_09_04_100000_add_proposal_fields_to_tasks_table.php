<?php

/**
 * ==========================================================
 * MODUL       : 2026_09_04_100000_add_proposal_fields_to_tasks_table
 * KLASIFIKASI : DATA
 * TUJUAN      : F-186 (keputusan Boss 2026-09-04) — member boleh mengajukan task
 *               baru untuk dirinya sendiri, admin approve/reject. Membalik SEBAGIAN
 *               F-29 ("member tidak boleh buat task") secara sadar, bukan celah.
 *               Kolom NEMPEL di `tasks` (bukan tabel terpisah seperti
 *               deadline_extensions) karena baris pengajuan DAN baris task hasil
 *               approve adalah ENTITAS YANG SAMA — task tidak "lahir ulang" saat
 *               disetujui, cuma proposal_status-nya yang berubah.
 * DIPANGGIL   : App\Observers\TaskObserver (notifikasi), App\Http\Controllers\TaskProposalController
 * MEMANGGIL   : users (proposal_reviewed_by)
 * DATA MASUK  : Form "Ajukan Tugas" member, form approve/reject admin
 * DATA KELUAR : tasks.proposal_status dibaca App\Models\Scopes\PendingProposalScope
 *               (global scope, SEMUA listing task otomatis menyembunyikan baris
 *               'pending' — lihat header scope itu untuk alasan dipilih global
 *               scope, bukan ->where() manual di tiap query)
 * RISIKO      : proposal_status NULL = task NORMAL (admin-created ATAU proposal
 *               yang SUDAH di-approve — approve() mengembalikan kolom ini ke NULL,
 *               bukan 'approved', supaya task itu balik jadi task biasa 100% tanpa
 *               nilai enum ketiga yang harus terus dijaga di semua query). 'pending'
 *               = menunggu admin. 'rejected' = ditolak, baris ini SELALU dipasangkan
 *               dengan soft-delete (F-16) di request yang sama — tidak pernah berdiri
 *               sendiri tanpa deleted_at terisi.
 * ==========================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('proposal_status', ['pending', 'rejected'])->nullable()->after('created_by');
            $table->foreignId('proposal_reviewed_by')->nullable()->after('proposal_status')->constrained('users');
            $table->dateTime('proposal_reviewed_at')->nullable()->after('proposal_reviewed_by');
            $table->text('proposal_review_note')->nullable()->after('proposal_reviewed_at');

            // DIPAKAI: TaskProposalController::index() (antrean admin) — satu-
            // satunya query yang benar-benar filter WHERE proposal_status='pending'
            // secara rutin (lihat header PendingProposalScope kenapa scope global
            // TIDAK butuh index terpisah untuk kasusnya sendiri).
            $table->index('proposal_status');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['proposal_reviewed_by']);
            $table->dropColumn(['proposal_status', 'proposal_reviewed_by', 'proposal_reviewed_at', 'proposal_review_note']);
        });
    }
};
