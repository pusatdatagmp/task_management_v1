# PROMPT v0.8 HARI-7 — BUFFER + VERIFIKASI UTUH + TUTUP F-97 (PENUTUP v0.8)

> Hari terakhir v0.8. Nol fitur baru. Verifikasi seluruh sistem end-to-end +
> WAJIB tutup utang F-97 (6 item browser). Data lokal.

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-108 (aturan extension), audit H6
```

🔴 **Dan siapkan dev server + browser sungguhan** untuk verifikasi F-97 (§ khusus di bawah).

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/01-PRD.md §5.2 daftar V08-* · docs/04-FINDING-REGISTRY.md (F-97 + semua v0.8).

LAPORKAN:
- Status Chrome extension: TERSEDIA atau TIDAK. Ini menentukan jalur verifikasi F-97
- Checklist Fase A-C
- Apa pun yang menurutmu masih menggantung dari H1-H6
BERHENTI. Tunggu Boss "LANJUT".

## KONTEKS

v0.8 fitur SELESAI (counter, dashboard, recurring, attachment, extension, notif 10).
Hari ini: TIDAK ADA FITUR BARU. Verifikasi utuh + tutup utang verifikasi visual +
integrasi antar-pilar. Kalau nemu bug integrasi, PERBAIKI + lapor.

## FASE A — TUTUP F-97 (6 ITEM BROWSER) 🔴 PRIORITAS

Utang 6 sesi: 2 HARDEN (hari libur, unassign member) + counter tick + dashboard UI
+ template UI + attachment. Belum pernah dilihat mata manusia (pola F-73).

A1. KALAU Chrome extension TERSEDIA: verifikasi keenam lewat browser nyata (Playwright/
    ekstensi), screenshot tiap satu. Untuk masing-masing:
    - Hari libur: tambah libur -> segmen yang melewatinya berkurang
    - Unassign member bertask-aktif -> pesan tolak MUNCUL di React
    - Counter: task IN_PROGRESS di jam kerja -> angka menick; di luar jam -> statis
    - Dashboard: 3 angka per user tampil, bar animasi, tooltip multi-assignee
    - Template: halaman kelola template render + form
    - Attachment: upload output, download, hapus (admin), terkunci pasca-approve

A2. KALAU extension TETAP TIDAK TERSEDIA:
    Buat CHECKLIST-VERIFIKASI-MANUAL.md di root — langkah klik per item (URL, aksi,
    hasil yang diharapkan) supaya BOSS bisa verifikasi manual 15 menit di dev server.
    Jangan klaim F-97 tertutup tanpa mata manusia. Nyatakan jujur: menunggu Boss.

A3. Apa pun jalurnya: laporkan status TIAP item (LULUS browser / MENUNGGU manual).

## FASE B — VERIFIKASI INTEGRASI ANTAR-PILAR

Test end-to-end yang melintasi fitur (bukan unit terisolasi). Di MySQL (F-83):

B1. tests/Feature/IntegrationV08Test.php — skenario nyata lintas-fitur:
    - Recurring generate task -> muncul di dashboard beban assignee -> counter jalan
      saat IN_PROGRESS -> upload output -> REVIEW -> admin approve -> realisasi beku +
      attachment terkunci + dashboard beban berkurang
    - Task multi-assignee dari template -> beban DIBAGI di dashboard (F-96), realisasi
      per-user, poin utuh
    - Extension approve -> due_date geser tapi original_due_date utuh (F-47) ->
      dashboard/overdue pakai due_date baru

B2. 🔴 KONSISTENSI ANGKA lintas-fitur:
    - Angka realisasi di counter (live) vs di dashboard vs di detail task -> SAMA
      sumbernya (BusinessHoursCalculator, F-94). Buktikan tidak ada 3 hitungan beda
    - Beban dashboard = Σ(estimasi÷assignee) konsisten dengan yang tampil di detail

B3. Anomali (F-53): task realisasi > 3x estimasi -> muncul di dashboard, TIDAK
    menghukum otomatis.

## FASE C — VERIFIKASI CAKUPAN v0.8 (PRD §5.2)

C1. Cek satu per satu, tulis LENGKAP/SEBAGIAN/TIDAK ADA + bukti:
    V08-1  Counter live (F-38 calculated, F-94 konsisten)
    V08-2  Dashboard 3 metrik + idle (F-52)
    V08-3  Beban multi-assignee dibagi (F-96)
    V08-4  Recurring engine (F-100/101/102 aturan tanggal)
    V08-5  Attachment output + keamanan (F-104/105/107)
    V08-6  Extension flow + original_due_date (F-47)
    V08-7  Notifikasi 10 trigger (F-35)
    V08-8  Holidays terintegrasi business-hours (F-43)
    V08-9  Business-hours matang per-hari (F-66)

C2. Jawab jujur (pertanyaan F-73):
    - Ada fitur diklaim selesai tapi belum pernah diverifikasi browser? (F-97)
    - Ada jalur kode yang tidak pernah dijalankan test?
    - Kalau tim mulai pakai besok, apa yang paling mungkin pecah?

C3. RINGKASAN KESEHATAN SISTEM:
    - total test, total assertion
    - tsc/pint/build/lint status
    - utang teknis tersisa (daftar)
    - finding TERBUKA di registry (F-59, F-97 kalau belum tutup, dll)

## DILARANG KERAS

JANGAN buat fitur baru (hari verifikasi)
JANGAN klaim F-97 tutup tanpa mata manusia (browser/manual Boss)
JANGAN ubah rumus/angka yang sudah beku (F-39)
JANGAN deploy/L13 (Boss: lokal) · JANGAN edit docs/ SELAIN buat CHECKLIST-VERIFIKASI-MANUAL.md
JANGAN dependency tanpa approval

## DEFINITION OF DONE

[ ] F-97: 6 item terverifikasi browser ATAU CHECKLIST-VERIFIKASI-MANUAL.md dibuat + status jujur tiap item
[ ] IntegrationV08Test lulus (skenario lintas-fitur end-to-end)
[ ] konsistensi angka realisasi counter=dashboard=detail dibuktikan (F-94)
[ ] beban multi-assignee konsisten lintas tampilan (F-96)
[ ] 9 item V08 dilaporkan satu per satu
[ ] 3 pertanyaan kejujuran F-73 dijawab
[ ] ringkasan kesehatan sistem (test/tsc/pint/build + utang + finding terbuka)
[ ] php artisan test -> SEMUA lulus MySQL (210 lama + baru)
[ ] npx tsc 0, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / F-97 (status tiap item) / V0.8 (9 item) / KESEHATAN SISTEM /
KEJUJURAN (3 jawaban) / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Hari ini bukan menambah — hari ini membuktikan.** Enam hari v0.8 membangun enam fitur; H7 memastikan keenamnya bekerja **bersama**, bukan cuma sendiri-sendiri. Test integrasi Fase B mengejar hal yang test unit tidak bisa: apakah angka realisasi yang sama muncul konsisten di counter, dashboard, dan detail task — atau diam-diam ada tiga hitungan berbeda. Itu jenis bug yang cuma muncul saat fitur bertemu.

**F-97 harus tutup hari ini — dan Jarvis tidak akan melunak soal ini.** Enam layar dibangun, teruji ratusan kali lewat test, tapi belum pernah dilihat mata manusia. Boss kena pola ini persis sekali: `/login` mati tiga hari sambil semua test hijau (F-73). Kalau Chrome extension akhirnya tersedia, Claude Code verifikasi + screenshot. Kalau tidak, dia buatkan **checklist klik 15 menit** untuk Boss — dan v0.8 tidak Jarvis nyatakan tutup sampai Boss sendiri mengklik keenamnya. Test hijau bukan bukti layar hidup.

**Kejujuran Fase C2 adalah penutup yang benar.** Tiga pertanyaan itu — ada yang belum diverifikasi? ada jalur tak teruji? apa yang paling mungkin pecah? — memaksa sistem menghadapi dirinya sendiri sebelum dianggap selesai. Jawaban jujur di sini lebih berharga daripada klaim "semua sempurna".

**Setelah H7, v0.8 TUTUP.** Yang Boss punya: task manager (v0.5) + performance layer (v0.8) — dashboard idle/beban, counter hidup, recurring, attachment, extension, RBAC dinamis. Semua lokal, teruji, dengan 108 finding jadi jejak keputusan permanen.

**Yang menanti setelah v0.8 (kalau Boss mau lanjut):** upgrade L13 (bug-fix L12 habis ±Agu) · deploy (jam data KPI mulai) · v1.5 scoring (butuh ≥1 bulan data nyata) · lalu payroll v2.0 · marketplace v3.0. Tapi itu semua keputusan Boss — Jarvis tidak mendorong deploy sampai Boss mengangkatnya.
