# PROMPT v0.8 HARI-4 — RECURRING ENGINE (F-46, F-60, F-61, F-100, F-101, F-102)

> **Subsistem TERBESAR & PALING RAWAN di v0.8.** Template → instance otomatis.
> Data lokal. Scheduler hanya jalan via `php artisan schedule:run` manual sampai ada
> server dengan cron (Boss belum deploy) — ini WAJAR, engine tetap dibangun & diuji lengkap.

---

## §0. YANG BOSS LAKUKAN DULU

Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-100/101/102 (aturan recurring), F-99 ditutup
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca utuh:
1. CLAUDE.md
2. docs/02-DATA-MODEL.md      <- §3.8 task_templates (skema sudah ada sejak H1 v0.5)
3. docs/03-BUSINESS-FLOW.md   <- §3 alur recurring
4. docs/04-FINDING-REGISTRY.md  <- F-46, F-60, F-61, F-100, F-101, F-102, F-86, F-69, F-81

LAPORKAN:
- Ringkas 5 aturan tanggal recurring dengan kata-katamu (F-100/101/102) — Jarvis
  ingin yakin kamu paham SEBELUM menulis, karena ini rawan
- F-97: 4 item browser masih tertunda. Chrome extension tersedia? Kalau tidak, lanjut
- Checklist Fase A-E
- Konflik/ketidakjelasan apa pun

BERHENTI. Tunggu Boss bilang "LANJUT".

## KONTEKS

task_templates SUDAH ADA sebagai skema sejak v0.5 Hari-1, TAPI ENGINE-nya belum
pernah dibangun (sengaja ditunda). Hari ini: template CRUD + generator instance.

🔴 Ini bypass form task normal. Karena itu WAJIB menegakkan invarian yang biasanya
dijaga StoreTaskRequest — khususnya F-86 (assignee harus project member). Recurring
adalah sumber terbesar "state mustahil dibuat lewat UI". Hati-hati.

## ATURAN TANGGAL — URUTAN OPERASI (WAJIB PERSIS)

Untuk tiap template aktif, tiap kali scheduler jalan pada hari D (WIB, F-69):

1. Hitung TANGGAL NATURAL:
   - daily   -> D
   - weekly  -> hari dengan recurrence_config.day_of_week di minggu D
   - monthly -> recurrence_config.day_of_month di bulan D

2. CLAMP monthly (F-101):
   day_of_month > jumlah hari bulan -> hari TERAKHIR bulan (31 Jan -> 28/29 Feb)

3. LIBUR/AKHIR PEKAN (F-102) — SETELAH clamp:
   - daily   -> kalau tanggal natural libur/non-workday: SKIP TOTAL (jangan geser,
               jangan backfill. Tumpukan tetap datang dari carryover F-60)
   - weekly/monthly -> GESER MAJU ke hari kerja berikutnya

4. GENERATE hanya kalau TANGGAL EFEKTIF (hasil 1-3) == D (F-100):
   Tidak ada backfill. Scheduler terlewat -> hari terlewat TIDAK direkonstruksi.
   (weekly/monthly yang digeser: efektifnya jatuh di hari kerja, digenerate saat
    scheduler jalan di hari kerja itu — forward, bukan menengok ke belakang)

5. IDEMPOTENCY (F-61):
   Cek last_generated_date. Kalau template sudah generate untuk tanggal efektif ini
   -> SKIP. Cron 2x hari sama TIDAK menggandakan. Update last_generated_date setelah
   sukses generate.

🔴 daily-shift DILARANG (langgar F-100). Ini sudah diputuskan Boss (F-102). Jangan
   "memperbaiki" jadi shift — itu menghidupkan konflik yang sudah diselesaikan.

## FASE A — TASK TEMPLATE CRUD

A1. UI kelola template (permission task.manage — RBAC, admin).
    Field: title, description (rich text), project, task_type (daily/weekly/monthly),
    estimated_minutes, points, priority, recurrence_config (day_of_week / day_of_month),
    default_assignees (multi, HARUS project member), is_active.

