# PROMPT HARI-2 — PERBAIKAN + F-57

> **Fokus Hari-2:** migrasi MySQL 8 · perbaiki 5 deviasi Hari-1 · bangun **F-57 (cap jendela kerja)**
> **TIDAK ADA CRUD di Hari-2.** Alasan: F-57 adalah kalkulator yang di v2.0 menentukan potongan gaji.
> Tidak dikerjakan sambil lalu.

---

## §0. YANG BOSS LAKUKAN DULU (Claude Code tidak bisa)

### 0.1 — RAPIKAN STRUKTUR FILE 🔴 WAJIB

Audit menemukan: **`docs/` tidak ada.** File Boss ada di root dengan suffix `-1` (hasil unduh ganda). `CLAUDE.md` merujuk `docs/…` yang tidak pernah ada — setiap sesi Claude Code harus menebak.

**Buat folder `docs`, pindahkan & rename tanpa suffix:**

```
C:\laragon\www\taskapp\        (atau C:\xampp\htdocs\taskapp)
├── CLAUDE.md                  <- root
└── docs\
    ├── 01-PRD.md              <- dari 01-PRD-1.md
    ├── 02-DATA-MODEL.md       <- dari 02-DATA-MODEL-1.md
    ├── 03-BUSINESS-FLOW.md
    ├── 04-FINDING-REGISTRY.md
    ├── 05-TUTORIAL-SETUP.md
    ├── PROMPT-HARI-1.md
    └── PROMPT-HARI-2.md       <- file ini
```

### 0.2 — INSTALL MySQL 8

XAMPP Boss memakai **MariaDB 10.4** — EOL sejak 18 Juni 2024. Keputusan Boss: pindah ke MySQL 8.

1. Unduh **MySQL Community Server 8.0** → https://dev.mysql.com/downloads/installer/
2. Jalankan installer → pilih **Server only**
3. 🔴 **Port: `3307`** (BUKAN 3306 — biar tidak bentrok dengan MariaDB XAMPP yang masih terpasang)
4. Authentication: pilih **Use Legacy Authentication Method** (lebih mudah untuk lokal)
5. Set password root — **catat baik-baik**
6. Selesaikan instalasi

**Buat database:**
```
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p -P 3307
```
```sql
CREATE DATABASE task_management_v1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SELECT VERSION();
EXIT;
```
✅ `SELECT VERSION()` harus menampilkan **8.0.x**, bukan 10.4.

