# PROMPT HARI-7 — PENUTUP v0.5 (SIAP DIPAKAI TIM)

> **Isi:** detail task (F-82) · trigger #3 (F-84) · test suite → MySQL (F-83) · N+1 (F-85) · persiapan produksi
> **Setelah hari ini v0.5 TUTUP.** Tim mulai memakai, dan data KPI mulai terkumpul.

---

## §0. YANG BOSS LAKUKAN DULU

Salin ulang 2 file:
```
CLAUDE.md                    <- +F-83 (test = engine produksi)
docs/04-FINDING-REGISTRY.md  <- +F-82..F-85 + catatan audit Hari-6
```

Dan buat database test (sekali saja):
```
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p -P 3307
```
```sql
CREATE DATABASE task_management_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca utuh:
1. CLAUDE.md                    <- F-83 BARU di §5
2. docs/03-BUSINESS-FLOW.md     <- §6 permission, §9 notifikasi
3. docs/04-FINDING-REGISTRY.md  <- F-82, F-83, F-84, F-85 BARU

LAPORKAN checklist Fase A-F, dependency-aware, + hal yang belum jelas.

BERHENTI. Tunggu Boss bilang "LANJUT".

## KONTEKS

Hari-6 lulus, 99 test, verifikasi Playwright terhadap MySQL nyata. Bagus.

Hari ini menutup 3 lubang yang KAMU sendiri laporkan di deviasi #3, #5, #6 —
ketiganya benar, dan dua di antaranya adalah kesalahan spec Jarvis, bukan kamu:

  Deviasi #6 -> F-82: tidak ada halaman detail task. Jarvis menulis "Task CRUD"
                tanpa memeriksa siapa yang MEMBACA hasilnya. Member tidak bisa
                membaca deskripsi tugasnya sendiri.
  Deviasi #5 -> F-84: spec 10-trigger Jarvis tumpang tindih. Kamu benar patuh
                pada spec; spec-nya yang keliru. Boss memutuskan opsi (b).
  Deviasi #3 -> F-83: test sqlite tidak menguji jalur produksi.

Setelah hari ini, v0.5 TUTUP dan tim mulai memakai aplikasi ini.
Mulai saat itu, F-39 berlaku pada data NYATA: angka yang dibekukan tidak bisa
diperbaiki lagi. Semua yang salah hari ini menjadi permanen besok.

## FASE A — HALAMAN DETAIL TASK (F-82) — PRIORITAS TERTINGGI

A1. Route: GET /projects/{project}/tasks/{task}
    scopeBindings() sudah aktif (F-76) - task milik project lain -> 404.

A2. Isi halaman:
    - Judul + badge status (warna dari task_status.color)
    - 🔴 DESKRIPSI RICH TEXT DIRENDER (bukan description_plain, bukan escaped)
      Ini SELURUH ALASAN halaman ini ada.
    - Metadata: assignee, priority, due_date, points, estimated_minutes, task_type
    - Kalau subtask: link ke parent
    - Kalau punya subtask: daftar subtask + statusnya
    - Tombol ubah status (lewat jalur service yang sama - F-45 tetap berlaku)
    - Admin: tombol Edit & Hapus. Member: TIDAK ADA (F-29)

A3. 🔴 RENDER HTML TIPTAP DENGAN AMAN.
    description berisi HTML dari editor. Merendernya mentah = celah XSS
    kalau ada yang menempelkan <script> lewat paste.
    Sanitasi sebelum render. JANGAN percaya HTML dari DB begitu saja.
    Kalau butuh library sanitizer: LAPOR nama paket + alasan, TUNGGU approval Boss.

A4. Permission:
    - Admin: semua task
    - Member: hanya task di project yang di-assign ke dia
    - Member BUKAN anggota project -> 404 (bukan 403 - jangan bocorkan keberadaannya)

A5. Klik dari List View / hasil search / notifikasi -> ke halaman ini.
    Perbaiki 3 tempat itu; sekarang semuanya mengarah ke List View project.

A6. [BROWSER] Bukti WAJIB:
    - Member login -> buka task yang di-assign -> DESKRIPSI RICH TEXT TERBACA
      (tebal tampil tebal, bukan tag mentah, bukan teks polos)
    - Member buka task project lain -> 404
    - Klik notifikasi -> mendarat di detail task, bukan List View

## FASE B — FIX TRIGGER #3 DOBEL (F-84)

B1. Masalah: admin approve -> assignee dapat 2 notif untuk 1 aksi
    (#3 "status berubah" + #7 "di-approve").

B2. Keputusan Boss: opsi (b).
    #3 hanya fire untuk transisi GENERIK yang tidak ditangkap trigger lain.
    Kalau transisi itu memicu #6 (masuk review) / #7 (approve) / #8 (reject),
    maka #3 DIAM.

B3. ALASAN (F-36): dua notif untuk satu aksi adalah persis kegagalan yang F-36
    coba cegah. Inbox banjir -> orang berhenti membaca -> trigger "lewat deadline"
    jadi tidak berguna, padahal itu sinyal KPI paling dasar Boss.

B4. 🔴 F-44: JANGAN deteksi pakai nama status. Pakai flag is_review / is_completed.

B5. Test: approve task -> assignee dapat TEPAT 1 notif, bukan 2.

## FASE C — TEST SUITE PINDAH KE MySQL (F-83)

C1. phpunit.xml: ganti sqlite in-memory -> MySQL, database task_management_test.
    Boss sudah membuat databasenya.

C2. 🔴 PERINGATAN - INI AKAN MEMBUAT TEST PECAH, DAN ITU TUJUANNYA.
    sqlite lenient, MySQL strict. Test yang lolos di sqlite bisa gagal di MySQL:
    collation, enum, FIELD(), json, tanggal.

    Setiap kegagalan = bug yang SELAMA INI TERSEMBUNYI.
    F-78 berlaku: perbaiki KODENYA, bukan test-nya. Kecuali test-nya memang
    salah asumsi soal sqlite.
    LAPORKAN setiap kegagalan + penyebabnya. Jangan diam-diam menambal.

C3. 🔴 HAPUS jalur LIKE di search (deviasi #3 Hari-6).
    Setelah test pakai MySQL, workaround sqlite tidak diperlukan lagi.
    Satu jalur kode = satu perilaku = test menguji yang benar-benar jalan.
    Dua jalur = salah satunya pasti membusuk (pelajaran F-72, F-76).

C4. 🔴 KEMUNGKINAN MASALAH: InnoDB FULLTEXT dan transaction.
    RefreshDatabase membungkus tiap test dalam transaction. Index FULLTEXT InnoDB
    di-update saat COMMIT - data yang dibuat dalam transaction mungkin TIDAK
    ditemukan MATCH AGAINST.
    Kalau test search gagal karena ini: pakai DatabaseMigrations (tanpa transaction)
    KHUSUS untuk SearchTest, bukan untuk semua test.
    LAPORKAN kalau ketemu - ini masalah nyata, bukan kesalahanmu.

C5. Guard FULLTEXT migration (F-67) tetap exclude sqlite - biarkan.
    Kalau nanti ada yang menjalankan test di sqlite, migration tidak boleh crash.

## FASE D — N+1 QUERY (F-85)

D1. Aktifkan di AppServiceProvider:
      Model::preventLazyLoading(! app()->isProduction());

D2. Jalankan test suite + buka halaman utama di browser.
    Setiap lazy load akan melempar exception -> perbaiki dengan eager loading (with()).

D3. Halaman yang paling rawan: List View (assignee+status+project),
    My Tasks (lintas project), detail task (subtask), notifikasi (task terkait).

D4. ALASAN: ini SATU-SATUNYA isu performa nyata di skala Boss (10 user, ~5rb task/thn).
    N+1 tidak terasa dengan 30 task seed; di 500 task, satu halaman = 500 query.
    preventLazyLoading membuatnya ketahuan saat DITULIS, bukan saat tim mengeluh lambat.

D5. JANGAN tambah cache/queue/index baru. Skala Boss tidak membutuhkannya, dan
    setiap lapisan harus dipelihara tim fresh entry selamanya.

## FASE E — PERSIAPAN PRODUKSI

E1. Seeder produksi TERPISAH dari seeder dev:
    - database/seeders/ProductionSeeder.php
    - 1 organization (nama asli - TANYA BOSS)
    - 1 work_schedule (Sen-Jum 08:00-17:00, kapasitas 480 - konfirmasi ke Boss)
    - 1 admin (email asli - TANYA BOSS, password acak yang dicetak sekali)
    - TIDAK ADA task/project dummy
    🔴 JANGAN mengarang nama organisasi/email. TANYA BOSS dulu.

E2. Buat docs/06-DEPLOYMENT.md - checklist untuk Boss, bahasa awam:
    - Syarat server (PHP 8.2+, MySQL 8, Node 20+)
    - Variabel .env produksi: APP_ENV=production, APP_DEBUG=false, APP_KEY baru
    - 🔴 APP_DEBUG=false WAJIB - kalau true, stack trace membocorkan kredensial DB
      ke siapa pun yang memicu error
    - php artisan migrate --force (BUKAN migrate:fresh - itu MENGHAPUS SEMUA DATA)
    - npm run build
    - Cron untuk scheduler (2 command notifikasi)
    - 🔴 BACKUP (F-13): perintah mysqldump + jadwal harian + cara restore
      ALASAN: data KPI tidak bisa direkonstruksi (F-51). Tanpa backup, satu
      kesalahan = hilang selamanya.
    - Checklist keamanan: ganti password default, HTTPS, .env tidak ter-commit

E3. Tulis daftar "JANGAN PERNAH di produksi" untuk Boss:
    - php artisan migrate:fresh (MENGHAPUS SEMUA DATA)
    - APP_DEBUG=true
    - .env masuk git

## FASE F — VERIFIKASI FINAL v0.5

F1. Jalankan ulang 13 item V05 (docs/01-PRD.md §5.1).
    V05-11 sekarang harus LENGKAP untuk 8 trigger (9/10 sengaja ditunda v0.8).

F2. Jawab jujur:
    - Ada yang diklaim selesai tapi belum diverifikasi browser?
    - Ada jalur kode yang tidak pernah dijalankan test?
    - Kalau tim mulai memakai besok, apa yang paling mungkin pecah?

## DILARANG KERAS DI HARI-7

JANGAN buat Board View / drag-drop -> v1.0
JANGAN buat dashboard / idle / beban / backlog -> v0.8
JANGAN buat attachment upload -> v0.8
JANGAN buat extension flow / trigger #9 #10 -> v0.8
JANGAN buat recurring engine -> v0.8
JANGAN buat comment / mention -> v1
JANGAN buat activity log UI -> v1
JANGAN buat tabel scoring/KPI/payroll -> v1.5 / v2.0
JANGAN tambah cache / queue / Redis / index baru (D5)
JANGAN buat scheduler untuk COUNTER (F-38)
JANGAN hardcode nama status (F-44)
JANGAN mengarang data produksi (E1) - TANYA BOSS
JANGAN install dependency tanpa approval Boss
JANGAN edit dokumen di docs/ SELAIN 06-DEPLOYMENT.md yang kamu buat sendiri

## DEFINITION OF DONE HARI-7

🔴 F-75: item [BROWSER] WAJIB dibuktikan di browser nyata.

[ ] [BROWSER] member buka task-nya -> DESKRIPSI RICH TEXT TERBACA (tebal = tebal)
[ ] [BROWSER] member buka task project lain -> 404
[ ] [BROWSER] klik notifikasi -> mendarat di DETAIL TASK
[ ] [BROWSER] klik hasil search -> mendarat di DETAIL TASK
[ ] [BROWSER] approve task -> assignee dapat TEPAT 1 notif (F-84)
[ ] phpunit.xml -> MySQL, bukan sqlite
[ ] grep "getDriverName() === 'sqlite'" di TaskController -> No matches (C3)
[ ] php artisan test -> SEMUA lulus DI MySQL (99 lama + baru)
[ ] Setiap test lama yang pecah karena MySQL -> DILAPORKAN + penyebabnya
[ ] preventLazyLoading aktif, test & browser bersih dari lazy loading
[ ] npx tsc --noEmit -> 0 error
[ ] ./vendor/bin/pint + npm run build + npm run lint -> bersih
[ ] docs/06-DEPLOYMENT.md ada, termasuk perintah backup + restore
[ ] ProductionSeeder ada, TIDAK berisi data dummy
[ ] 13 item V05 dilaporkan ulang

## FORMAT LAPORAN AKHIR

STATUS   : [SELESAI / BLOCKED / BUTUH KEPUTUSAN]
DIUBAH   : <daftar file>
BUKTI    : <perintah + output aktual + bukti browser>
MySQL    : <test lama yang pecah saat pindah engine + penyebabnya>
DEVIASI  : <kalau nol, tulis "NOL" eksplisit>
V0.5     : <13 item, masing-masing LENGKAP/SEBAGIAN/TIDAK ADA>
RISIKO   : <apa yang paling mungkin pecah saat tim mulai memakai>
NEXT     : <opsi + rekomendasi — TUNGGU keputusan Boss>

Mulai dari LANGKAH 0. Jangan tulis kode sebelum Boss bilang "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Fase A adalah alasan Hari-7 ada.**
Boss membayar Tiptap agar deskripsi task bisa kaya — lalu **tidak ada satu pun tempat pekerja bisa membacanya**. Admin bisa lewat form edit; member tidak bisa sama sekali. Ini lubang scope Jarvis: "Task CRUD" ditulis tanpa memeriksa **siapa yang membaca hasilnya**.

**Fase C akan membuat test pecah — dan itu tujuannya.**
sqlite pemaaf, MySQL galak. Setiap kegagalan adalah bug yang **selama ini tersembunyi di balik 99 test hijau**. Kalau tidak ada satu pun yang pecah, itu kabar bagus. Kalau ada lima, itu **lima bug yang tidak jadi mengenai tim Boss**.

**Jarvis menandai satu risiko teknis di C4:** InnoDB memperbarui index FULLTEXT saat *commit*, sementara test dibungkus transaction. Test search bisa gagal bukan karena kodenya salah. Sudah dituliskan solusinya supaya Claude Code tidak buang waktu menebak.

**Fase E butuh jawaban Boss** — Claude Code dilarang mengarang: **nama organisasi asli**, **email admin asli**, dan **konfirmasi jam kerja** (Sen–Jum 08:00–17:00, kapasitas 480 menit).

**Soal scalable — Jarvis tidak akan menjual sesuatu yang tidak Boss butuhkan.**
Di 10 user dan ~5rb task/tahun, MySQL polos sanggup bertahun-tahun. **Yang membuat sistem ini scalable bukan cache atau Redis — tapi yang sudah Boss bangun sejak Hari-1:** `organization_id` di semua tabel (F-5), activity log yang tidak bolong (F-51), angka yang dibekukan dan tidak bisa berubah retroaktif (F-39), skema KPI yang sudah tertanam menunggu diaktifkan. **Itu fondasi v3.0.** Satu-satunya isu performa nyata hari ini adalah N+1 — dan itu Fase D.

---

**Setelah Hari-7, empat keputusan menghadang sebelum tim benar-benar dilepas ke sistem:**

| ID | Isi | Kapan |
|---|---|---|
| **F-11** | Nama produk & branding | sebelum deploy |
| **F-12** | Hosting (VPS? cloud?) | sebelum deploy |
| **F-13** | Backup — perintahnya disiapkan Fase E, **jadwalnya keputusan Boss** | sebelum produksi |
| 🟠 **F-65** | **Upgrade Laravel 12 → 13.** Bug-fix L12 berakhir ±Agustus 2026 — **bulan depan** | secepatnya |

**Dan begitu tim mulai memakai, F-39 berlaku pada data nyata: angka yang dibekukan tidak bisa diperbaiki lagi.** Itu sebabnya F-57, F-69, dan F-79 dikejar mati-matian selagi datanya masih dummy.
