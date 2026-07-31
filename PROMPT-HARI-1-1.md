# PROMPT HARI-1 — SIAP TEMPEL (REVISI 2)

> **Cara pakai:** terminal di root proyek → `claude` → copy blok `=== MULAI ===` s/d `=== SELESAI ===` → tempel → Enter.

---

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca berurutan, utuh, baris demi baris:
1. CLAUDE.md
2. docs/01-PRD.md
3. docs/02-DATA-MODEL.md
4. docs/03-BUSINESS-FLOW.md

Lalu LAPORKAN ke Boss:
- Ringkasan scope Hari-1 versi pemahamanmu (maks 10 baris)
- Checklist eksekusi kamu, dependency-aware, risiko rendah -> tinggi
- Daftar hal yang belum jelas / bertentangan (kalau ada)
- Versi aktual dari sistem (BUKAN dari ingatanmu): PHP, Laravel, Node, MySQL

BERHENTI. Tunggu Boss bilang "LANJUT".

## KONTEKS PENTING

Ini BUKAN task manager biasa. Ini performance management system:
budget waktu -> KPI -> scoring -> reward/punishment -> gaji (v2.0) -> marketplace freelance (v3.0).

Konsekuensinya: SKEMA DATABASE adalah deliverable terpenting Hari-1.
Data yang tidak direkam sejak awal HILANG SELAMANYA - tidak bisa dihitung mundur.

## SCOPE HARI-1 — HANYA INI

### A. SCAFFOLD
- Laravel 12 + starter kit React (Inertia v2 + React 19 + Tailwind + shadcn/ui)
- VERIFIKASI dulu starter kit yang tersedia di versi Laravel aktual. JANGAN asumsi dari ingatan.
  Cek `laravel new --help` atau dokumentasi resmi. Lapor ke Boss kalau tidak sesuai ekspektasi.
- MySQL 8. Timezone: UTC di DB, Asia/Jakarta di tampilan. Locale id.

### B. MIGRATION — 17 FILE
Ikuti docs/02-DATA-MODEL.md §6 PERSIS. Urutan JANGAN diacak.

 1. organizations
 2. work_schedules       <- F-54: FK created_by ditambah BELAKANGAN (circular)
 3. holidays             <- tabel dibuat, KOSONG (dipakai v0.8)
 4. users
 5. sessions/cache       (bawaan)
 6. projects
 7. project_user
 8. task_statuses        <- 3 flag: is_work_state, is_review, is_completed (F-44)
 9. task_templates       <- recurring blueprint (engine v0.8)
10. tasks                <- INTI
11. task_user
12. task_time_segments   <- F-41 JANTUNG REALISASI
13. deadline_extensions
14. attachments
15. activity_logs
16. notifications        (php artisan notifications:table)
17. add_fulltext_to_tasks  <- F-24 raw DB::statement

ATURAN WAJIB:
- F-5 : organization_id ADA di SEMUA tabel bisnis. Tanpa kecuali.
- F-16: softDeletes di users, projects, tasks.
- F-19: task_statuses.is_completed WAJIB.
- F-44: TIGA flag di task_statuses. JANGAN hardcode nama status di mana pun.
- F-21: tasks.completed_at WAJIB.
- F-31: tasks.due_date WAJIB (NOT NULL).
- F-37: tasks.points, tasks.estimated_minutes, tasks.quality_rating WAJIB - ini RAW data KPI.
- F-47: tasks.original_due_date WAJIB - tanpa ini metrik on-time bohong total.
- Semua index & FULLTEXT sesuai §3.9.
- utf8mb4_unicode_ci.

### C. MODEL + RELASI
Model: Organization, WorkSchedule, Holiday, User, Project, TaskStatus,
       TaskTemplate, Task, TaskTimeSegment, DeadlineExtension, Attachment, ActivityLog

- Relasi PERSIS sesuai ERD §2 (termasuk Task self-relation parent/children)
- F-15: OrganizationScope sebagai GLOBAL SCOPE di semua model bisnis.
        Query tanpa scope = bug keamanan, bukan optimasi.
- F-20: validasi subtask maks 1 level. parent yang punya parent = DITOLAK.
- F-40: WorkSchedule::active() -> effective_from <= today, order desc, first.
        JANGAN pernah update baris lama. Ubah setting = INSERT baru.

### D. OBSERVER — F-22, F-51 (TULANG PUNGGUNG)
- Activity log WAJIB via Eloquent Observer. BUKAN panggilan manual di controller.
- Event: created, updated, status_changed, assigned, unassigned, completed,
         approved, rejected, deleted, extension_requested, extension_approved,
         extension_rejected, attachment_uploaded
- properties json WAJIB berisi {"old":{...},"new":{...}}
- F-23: log IMMUTABLE. Tidak ada update, tidak ada delete. Selamanya.
- F-51: log tidak boleh bolong satu event pun - ini sumber 4 dari 6 metrik KPI.

TaskObserver juga menangani:
- F-21: status pindah ke is_completed=true -> completed_at = now(). Keluar -> null.
- F-41: status pindah ke is_work_state=true  -> INSERT task_time_segments (ended_at NULL)
        status keluar dari is_work_state     -> UPDATE ended_at = now()
- F-48: maks 1 segmen terbuka per task. Segmen terbuka ganda = data korup.

### E. SEEDER — sesuai §8
- 1 organization
- 1 work_schedule (Sen-Jum, 08:00-17:00, daily_capacity_minutes = 480)
- holidays KOSONG
- 1 admin + 9 member
- 2 project x 4 status default (flag PERSIS sesuai tabel §3.7)
- 3 task_templates (daily, weekly, monthly) - is_active, BELUM generate
- 30 task: campur 5 task_type, beragam status/assignee/priority/points/estimated_minutes
  due_date SEBAGIAN sudah lewat SEBAGIAN mendatang