A2. 🔴 task_type di template HANYA daily/weekly/monthly.
    tentative/project TIDAK berulang -> tidak punya template (F-46). Tolak kalau dikirim.

A3. 🔴 F-86: default_assignees WAJIB divalidasi sebagai member project saat simpan
    template DAN saat generate (member bisa berubah setelah template dibuat).
    Assignee non-member -> tolak saat simpan; saat generate -> DROP yang bukan member
    lagi (jangan attach non-member — itu state mustahil). Kalau tersisa 0 assignee,
    tetap generate task UNASSIGNED (valid, admin bisa assign). Log yang di-drop.

A4. recurrence_config validasi:
    - weekly: day_of_week 1-7 (ISO)
    - monthly: day_of_month 1-31
    - daily: config kosong/diabaikan

A5. is_active=false -> berhenti generate ke depan, instance yang sudah ada TETAP.

A6. Edit template TIDAK mengubah instance yang sudah tergenerate (F-46:
    template != task, instance independen setelah lahir).

## FASE B — GENERATOR + COMMAND

B1. app/Console/Commands/GenerateRecurringTasksCommand.php
    Terapkan aturan tanggal di atas (urutan 1-5 PERSIS).

B2. Daftarkan di scheduler HARIAN, jam awal hari WIB (mis. 00:05 Asia/Jakarta).
    🔴 F-81: scheduler untuk recurring itu SAH (beda dari F-38 counter). Ini bukan
    pelanggaran "jangan scheduler" — larangan itu khusus counter yang harus calculated.

B3. Instance yang digenerate:
    - project_id, title, description, estimated_minutes, points, priority <- dari template
    - task_template_id -> menunjuk template asalnya
    - task_type <- dari template
    - task_status_id -> status position TERKECIL project (F-44, jangan hardcode 'TODO')
    - due_date -> tanggal EFEKTIF pada jam end_time work_schedule (WIB)
    - assignees -> default_assignees yang MASIH member (F-86, A3)

B4. 🔴 F-60: instance lama yang belum selesai JANGAN dihapus saat instance baru lahir.
    Task kemarin belum DONE -> tetap ada, jadi overdue. Ini yang menghasilkan tumpukan.

B5. Setiap generate -> activity_log (F-51, lewat observer yang sudah ada).
    Assign lewat relasi Eloquent ($task->assignees()->sync), BUKAN DB::table (F-51).

B6. Notifikasi assignee saat instance lahir (pola notifikasi yang sudah ada).

## FASE C — TEST (MySQL, F-83) — INI YANG MENYELAMATKAN

🔴 Pakai travelTo() untuk SEMUA test tanggal (pelajaran H2 v0.5: tanggal relatif = flaky).

C1. tests/Feature/RecurringDailyTest.php
    - hari kerja normal -> 1 instance daily
    - jalankan 2x hari sama -> tetap 1 (F-61 idempotency)
    - hari libur -> 0 instance (F-102 daily skip)
    - akhir pekan -> 0 instance
    - scheduler terlewat 3 hari -> saat jalan, HANYA hari ini (F-100 no backfill),
      bukan 3 instance

C2. tests/Feature/RecurringWeeklyTest.php
    - day_of_week jatuh hari kerja -> generate hari itu
    - day_of_week jatuh Sabtu -> GESER ke Senin, generate saat scheduler jalan Senin
    - idempotency

C3. tests/Feature/RecurringMonthlyTest.php
    - day_of_month=31, bulan 28 hari -> clamp ke 28 (F-101)
    - day_of_month=31, lalu 28 itu Sabtu -> clamp DULU ke 28, lalu geser ke Senin (urutan)
    - day_of_month normal hari kerja -> generate

C4. tests/Feature/RecurringInvariantTest.php (F-86 — KRITIS)
    - default_assignee yang sudah bukan member -> di-drop, task tetap lahir
    - semua assignee bukan member -> task lahir UNASSIGNED, tidak crash
    - assign lewat relasi -> activity_log 'assigned' tercatat (F-51)
    - instance lama belum selesai + generate baru -> DUA instance (F-60)

