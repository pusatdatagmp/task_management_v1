# PROMPT v0.8 HARI-2 — DASHBOARD: RUMUS + BACKEND (F-52, F-96)

> **Backend & query saja. BELUM UI** (itu Hari-3). Fokus: rumus benar + test.
> Data lokal. Tidak deploy.

---

## §0. YANG BOSS LAKUKAN DULU

Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-96 (F-63 diputus), +F-97 (utang browser)
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca utuh:
1. CLAUDE.md
2. docs/02-DATA-MODEL.md      <- §5 RUMUS DASHBOARD (jantung hari ini)
3. docs/03-BUSINESS-FLOW.md   <- §5 dashboard
4. docs/04-FINDING-REGISTRY.md  <- F-52, F-96, F-97 BARU

LAPORKAN:
- Konfirmasi kamu paham F-96: beban DIBAGI RATA antar assignee, poin UTUH,
  realisasi per-user dari segmen
- F-97: 3 item browser masih tertunda. Kalau Chrome extension tersedia sekarang,
  angkat; kalau tidak, nyatakan "masih tertunda" dan lanjut (jangan ulang saran install)
- Rumus §5 mana yang butuh klarifikasi sebelum ditulis
- Checklist Fase A-C

BERHENTI. Tunggu Boss bilang "LANJUT".

## KONTEKS

BusinessHoursCalculator matang (HARDEN). Counter live jalan (H1). Realisasi
per-user sudah terpisah otomatis lewat task_time_segments.user_id.

Hari ini: mengubah rumus §5 data-model jadi QUERY NYATA + test. BELUM UI.
Ini fondasi angka yang admin lihat di Hari-3. Angka salah di sini = admin ambil
keputusan salah -> lebih berbahaya daripada tidak ada dashboard.

## FASE A — SERVICE DASHBOARD (rumus §5 + F-96)

A1. Buat app/Services/DashboardService.php
    Method per metrik, per user, untuk tanggal tertentu (default hari ini WIB).

A2. KAPASITAS (per user):
    users.daily_capacity_minutes ?? work_schedule aktif.daily_capacity_minutes
    (F-40: work_schedule versioned, pakai yang aktif untuk tanggal itu)

A3. 🔴 BEBAN (F-96a — DIBAGI RATA):
    Σ (estimated_minutes / jumlah_assignee) untuk task yang:
    - di-assign ke user
    - status BUKAN is_completed (F-44: pakai flag, bukan nama)
    - DATE(due_date) = tanggal itu  ATAU  due_date < sekarang (overdue)

    🔴 jumlah_assignee dihitung dari task_user. Task 4 jam, 2 assignee ->
    beban 2 jam per orang. Task tanpa assignee lain -> 4 jam penuh.
    Pembagian di LEVEL QUERY/agregasi, jangan ubah tasks.estimated_minutes
    (kolom itu tetap total task — F-39 semangatnya: data mentah tak diubah).

A4. BACKLOG (per user):
    Σ (estimated_minutes / jumlah_assignee) untuk task yang:
    - di-assign ke user
    - status BUKAN is_completed
    - DATE(due_date) > tanggal itu (masa depan)
    Pembagian sama dengan beban (F-96a).

A5. AKTIF (per user):
    Σ realisasi segmen TERBUKA milik user hari itu.
    Pakai BusinessHoursCalculator (F-94) — JANGAN hitung ulang wall-clock.
    Realisasi sudah per-user (segmen.user_id) -> TIDAK dibagi (beda dari beban).

A6. IDLE_PLAN  = KAPASITAS - BEBAN
    IDLE_REAL   = KAPASITAS - Σ realisasi user hari itu (segmen tertutup + terbuka)
    F-52: TAMPILKAN KEDUANYA nanti di UI. Service kembalikan dua-duanya.

A7. 🔴 F-53 ANOMALI: untuk task selesai hari itu, kalau realisasi > 3x estimasi
    -> tandai anomaly. Service kembalikan daftar task anomali per user.
    JANGAN hukum, JANGAN blokir — cuma tandai untuk admin lihat.

A8. POIN (F-96b — UTUH): kalau service menghitung poin per user (untuk konteks
    KPI mentah), poin task UTUH ke tiap assignee, TIDAK dibagi. Beda dari beban.
    (Poin belum jadi skor apa pun — itu v1.5. Ini cuma agregasi mentah.)

## FASE B — ENDPOINT/QUERY EFISIEN (F-85)

B1. Dashboard tim = banyak user sekaligus. 🔴 JANGAN N+1.
    JANGAN loop per user lalu query per user. Agregasi dalam query minimal:
    - satu query beban/backlog (group by user, join task_user, hitung assignee count)
    - satu query realisasi (group by user, dari segmen)
    Muat sekali, susun di memori.

B2. 🔴 assignee count untuk pembagian F-96a: hati-hati SQL.
    estimated_minutes / COUNT(assignee) per task, LALU jumlahkan per user.
    Bukan SUM(estimated_minutes) / SUM(assignee) — itu salah matematis.
    Test angka ini eksplisit (C2).

B3. Permission: dashboard butuh permission BARU. Katalog RBAC sekarang 7, belum
    ada dashboard. Tambah 'dashboard.view' (INSERT baris seeder — Gate::before
    data-driven, tidak perlu deploy kode, konfirmasi laporan RBAC).
    Beri ke role admin. JANGAN beri ke member (F-95: member nol permission).