### 0.3 — UBAH `.env`

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=task_management_v1
DB_USERNAME=root
DB_PASSWORD=<password yang Boss buat>
```

> 🔴 **JANGAN ubah `DB_CONNECTION` jadi `mariadb`.** Guard FULLTEXT Hari-1 memeriksa `driver === 'mysql'`. Ini akan diperbaiki di Fase B, tapi sampai itu selesai — biarkan `mysql`.

### 0.4 — VERIFIKASI
```
php artisan migrate:fresh --seed
```
Harus sukses. Kalau gagal → jangan lanjut, lapor ke Jarvis.

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca utuh, baris demi baris:
1. CLAUDE.md
2. docs/01-PRD.md
3. docs/02-DATA-MODEL.md
4. docs/03-BUSINESS-FLOW.md
5. docs/04-FINDING-REGISTRY.md

Lalu jalankan dan tempel output asli:
  php artisan --version
  php artisan tinker --execute="echo DB::selectOne('SELECT VERSION() v')->v;"
  php artisan tinker --execute="echo DB::connection()->getDriverName();"

LAPORKAN:
- Konfirmasi database sekarang MySQL 8.x (BUKAN MariaDB). Kalau masih MariaDB, STOP dan lapor.
- Checklist eksekusi Fase A-D di bawah, dependency-aware
- Hal yang belum jelas / bertentangan

BERHENTI. Tunggu Boss bilang "LANJUT".

## KONTEKS

Hari-1 sudah selesai dan diaudit. Semua DoD lolos. Ditemukan 9 deviasi.
Hari-2 = PERBAIKAN + membangun F-57. TIDAK ADA fitur CRUD.

Kenapa F-57 didahulukan: F-39 membekukan actual_minutes saat approve dan
MELARANG penghitungan ulang. Rumus sekarang (calculateRawActualMinutes) menjumlah
durasi mentah tanpa cap jendela kerja. Kalau ada task nyata di-approve dengan rumus
ini, angkanya SALAH PERMANEN dan tidak bisa diperbaiki. F-57 harus ada SEBELUM
aplikasi menyentuh data nyata.

## FASE A — VERIFIKASI MIGRASI MySQL 8

A1. php artisan migrate:fresh --seed  -> harus sukses
A2. Buktikan kolom json sekarang bertipe JSON native (BUKAN longtext):
    SHOW COLUMNS FROM work_schedules;     -> days_of_week harus json
    SHOW COLUMNS FROM activity_logs;      -> properties harus json
    SHOW COLUMNS FROM task_templates;     -> recurrence_config, default_assignees harus json
A3. SHOW INDEX FROM tasks;  -> fulltext_index HARUS ada
A4. Tempel semua output asli.

Kalau ada yang masih longtext -> LAPOR, jangan perbaiki sendiri.

## FASE B — PERBAIKI 5 DEVIASI HARI-1

### B1 — Guard FULLTEXT (F-67) 🔴
Sekarang: if (driver === 'mysql') -> rapuh.
Masalah: Laravel 11+ punya driver 'mariadb' terpisah. Ganti satu kata di .env dan
FULLTEXT tidak dibuat -> search MATI DIAM-DIAM tanpa error.

Perbaiki jadi EXCLUDE, bukan INCLUDE:
  if (! in_array(DB::connection()->getDriverName(), ['sqlite'])) { ... }

Prinsip: daftar yang DIKECUALIKAN harus eksplisit. Daftar yang DIIZINKAN akan
selalu ketinggalan saat ada driver baru.

### B2 — due_date default (F-68) 🔴
Sekarang: default +7 hari di Task::booted().
Masalah: F-31 bilang due_date WAJIB. Kalau model diam-diam mengisi +7 hari saat
admin lupa, sistem MENYEMBUNYIKAN kesalahan input. Task tanpa deadline sadar-diri
lebih baik daripada task dengan deadline karangan.

Perbaiki:
- HAPUS auto-fill dari Task::booted()
- due_date tetap NOT NULL di DB
- Validasi wajib di FormRequest (Hari-3)
- Default +7 hari = PRE-FILL di form UI (Hari-3), bukan di model

### B3 — Header klasifikasi (CLAUDE.md §3.1)
Tambahkan header ke 4 file yang belum punya:
  database/factories/OrganizationFactory.php
  database/factories/UserFactory.php
  app/Http/Requests/Auth/LoginRequest.php
  tests/Feature/Settings/ProfileUpdateTest.php

### B4 — Dokumentasikan pivot tanpa organization_id
task_user dan project_user tidak punya organization_id (F-5 tampak dilanggar).
Ini SENGAJA - pivot mewarisi tenant dari parent-nya.
Tambahkan komentar di kedua migration yang menjelaskan ini, sebut F-5.
Supaya auditor berikutnya tidak menganggap ini bug.

### B5 — JANGAN sentuh
- TaskUser pivot model + observer -> DITERIMA, biarkan
- #[ObservedBy] attribute -> DITERIMA, biarkan
- Auth::hasUser() fix -> DITERIMA, biarkan
- 3 test yang dihapus -> DITERIMA, biarkan

## FASE C — BANGUN F-57 (INTI HARI-2)

### C1 — Service baru
Buat: app/Services/BusinessHoursCalculator.php

Kontrak:
  overlapMinutes(Carbon $start, ?Carbon $end, WorkSchedule $schedule): int

Logika:
1. $end null (segmen masih berjalan) -> $end = min(now(), penutupan jendela hari ini)
2. $end <= $start -> return 0
3. Iterasi per HARI dari $start sampai $end:
   a. Hari itu ada di $schedule->days_of_week? Tidak -> 0 menit, lanjut
   b. Jendela hari itu = [tanggal + start_time, tanggal + end_time]
   c. overlap = max(0, min($end, jendela_akhir) - max($start, jendela_awal))
   d. akumulasi menit
4. GUARD: batasi iterasi maksimal 365 hari. Segmen lebih panjang dari itu =
   data korup, lempar exception, JANGAN diam-diam kembalikan angka.
5. Holiday: SKIP di v0.5 (tabel kosong, F-43). Siapkan titik masuknya, jangan bangun.

### C2 — Config mana yang dipakai (F-66) 🔴
work_schedules VERSIONED (F-40). Segmen bisa menyeberang perubahan config.
ATURAN v0.5: pakai config yang aktif saat SEGMEN DIMULAI (started_at).
Resolusi per-hari -> v0.8.
TULIS ini sebagai komentar di kode, sebut F-66. Ini keputusan sadar, bukan kelalaian.

### C3 — Integrasi
- Task::calculateActualMinutes() = Σ overlapMinutes() seluruh segmen task
- HAPUS calculateRawActualMinutes() dari TaskObserver, ganti dengan yang baru
- Freeze saat approve (F-39) tetap seperti sekarang - hanya rumusnya yang berubah
- F-53: kalau hasil > 3x estimated_minutes -> catat activity_log event 'anomaly_flagged'.
  JANGAN blokir, JANGAN hukum. Hanya tandai.

### C4 — Seeder
Perbarui seeder supaya 3 task DONE+approved memakai rumus F-57 yang benar.
Pastikan minimal 1 segmen menyeberang weekend untuk membuktikan cap bekerja.

## FASE D — TEST (WAJIB, BUKAN OPSIONAL)

Buat: tests/Unit/BusinessHoursCalculatorTest.php
Jendela uji: Sen-Jum, 08:00-17:00

Kasus WAJIB (semua harus lulus):
 1. Jumat 16:00 -> Senin 09:00        = 120 menit  <- KASUS UTAMA (bukan 3900)
 2. Senin 09:00 -> Senin 11:00        = 120 menit
 3. Senin 16:00 -> Selasa 09:00       = 120 menit
 4. Sabtu 10:00 -> Sabtu 12:00        = 0 menit
 5. Senin 07:00 -> Senin 09:00        = 60 menit   (jendela baru buka 08:00)
 6. Senin 16:00 -> Senin 18:00        = 60 menit   (jendela tutup 17:00)
 7. Senin 18:00 -> Senin 20:00        = 0 menit
 8. Senin 08:00 -> Jumat 17:00        = 2700 menit (5 hari x 9 jam)
 9. ended_at null -> hitung sampai min(now, penutupan jendela)
10. end <= start                      = 0 menit
11. Segmen > 365 hari                 -> exception

Test akumulasi multi-segmen (skenario tolak-lalu-kerja-lagi):
12. Task dengan 2 segmen -> actual_minutes = jumlah keduanya

## DILARANG KERAS DI HARI-2

JANGAN buat controller CRUD apa pun (Project/Task/Status/WorkSchedule) -> Hari-3
JANGAN buat halaman UI baru
JANGAN buat List View / Board View
JANGAN buat search, notifikasi UI, dashboard
JANGAN buat recurring engine
JANGAN buat scheduler/cron (F-38 - counter = calculated, BUKAN stateful)
JANGAN hitung ulang actual_minutes task yang SUDAH frozen (F-39) - kecuali lewat
  migrate:fresh --seed yang membuat ulang semuanya dari nol
JANGAN buat tabel scoring/KPI/payroll
JANGAN bangun holiday calendar (F-43 - v0.8)
JANGAN install dependency tanpa approval Boss

## ATURAN YANG PALING SERING DILANGGAR - BACA ULANG

F-38 : Counter = CALCULATED. Simpan timestamp, hitung saat ditanya. NOL scheduler.
F-39 : Angka yang sudah frozen TIDAK BOLEH dihitung ulang.
F-44 : JANGAN hardcode nama status. Pakai flag is_work_state/is_review/is_completed.
F-51 : Activity log tidak boleh bolong. Kalau kamu menambah jalur perubahan data
       baru, pastikan observer tetap menangkapnya.

Peringatan dari audit Hari-1 (temuan Claude Code sendiri):
"Kalau assign task lewat query manual ke tabel pivot (bukan $task->assignees()->attach()),
event assigned TIDAK tercatat = lubang F-51."
Ingat ini saat Hari-3.

## STANDAR KOMENTAR
CLAUDE.md §3 tanpa kompromi. Audiens: programmer FRESH ENTRY.
Header klasifikasi di SETIAP file baru. Provenance SUMBER + DIPAKAI.
Sebut nomor F-N di komentar business rule.

## DEFINITION OF DONE HARI-2

Tempel output ASLI, bukan klaim:

[ ] SELECT VERSION() -> 8.0.x
[ ] days_of_week, properties, recurrence_config bertipe json (BUKAN longtext)
[ ] SHOW INDEX FROM tasks -> fulltext_index ada
[ ] php artisan migrate:fresh --seed -> sukses
[ ] php artisan test -> SEMUA lulus, termasuk 12 test BusinessHoursCalculator
[ ] Test kasus #1 (Jumat 16:00 -> Senin 09:00 = 120) LULUS
[ ] grep calculateRawActualMinutes -> No matches (sudah diganti)
[ ] grep "=== 'mysql'" di migration fulltext -> No matches (sudah jadi exclude sqlite)
[ ] grep due_date di Task::booted() -> No matches (auto-fill sudah dihapus)
[ ] Task::whereNotNull('actual_minutes')->count() = 3
[ ] Nilai actual_minutes ketiganya MASUK AKAL (tidak ada yang > kapasitas harian x hari)
[ ] 4 file di B3 sudah punya header klasifikasi
[ ] ./vendor/bin/pint -> 0 issue
[ ] npm run build -> sukses
[ ] Tidak ada file scheduler/cron yang dibuat

## FORMAT LAPORAN AKHIR

STATUS   : [SELESAI / BLOCKED / BUTUH KEPUTUSAN]
DIUBAH   : <daftar file>
BUKTI    : <perintah + output aktual, tempel apa adanya>
DEVIASI  : <apa yang kamu bangun beda dari instruksi ini, dan kenapa>
RISIKO   : <apa yang bisa pecah di Hari-3>
NEXT     : <opsi + rekomendasi — TUNGGU keputusan Boss>

Mulai dari LANGKAH 0. Jangan tulis kode sebelum Boss bilang "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Kenapa Hari-2 tidak ada CRUD sama sekali?**
F-57 adalah kalkulator yang di **v2.0 menentukan potongan gaji orang**. Kalau dikerjakan sambil lalu bareng tiga CRUD, bug-nya tidak akan ketahuan sampai ada yang protes gajinya dipotong salah. **12 test unit itu bukan formalitas** — itu bukti kalkulatornya benar sebelum menyentuh uang siapa pun.

**Kenapa test #1 disebut "KASUS UTAMA"?**
`Jumat 16:00 → Senin 09:00 = 120 menit` adalah **seluruh alasan F-57 ada**. Tanpa cap jendela kerja, angkanya 3.900 menit (65 jam). Kalau test ini lulus, Masalah A selesai untuk selamanya.

**Kenapa guard FULLTEXT dibalik jadi "exclude sqlite"?**
Daftar **yang diizinkan** akan selalu ketinggalan saat ada driver baru — dan gagalnya **diam-diam** (search mati, tidak ada error). Daftar **yang dikecualikan** memaksa driver tak dikenal ikut jalur normal. Kalau salah, errornya berisik — dan error berisik jauh lebih murah daripada fitur yang mati tanpa suara.

**Timeline v0.5 sekarang 6 hari, bukan 5:**
| Hari | Isi |
|---|---|
| ~~1~~ | ✅ Fondasi + skema + observer + seeder |
| **2** | Migrasi MySQL 8 · perbaikan · **F-57** |
| 3 | Work Schedule CRUD · Project CRUD · Status CRUD |
| 4 | Task CRUD · validasi transisi F-45 |
| 5 | List View · filter · My Tasks |
| 6 | Search · notifikasi · buffer |

Naik 1 hari karena F-57 dinaikkan dari v0.8. **Itu harga yang murah** — alternatifnya adalah data KPI yang salah permanen.
