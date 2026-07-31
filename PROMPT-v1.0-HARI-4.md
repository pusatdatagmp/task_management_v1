# PROMPT v1.0 HARI-4 — ACTIVITY LOG UI (F-116)

> Menampilkan jejak audit yang SUDAH direkam sejak v0.5 (F-51) — belum pernah ada UI-nya.
> READ-ONLY. Log = sejarah, tidak bisa diedit/hapus (F-39 semangat). Data lokal.

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-116 (gating activity log), audit H3
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/02-DATA-MODEL.md (activity_logs) · docs/04-FINDING-REGISTRY.md
(F-116, F-51, F-95, F-85, F-106, F-90).

LAPORKAN:
- Konfirmasi: log global di-gate activity.view (F-116); timeline per-task membership (F-95); READ-ONLY
- INVENTARIS event: daftar SEMUA tipe event yang ADA di activity_logs sekarang
  (grep event constants + seeder). Ini bahan pemetaan label manusiawi. Termasuk
  yang non-standar: recurring_assignee_dropped (F-106), dan lain yang kamu temukan
- F-97: 9 item tertunda. Extension tersedia?
- Checklist Fase A-C
BERHENTI. Tunggu Boss "LANJUT".

## KONTEKS

activity_logs merekam sejak v0.5 (F-51) — sumber 4/6 metrik KPI. Selama ini "kotak
hitam" tanpa UI. Hari ini: menampilkannya. TIDAK menambah/mengubah event (itu sudah
ada dari observer). Hanya MENAMPILKAN.

🔴 READ-ONLY MUTLAK: log tidak bisa diedit/dihapus dari UI (F-39 semangat — jejak =
sejarah). Tidak ada tombol hapus/edit. Tidak ada endpoint mutasi log.

## FASE A — LOG GLOBAL (F-116)

A1. Halaman activity log global. Permission BARU 'activity.view':
    - INSERT ke katalog permission (RolePermissionSeeder) — Gate::before data-driven
      (F-90), nol deploy kode
    - Beri ke admin. TIDAK ke member (F-116)
    - Bisa diberikan ke peran lain lewat UI peran yang sudah ada (RBAC)
A2. Tampilan: timeline/tabel event, terbaru dulu. Tiap baris:
    - Pelaku (user), aksi (LABEL MANUSIAWI), objek (task/project), waktu (WIB, F-72)
    - Contoh: "Budi mengubah status 'Integrasi API' → DIKERJAKAN · 2 jam lalu"
A3. 🔴 LABEL MANUSIAWI, bukan string mentah (F-106):
    Petakan SETIAP event type -> kalimat Indonesia. recurring_assignee_dropped ->
    "Assignee X dilepas dari tugas berulang (bukan member lagi)". JANGAN tampilkan
    'recurring_assignee_dropped' mentah ke user. Buat peta lengkap dari inventaris LANGKAH 0.
A4. Filter: per user, per tipe event, rentang tanggal. Server-side (pola v0.5 H5),
    tercermin di URL.
A5. 🔴 PAGINASI + EAGER LOADING (F-85): log bisa ribuan baris. Paginate (mis. 50/hal).
    Eager load user & subject -> JANGAN N+1 (satu query user per baris = bencana).
    Buktikan jumlah query konstan.

## FASE B — TIMELINE PER-TASK (F-95)

B1. Di detail task: bagian "Riwayat" — event activity_log untuk task ITU saja.
    Terbaru dulu, label manusiawi (A3).
B2. Akses: siapa boleh lihat task (assignee, member project, admin) boleh lihat
    riwayatnya (F-95 membership). Bukan permission activity.view — itu untuk GLOBAL.
    Member lihat riwayat tugasnya sendiri; itu wajar & berguna.
B3. Read-only. Tidak ada aksi.

## FASE C — TEST (MySQL, F-83)

C1. tests/Feature/ActivityLogTest.php
    - admin (punya activity.view) buka log global -> 200
    - member (tanpa activity.view) buka log global -> 403 (F-116)
    - peran dengan activity.view diberikan -> bisa akses (F-90 dinamis)
    - filter user/tipe/tanggal -> hasil benar
    - 🔴 log global query -> NOL N+1 (buktikan jumlah query, F-85)
    - tidak ada endpoint edit/hapus log (read-only)