C5. tests/Feature (template CRUD):
    - tentative/project sebagai task_type template -> ditolak (A2)
    - non-member sebagai default_assignee saat simpan -> ditolak (A3)
    - edit template -> instance lama tak berubah (A6)

C6. 164 test lama tetap lulus. F-78 berlaku.

## DILARANG KERAS

JANGAN geser daily di libur (F-102 — sudah diputuskan, langgar F-100)
JANGAN backfill hari terlewat (F-100)
JANGAN hapus instance lama saat generate baru (F-60)
JANGAN attach assignee non-member (F-86 — state mustahil)
JANGAN assign lewat DB::table (F-51 — lewat relasi Eloquent)
JANGAN hardcode nama status/hari (F-44)
JANGAN buat scheduler untuk counter (F-38 — beda dari recurring, F-81)
JANGAN buat attachment/extension -> H5/H6
JANGAN buat template untuk tentative/project (F-46)
JANGAN deploy/L13 (Boss: lokal)
JANGAN install dependency tanpa approval
JANGAN edit dokumen docs/

## STANDAR KOMENTAR
CLAUDE.md §3. Header klasifikasi tiap file baru. Di generator, tulis komentar
urutan operasi tanggal (clamp->shift->today-check->idempotency) dengan F-N — fresh
entry WAJIB paham kenapa daily skip tapi weekly/monthly geser.

## DEFINITION OF DONE

🔴 F-83 test MySQL. Semua test tanggal pakai travelTo().

[ ] daily hari kerja -> 1 instance; libur/weekend -> 0 (F-102)
[ ] scheduler 2x hari sama -> tidak ganda (F-61)
[ ] scheduler terlewat 3 hari -> hanya hari ini (F-100)
[ ] weekly Sabtu -> geser Senin
[ ] monthly 31 di Feb -> clamp 28, lalu geser kalau perlu (F-101 urutan)
[ ] default_assignee non-member -> di-drop, task tetap lahir (F-86)
[ ] instance lama belum selesai + baru -> dua instance (F-60)
[ ] generate -> activity_log tercatat lewat observer (F-51)
[ ] tentative/project ditolak sebagai template (F-46)
[ ] php artisan test -> SEMUA lulus MySQL (164 lama + baru)
[ ] npx tsc 0 error, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / DEVIASI (nol -> "NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Ini hari paling rawan di v0.8 — dan Jarvis membangunnya di sekitar test, bukan sekadar kode.** Recurring engine punya banyak kasus tepi (libur, akhir pekan, tanggal 31, scheduler terlewat, assignee yang keluar). Setiap satu punya test eksplisit di Fase C. Kalau ada yang meleset, test yang menangkap — bukan tim Boss tiga minggu kemudian saat task muncul di hari yang salah.

**Aturan tanggalnya sekarang konsisten** setelah empat pertanyaan kemarin. Yang paling halus: **daily di-skip saat libur, tapi tumpukan tetap Boss dapat** — bukan dari geser (yang akan melanggar "no backfill"), melainkan dari carryover F-60 (task kemarin belum selesai tetap ada). Dua mekanisme berbeda menghasilkan hasil yang Boss inginkan; engine cuma perlu satu.

**Realita lokal (F-100 + tanpa deploy):** scheduler tidak benar-benar jalan harian sampai ada server dengan cron. Di lokal, Boss mengujinya lewat `php artisan schedule:run` manual. **Itu wajar dan cukup untuk membangun + menguji.** Saat nanti deploy, cron dipasang sekali di server. Engine-nya sudah teruji lengkap sebelum itu.

**F-86 adalah bahaya senyap hari ini.** Recurring melewati form task, jadi ia bisa menciptakan task dengan assignee yang bukan lagi member project — persis bug yang ditemukan di Hari-7 v0.5. Fase A3 + test C4 mengunci ini: assignee non-member di-drop, task tetap lahir (unassigned kalau perlu), tidak pernah attach yang mustahil.

**Peta v0.8:** ~~H1~~ ~~H2~~ ~~H3~~ -> H4 recurring -> H5 attachment -> H6 extension + 2 notif -> H7 buffer & verifikasi utuh.