- 5 subtask
- 10 task_time_segments - termasuk 2 yang MENYEBERANG malam/weekend (uji F-41/F-57)
- 3 task DONE+approved dengan actual_minutes & rejection_count FROZEN (uji F-39)
- 1 deadline_extension pending + 1 approved
- 2 attachment (1 output, 1 evidence)
- activity_logs ter-generate otomatis via observer

### F. AUTH
- Login/logout jalan
- TANPA self-signup, TANPA email verification, TANPA reset password
- Role admin | member
- is_active=false -> diblokir login
- Redirect setelah login ke halaman kosong bertulisan "Dashboard". CUKUP ITU.

## DILARANG KERAS DI HARI-1

JANGAN buat controller CRUD (Hari-2/3)
JANGAN buat halaman List View / Board View
JANGAN buat search, notifikasi UI, dashboard
JANGAN buat recurring ENGINE (skema saja - engine v0.8)
JANGAN buat counter UI / kalkulator business-hours (v0.8)
JANGAN buat scheduler / cron apa pun (F-38: counter = calculated, BUKAN stateful)
JANGAN buat tabel scoring/KPI/payroll (v1.5 & v2.0)
JANGAN pasang Firebase (F-6), Elasticsearch/Algolia (F-7)
JANGAN buat comment, custom field, tags, lists, dependencies
JANGAN install dependency di luar starter kit tanpa approval Boss
JANGAN refactor apa pun di luar scope

Butuh sesuatu di luar daftar? BERHENTI, laporkan alasannya, tunggu keputusan Boss.

## STANDAR KOMENTAR

Ikuti CLAUDE.md §3 tanpa kompromi. Audiens: programmer FRESH ENTRY.
- Header klasifikasi (§3.1) di SETIAP file yang kamu buat
- Provenance: SUMBER + DIPAKAI
- Business rule + alasannya, sebut nomor F-N nya
- JANGAN komentari baris self-evident
- Komentar Bahasa Indonesia, identifier Bahasa Inggris

## DEFINITION OF DONE HARI-1

Belum selesai sampai SEMUA terbukti. Tempel output ASLI, bukan klaim:

[ ] `php artisan migrate:fresh --seed` sukses tanpa error
[ ] `php artisan tinker` -> Task::count() = 35          (30 task + 5 subtask)
[ ] `php artisan tinker` -> TaskStatus::count() = 8      (2 project x 4)
[ ] `php artisan tinker` -> TaskTemplate::count() = 3
[ ] `php artisan tinker` -> TaskTimeSegment::count() = 10
[ ] `php artisan tinker` -> ActivityLog::count() > 0     (BUKTI observer jalan)
[ ] `php artisan tinker` -> Task::whereNotNull('actual_minutes')->count() = 3  (F-39 frozen)
[ ] `php artisan tinker` -> WorkSchedule::active()->daily_capacity_minutes = 480
[ ] `SHOW INDEX FROM tasks` menampilkan fulltext_index
[ ] `SHOW COLUMNS FROM tasks` memuat: points, estimated_minutes, actual_minutes,
    quality_rating, rejection_count, original_due_date, task_type
[ ] Semua tabel bisnis punya kolom organization_id (F-5) - buktikan dengan query
[ ] Bisa login dari browser sebagai admin
[ ] `./vendor/bin/pint` -> 0 issue
[ ] `npm run build` -> sukses
[ ] Header klasifikasi ada di semua file baru

## FORMAT LAPORAN AKHIR

STATUS   : [SELESAI / BLOCKED / BUTUH KEPUTUSAN]
DIUBAH   : <daftar file>
BUKTI    : <perintah + output aktual, tempel apa adanya>
RISIKO   : <apa yang bisa pecah di Hari-2>
NEXT     : <opsi + rekomendasi — TUNGGU keputusan Boss>

Mulai dari LANGKAH 0. Jangan lewati. Jangan tulis kode sebelum Boss bilang "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Kenapa LANGKAH 0 (baca + lapor + berhenti)?**
Mandatory #7 — tidak ada eksekusi unilateral. Claude Code menunjukkan rencananya **sebelum** membakar kuota untuk kerja yang salah arah. Biaya 5 menit, menyelamatkan berjam-jam.

**Kenapa Definition of Done pakai angka?**
`ActivityLog::count() > 0` membuktikan **observer benar-benar jalan** (F-22), bukan sekadar filenya ada.
`Task::whereNotNull('actual_minutes')->count() = 3` membuktikan **freeze F-39** bekerja.
Angka tidak bisa diklaim — harus dibuktikan.

**Kalau Claude Code melanggar scope:**
```
STOP. Itu di luar scope Hari-1 (docs/01-PRD.md §5.1). Kembali ke daftar A-F.
```

**Kalau klaim "selesai" tanpa tempel output:**
```
Tempel output asli setiap item Definition of Done. Klaim tanpa bukti tidak diterima (CLAUDE.md §2 no.11).
```

**Kalau Claude Code mulai bikin scheduler/cron untuk counter:**
```
STOP. F-38: counter = calculated, BUKAN stateful. Simpan timestamp, hitung saat ditanya.
Tidak ada scheduler di v0.5.
```

**Kalau kena limit di tengah jalan:** `/status` cek kuota. Kerjaan aman di disk. Sesi berikutnya:
```
Baca CLAUDE.md dan docs/. Lanjutkan Hari-1 dari checklist yang belum selesai.
```