C2. tests/Feature/TaskTimelineTest.php
    - member lihat riwayat tugasnya -> 200, event task itu saja
    - member lihat riwayat task project lain -> 404 (F-95)
    - label event ter-render manusiawi, bukan string mentah (F-106)
C3. 240 test lama tetap lulus. F-78.

## DILARANG KERAS

JANGAN buat endpoint edit/hapus activity_log (READ-ONLY, F-39)
JANGAN tampilkan event string mentah ke user (F-106 — label manusiawi)
JANGAN N+1 di log global (F-85 — eager load + paginate)
JANGAN beri activity.view ke member default (F-116)
JANGAN pakai activity.view untuk timeline per-task (F-95 membership, B2)
JANGAN tambah/ubah event observer (cuma menampilkan yang ada)
JANGAN deploy/L13 · JANGAN edit docs/ · JANGAN dependency tanpa approval

## STANDAR KOMENTAR
CLAUDE.md §3. Sebut F-116 (gating), F-106 (label), F-85 (eager load).

## DEFINITION OF DONE

🔴 F-83 test MySQL. F-75 [BROWSER] kalau extension tersedia.

[ ] activity.view ada (admin default, assignable), member tanpa -> 403 log global
[ ] log global: label manusiawi semua event (termasuk F-106), bukan string mentah
[ ] log global NOL N+1 (jumlah query dibuktikan, F-85) + paginasi
[ ] timeline per-task: member lihat tugasnya (F-95), task lain -> 404
[ ] tidak ada endpoint mutasi log (read-only)
[ ] filter server-side + URL
[ ] php artisan test -> SEMUA lulus MySQL (240 lama + baru)
[ ] npx tsc 0, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / PETA LABEL EVENT (daftar event->kalimat) / DEVIASI (nol->"NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Hari ini membuka kotak hitam.** Sejak v0.5, setiap perubahan status, assign, approve, tolak — semua terekam diam-diam di activity_log (F-51). Itu sumber 4 dari 6 metrik KPI Boss, tapi belum pernah bisa dilihat manusia. Hari ini ia punya wajah: siapa melakukan apa, kapan. Untuk Boss yang membangun sistem penilaian kinerja, ini alat audit paling langsung — "kenapa tugas ini telat?" bisa ditelusuri ke urutan kejadian nyata, bukan ingatan orang.

**Label manusiawi (F-106) itu bukan kosmetik.** Log menyimpan event sebagai kode: `recurring_assignee_dropped`, `status_changed`. Kalau ditampilkan mentah, admin melihat jargon dan berhenti memakainya. Diterjemahkan jadi kalimat Indonesia, log jadi cerita yang bisa dibaca siapa saja. Peta lengkap event→kalimat dibangun dari inventaris nyata di LANGKAH 0, bukan tebakan.

**Read-only mutlak — dan itu prinsip, bukan batasan malas.** Log yang bisa diedit bukan audit trail; itu draf. Seluruh nilai jejak audit ada pada tidak bisa diubahnya. Ini semangat F-39 yang sama: sekali tercatat, jadi sejarah. Tidak ada tombol hapus, tidak ada endpoint mutasi.

**Gating yang Boss pilih (F-116) tepat untuk sistem menuju payroll.** Log global memperlihatkan pola kerja semua orang — data pengawasan. Boss memilih permission `activity.view` (admin + peran tertentu), bukan terbuka ke semua. Timeline per-task tetap terbuka untuk yang mengerjakan. Jadi member lihat riwayat tugasnya sendiri (berguna), tapi tidak memantau semua orang (tidak sehat). Keseimbangan yang benar sebelum uang terlibat.

**F-97 kini 9 item — dan H4 tidak menambah banyak** (log UI sebagian besar bisa dilihat dari data, tapi tetap masuk checklist). H5 adalah penutup: buffer, verifikasi integrasi, DAN tutup F-97. Jarvis akan menuntut tumpukan 9 item itu dilihat sebelum v1.0 dinyatakan tuntas. Sembilan sesi tanpa mata manusia adalah utang terbesar yang tersisa — bukan bug, tapi risiko yang hanya manusia bisa tutup.

**Peta v1.0:** ~~H1~~ ~~H2~~ ~~H3~~ -> H4 activity log UI -> H5 buffer + verifikasi integrasi + TUTUP F-97 -> v1.0 TUNTAS.
