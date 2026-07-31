# PROMPT HARI-4 — PERBAIKAN + TASK CRUD

> **Hari terpadat v0.5.** Fase A–C perbaikan (~3 jam) · Fase D–E Task CRUD (~5 jam)
> Task CRUD adalah **pintu masuk seluruh data KPI Boss**. Kalau waktu habis, korbankan Fase C — bukan Fase D/E.

---

## §0. YANG BOSS LAKUKAN DULU

Salin ulang 2 file yang Jarvis perbarui (menimpa yang lama):
```
CLAUDE.md                    <- +F-72 (trait TZ), +F-74 (radio), +F-75 (bukti browser di DoD)
docs/04-FINDING-REGISTRY.md  <- +F-72..F-75 + catatan audit Hari-3
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Dokumen DIPERBARUI Boss. Baca ulang utuh, jangan andalkan ingatan sesi lalu:
1. CLAUDE.md                    <- F-72, F-74, F-75 BARU
2. docs/02-DATA-MODEL.md        <- §3.9 tasks, §3.10 segments, §3.7 statuses
3. docs/03-BUSINESS-FLOW.md     <- §1 lifecycle, §6 permission, §8 alur task CRUD
4. docs/04-FINDING-REGISTRY.md  <- F-72, F-73, F-74, F-75

LAPORKAN:
- Konfirmasi kamu membaca F-72 (trait TZ), F-73 (login crash), F-74 (radio), F-75 (bukti browser)
- Checklist Fase A-F, dependency-aware
- Hal yang belum jelas / bertentangan

BERHENTI. Tunggu Boss bilang "LANJUT".

## KONTEKS

Hari-3 lulus. KAMU sendiri yang menemukan dua bug lewat verifikasi Playwright:
F-72 (serializeDate paksa UTC) dan F-73 (/login crash sejak Hari-1).
Keduanya Boss putuskan diperbaiki hari ini. Itu temuan bagus - lanjutkan cara kerja itu.

Hari ini PALING PADAT. Prioritas kalau waktu habis:
  WAJIB   : Fase A, B, D, E
  KORBAN  : Fase C (radio) -> geser ke Hari-5

## FASE A — TRAIT TIMEZONE (F-72)

A1. Buat: app/Models/Concerns/SerializesDatesInAppTimezone.php

    Isi: override serializeDate(DateTimeInterface $date) supaya memformat dalam
    config('app.timezone'), BUKAN UTC.

    Kenapa trait, bukan base class: User extends Authenticatable, tidak bisa extend
    base Model kustom. Trait bisa dipasang di semua model tanpa kecuali.

A2. Pasang di SEMUA 13 model:
    Organization, WorkSchedule, Holiday, User, Project, TaskStatus, TaskTemplate,
    Task, TaskUser, TaskTimeSegment, DeadlineExtension, Attachment, ActivityLog

A3. HAPUS serializeDate() override lokal di WorkSchedule.php - digantikan trait.
    Jangan biarkan dua sumber kebenaran.

A4. Bukti WAJIB (browser, F-75):
    - Buka halaman Pengaturan > Jam Kerja
    - effective_from HARUS tampil sebagai tanggal yang sama dengan di database
    - Sebelum fix: DB 2026-07-18 -> frontend 2026-07-17T17:00:00Z (mundur 1 hari)

A5. Tambahkan ke CLAUDE.md? TIDAK. Boss yang pegang dokumen. Kalau kamu merasa
    ada yang perlu masuk dokumen, LAPORKAN - jangan edit sendiri.

## FASE B — FIX /login CRASH (F-73)

B1. resources/js/pages/auth/login.tsx - route('register') dipanggil tanpa guard.
    Route register sudah dicabut sejak Hari-1 (PROMPT-HARI-1 §F). Ziggy throw -> blank page.

B2. Perbaiki: HAPUS link "Sign up" sepenuhnya.
    JANGAN pakai route().has() atau try-catch. Route-nya memang tidak ada dan
    tidak akan pernah ada di v0.5 (tanpa self-signup). Link ke halaman yang tidak
    ada bukan sesuatu yang perlu di-guard - itu perlu dihapus.

B3. Grep seluruh resources/js: adakah route() lain yang menunjuk route dicabut?
    (register, password.request, verification.*). Perbaiki semua yang ketemu.

B4. Bukti WAJIB (browser, F-75):
    - Buka /login di browser -> form TAMPIL (bukan blank)
    - Login sebagai admin lewat FORM (bukan POST API) -> masuk dashboard
    - Login sebagai member lewat FORM -> masuk

## FASE C — RADIO STATUS (F-74)

C1. Halaman kelola status project: ubah dari checkbox per-status menjadi
    tabel dengan RADIO.

    Nama          Warna   Penanda selesai   Butuh review   Counter jalan
    TODO          [#]     ( )               ( )            [ ]
    IN PROGRESS   [#]     ( )               ( )            [x]
    REVIEW        [#]     ( )               (o)            [ ]
    DONE          [#]     (o)               ( )            [ ]
                          ^radio            ^radio         ^checkbox

C2. is_completed  -> RADIO, wajib tepat 1 terpilih
    is_review     -> RADIO, boleh "tidak ada" (opsi kosong)
    is_work_state -> CHECKBOX, minimal 1 tercentang (boleh lebih)

C3. Submit sekali -> SATU transaction:
      DB::transaction(fn() => [
        set semua is_completed = false,
        set yang dipilih = true
      ]);
    Tidak pernah ada state invalid karena tidak pernah ada langkah antara.

C4. HAPUS flagConstraintViolation() per-mutasi untuk is_completed & is_review.
    Radio membuatnya tidak relevan. Pertahankan hanya validasi is_work_state >= 1.

    ALASAN (F-74): checkbox mengizinkan 0 atau 2 -> butuh validasi penolak ->
    admin terjebak di antara dua langkah yang keduanya sah. Radio menghapus
    seluruh kelas masalah ini. Struktur UI = struktur constraint.

C5. Bukti WAJIB (browser, F-75):
    - Pindahkan penanda selesai dari DONE ke status lain -> BERHASIL dalam 1 submit
    - Verifikasi: TaskStatus::where('project_id',X)->where('is_completed',true)->count() = 1

## FASE D — TASK CRUD (INTI HARI INI)

ADMIN ONLY untuk create/edit/delete (F-29). Member hanya ubah status task-nya sendiri.

D1. Task index per project - TABEL POLOS.
    JANGAN buat filter/sort/search -> itu Hari-5. Cukup daftar + link edit.
    Subtask ditampilkan menjorok di bawah parent-nya.

D2. Form Create/Edit:
    - title              WAJIB, maks 255
    - description        opsional, RICH TEXT (F-30)
    - task_type          WAJIB - lihat D3
    - estimated_minutes  WAJIB (RAW data KPI - F-37)
    - points             WAJIB, default 0 (RAW data KPI - F-37)
    - due_date           WAJIB (F-31). PRE-FILL +7 hari DI FORM.
                         JANGAN kembalikan auto-fill di model (F-68).
    - priority           default normal
    - assignee[]         opsional, multi-select
    - parent_task_id     opsional -> subtask

D3. 🔴 task_type di Hari-4 HANYA: tentative | project

    daily/weekly/monthly TIDAK dibuat lewat form ini. Ketiganya lahir dari
    task_templates via recurring engine (F-46) yang dijadwalkan v0.8.

    ALASAN: membuat template tanpa engine = fitur mati. Admin bikin "task harian",
    tidak ada instance yang muncul, admin bingung dan lapor bug yang bukan bug.
    Enum tasks.task_type tetap punya 5 nilai - yang 3 diisi engine nanti.

    Kalau menurutmu ini keliru: LAPOR, jangan putuskan sendiri.

D4. 🔴 ASSIGN WAJIB LEWAT RELASI ELOQUENT:
      $task->assignees()->sync($ids);
    DILARANG KERAS:
      DB::table('task_user')->insert(...)
      DB::table('task_user')->delete(...)

    ALASAN (temuanmu sendiri di audit Hari-1): query manual MELEWATI observer
    -> event assigned/unassigned TIDAK tercatat -> lubang F-51 -> data KPI bolong
    permanen dan tidak bisa direkonstruksi.

D5. 🔴 SUBTASK MAKS 1 LEVEL (F-20):
    parent_task_id yang parent-nya SUDAH punya parent -> TOLAK.
    Validasi di service layer, bukan cuma dropdown yang disaring.

D6. Delete = soft delete (F-16). Task terhapus = data KPI hilang.
    Hapus parent -> subtask ikut soft delete. Konfirmasi dulu, sebutkan jumlahnya.

D7. Status task baru = status dengan position TERKECIL di project itu.
    JANGAN hardcode 'TODO' (F-44).

## FASE E — VALIDASI TRANSISI (F-45)

E1. Aturan:
    - MAJU  : hanya ke position + 1. TODO -> DONE DITOLAK.
    - MUNDUR: bebas ke position lebih rendah (revisi/reset).
    Validasi di SERVICE LAYER, bukan cuma frontend.

E2. Member boleh ubah status task yang di-assign ke dia. TIDAK boleh ubah field lain.

E3. Observer sudah menangani (jangan duplikasi):
    - completed_at saat masuk is_completed (F-21)
    - task_time_segments buka/tutup saat masuk/keluar is_work_state (F-41)
    - freeze actual_minutes + rejection_count saat approve (F-39)
    Pastikan jalur ubah status yang baru MELEWATI observer, bukan update mentah.

E4. Approve/Reject di status is_review -> ADMIN ONLY (F-28).
    - Approve: pindah ke status is_completed + isi quality_rating (1-5)
    - Reject : mundur ke status is_work_state, rejection_count++

## FASE F — TEST

F1. tests/Feature/TaskTest.php
    - create task -> status = position terkecil
    - due_date kosong -> DITOLAK (F-31)
    - subtask 2 level -> DITOLAK (F-20)
    - assign lewat sync() -> ActivityLog bertambah event 'assigned' (F-51)
    - delete = soft delete, deleted_at terisi

F2. tests/Feature/TaskTransitionTest.php
    - TODO -> DONE  -> DITOLAK (F-45)
    - TODO -> IN PROGRESS -> LOLOS, segmen terbuka dibuat (F-41)
    - IN PROGRESS -> REVIEW -> LOLOS, segmen ditutup
    - REVIEW -> IN PROGRESS (reject) -> rejection_count++, segmen BARU dibuat
    - REVIEW -> DONE (approve) -> completed_at terisi, actual_minutes FROZEN (F-39)
    - member ubah status task orang lain -> 403

F3. 🔴 AKUMULASI MULTI-SEGMEN END-TO-END
    Ini yang belum pernah diuji (catatan audit Hari-2, test #12 hanya di level kalkulator).
    Skenario: kerja -> review -> ditolak -> kerja lagi -> review -> approve
    Assert: actual_minutes = jumlah SEMUA segmen, dihitung dengan cap jendela kerja (F-57)

F4. 31+18 test lama HARUS tetap lulus TANPA diubah.
    Ada yang gagal -> LAPOR, jangan tambal test-nya.

## DILARANG KERAS DI HARI-4

JANGAN buat filter/sort/search di task index -> Hari-5
JANGAN buat List View canggih / Board View / drag-drop
JANGAN buat My Tasks -> Hari-5
JANGAN buat notifikasi UI -> Hari-6
JANGAN buat dashboard
JANGAN buat task_templates CRUD -> v0.8 bareng engine
JANGAN buat recurring engine
JANGAN buat scheduler/cron (F-38)
JANGAN buat attachment upload -> v0.8
JANGAN buat deadline extension flow -> v0.8
JANGAN kembalikan auto-fill due_date di model (F-68)
JANGAN hardcode nama status (F-44)
JANGAN hitung ulang actual_minutes yang sudah frozen (F-39)
JANGAN edit dokumen di docs/ - lapor kalau ada yang perlu diubah
JANGAN install dependency tanpa approval Boss

## RICH TEXT (F-30)

Butuh editor. Ini SATU-SATUNYA dependency yang boleh ditambah hari ini.
Sebelum install: LAPOR ke Boss nama paket + ukuran + alasan, TUNGGU approval.
Kalau Boss menolak: pakai textarea polos, rich text geser ke v1.

## STANDAR KOMENTAR
CLAUDE.md §3. Audiens: programmer FRESH ENTRY.
Header klasifikasi di SETIAP file baru. Provenance SUMBER + DIPAKAI.
Sebut nomor F-N di komentar business rule.

## DEFINITION OF DONE HARI-4

🔴 F-75: item bertanda [BROWSER] WAJIB dibuktikan di browser nyata (Playwright).
HTTP test TIDAK diterima sebagai bukti UI.

[ ] [BROWSER] /login tampil, login admin lewat FORM berhasil
[ ] [BROWSER] effective_from di halaman jam kerja = tanggal yang sama dengan DB
[ ] [BROWSER] pindah penanda selesai antar status berhasil 1 submit
[ ] [BROWSER] buat task lewat form -> muncul di daftar
[ ] [BROWSER] ubah status TODO -> DONE ditolak dengan pesan jelas
[ ] grep serializeDate di app/Models -> hanya di trait, tidak ada override lokal
[ ] grep "route('register')" di resources/js -> No matches
[ ] grep "DB::table('task_user')" -> No matches
[ ] php artisan test -> SEMUA lulus (49 lama TIDAK diubah + baru)
[ ] Test akumulasi multi-segmen (F3) LULUS
[ ] TaskStatus::where('is_completed',true)->count() = jumlah project (tepat 1 per project)
[ ] ./vendor/bin/pint -> 0 issue
[ ] npm run build -> sukses
[ ] Tidak ada scheduler/cron

## FORMAT LAPORAN AKHIR

STATUS   : [SELESAI / BLOCKED / BUTUH KEPUTUSAN]
DIUBAH   : <daftar file>
BUKTI    : <perintah + output aktual, tempel apa adanya>
           <bukti browser untuk item [BROWSER]>
DEVIASI  : <apa yang beda dari instruksi ini, dan kenapa>
RISIKO   : <apa yang bisa pecah di Hari-5>
NEXT     : <opsi + rekomendasi — TUNGGU keputusan Boss>

Mulai dari LANGKAH 0. Jangan tulis kode sebelum Boss bilang "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Satu keputusan Jarvis ambil di D3 — Boss boleh membatalkan.**
Form task Hari-4 hanya melayani `tentative` dan `project`. Tiga tipe berulang (`daily`/`weekly`/`monthly`) lahir dari `task_templates` via recurring engine yang dijadwalkan **v0.8**. Membuat template sekarang tanpa engine = **fitur mati**: admin bikin "task harian", tidak ada instance muncul, admin lapor bug yang bukan bug. Enum tetap punya 5 nilai. **Kalau Boss mau template CRUD masuk Hari-4, bilang — tapi itu menambah ~2 jam ke hari yang sudah penuh.**

**Kenapa `/login` diperbaiki dengan HAPUS, bukan guard?**
Route register **memang tidak ada dan tidak akan pernah ada** di v0.5 (tanpa self-signup — keputusan Hari-1). `route()->has()` atau try-catch cuma menyembunyikan link mati. **Link ke halaman yang tidak ada tidak perlu di-guard — perlu dihapus.**

**Kenapa F3 (akumulasi multi-segmen) ditandai merah?**
Audit Hari-2 mencatat test #12 hanya diuji **di level kalkulator**, bukan lewat Task + DB. Artinya rantai `kerja → review → ditolak → kerja lagi → approve → freeze` **belum pernah diuji utuh**. Itu skenario paling umum di dunia nyata, dan hasil akhirnya adalah angka yang membekukan dasar penilaian orang.

**Peringatan kapasitas.** `CLAUDE.md` sekarang **199 baris dari batas 200**. Finding berikutnya tidak muat — Jarvis akan pindahkan aturan yang sudah stabil ke `docs/` dan sisakan pointer.

**Kalau waktu Hari-4 habis:** korbankan **Fase C (radio)**, geser ke Hari-5. Jangan pernah korbankan Fase D/E — Task CRUD adalah pintu masuk seluruh data KPI.