B4. Endpoint mengembalikan data mentah (JSON/props) — Hari-3 yang merender.
    Boleh Inertia props untuk halaman dashboard kosong, atau endpoint terpisah.
    Pilih yang konsisten dengan pola app.

## FASE C — TEST (MySQL, F-83)

C1. tests/Feature/DashboardTest.php
    - kapasitas: user tanpa override -> pakai work_schedule; dengan override -> pakai override
    - beban: task due hari ini + overdue dihitung, task masa depan TIDAK
    - backlog: hanya task masa depan
    - task selesai TIDAK masuk beban/backlog (is_completed)
    - member tanpa dashboard.view -> 403
    - admin dengan dashboard.view -> 200

C2. 🔴 tests/Feature/DashboardMultiAssigneeTest.php (F-96 — WAJIB, ini inti hari ini)
    - task 240 menit, 2 assignee -> beban 120 tiap orang (F-96a dibagi rata)
    - task 240 menit, 1 assignee -> beban 240 (tidak dibagi)
    - task 300 menit, 3 assignee -> beban 100 tiap orang
    - realisasi: assignee A kerja 180m, B kerja 60m -> AKTIF/REAL A=180 B=60
      (per-user, TIDAK dibagi — beda dari beban)
    - poin task 50, 2 assignee -> poin 50 tiap orang (F-96b UTUH, tidak dibagi)

C3. tests/Feature/DashboardAnomalyTest.php (F-53)
    - realisasi > 3x estimasi -> muncul di daftar anomali
    - tidak otomatis mengubah status/skor apa pun

C4. 149 test lama tetap lulus (MySQL). F-78 berlaku.

## DILARANG KERAS

JANGAN buat UI dashboard -> Hari-3
JANGAN petakan poin/realisasi ke skor atau rupiah -> v1.5 (F-4 Goodhart)
JANGAN ubah tasks.estimated_minutes untuk pembagian (agregasi saja, A3)
JANGAN hitung ulang actual_minutes frozen (F-39)
JANGAN N+1 / loop-per-user (F-85)
JANGAN hardcode nama status (F-44)
JANGAN beri dashboard.view ke member (F-95)
JANGAN buat recurring/attachment/extension -> hari berikutnya
JANGAN scheduler/cron untuk counter (F-38)
JANGAN deploy / L13 (Boss: lokal dulu)
JANGAN install dependency tanpa approval
JANGAN edit dokumen docs/

## STANDAR KOMENTAR
CLAUDE.md §3. Header klasifikasi tiap file baru. Sebut F-N di komentar rumus
(khususnya F-96 di pembagian beban — fresh entry WAJIB paham kenapa beban dibagi
tapi realisasi & poin tidak).

## DEFINITION OF DONE

🔴 F-83: test di MySQL.

[ ] DashboardService kembalikan kapasitas/aktif/beban/backlog/idle_plan/idle_real per user
[ ] BEBAN task multi-assignee = estimasi ÷ jumlah assignee (test C2)
[ ] REALISASI per-user tidak dibagi (test C2)
[ ] POIN utuh tiap assignee (test C2)
[ ] pembagian per-task lalu dijumlah (bukan SUM/SUM — B2)
[ ] anomali realisasi > 3x estimasi terdaftar, tidak menghukum (F-53)
[ ] dashboard.view ada, admin punya, member tidak (F-95)
[ ] query dashboard tim NOL N+1 (F-85) — buktikan jumlah query
[ ] member akses endpoint dashboard -> 403
[ ] php artisan test -> SEMUA lulus MySQL (149 lama + baru)
[ ] npx tsc 0 error, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / DEVIASI (nol -> "NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum Boss "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Hari-2 sengaja backend-saja.** Angka dashboard adalah dasar keputusan admin — siapa di-assign lagi, siapa overload. Kalau rumusnya salah dan langsung dibungkus UI cantik, salahnya tersembunyi di balik tampilan. **Pisahkan: buktikan angkanya benar dulu (test), baru render (Hari-3).**

**Inti hari ini ada di F-96 — dan satu jebakan SQL.** Pembagian beban multi-assignee harus **per-task dulu baru dijumlah**, bukan `SUM(estimasi) / SUM(assignee)`. Contoh kenapa: task A 100m/1 orang + task B 100m/2 orang untuk user yang sama = harusnya 100 + 50 = 150. Kalau `SUM/SUM` = 200/3 = 67 — **salah total**. Test C2 mengunci ini.

**Kenapa beban dibagi tapi realisasi & poin tidak?**
- **Beban dibagi** — soal kapasitas: kalau 2 orang pegang task 4 jam, tidak realistis membebankan 4 jam ke masing-masing (F-96a, keputusan Boss)
- **Realisasi tidak dibagi** — sudah fakta per-user: segmen mencatat siapa kerja berapa lama (otomatis sejak v0.5)
- **Poin utuh** — memotivasi: kalau dibagi, orang hindari kerja bareng demi skor (F-96b, F-4 Goodhart)

Tiga angka, tiga logika berbeda — dan itu benar. Komentar kode wajib menjelaskan ini ke fresh entry, atau mereka akan "menyeragamkan" ketiganya dan merusak semantiknya.

**F-97 dibawa terus:** 3 item belum dilihat mata manusia di browser. Bukan blocker, tapi Jarvis menagihnya tiap LANGKAH 0 sampai Chrome extension tersedia. Boss kena pola ini sekali (F-73, `/login`) — Jarvis tidak biarkan menumpuk diam-diam.
