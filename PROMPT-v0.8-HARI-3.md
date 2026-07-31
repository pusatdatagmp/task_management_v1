# PROMPT v0.8 HARI-3 — DASHBOARD UI (F-52)

> **Merender angka yang sudah dibuktikan benar di Hari-2.** Tidak ada rumus baru.
> Data lokal. Tidak deploy.

---

## §0. YANG BOSS LAKUKAN DULU

Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-98 (atribusi realisasi), +F-99 (tsc)
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA, REKONSILIASI, LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/03-BUSINESS-FLOW.md §5 · docs/04-FINDING-REGISTRY.md (F-98, F-99).

### 0A — REKONSILIASI tsc (F-99) — WAJIB dijawab
Laporan H2 bilang "tsc 3 error pre-existing sejak Hari-5". Itu janggal:
use-live-counter.test.ts DIBUAT di H1 v0.8, dan laporan H1 klaim "tsc 0 error".
Jawab jujur:
- npx tsc --noEmit sekarang -> berapa error, di file apa?
- Apakah 3 error ini dari use-live-counter.test.ts (H1) atau benar dari Hari-5?
- Kalau dari H1: kenapa H1 lolos klaim tsc=0? (tsconfig exclude? file belum final?)
- Bisa dibawa ke 0 tanpa install dependency? (mis. exclude file node --test dari
  tsconfig, atau // @ts-nocheck di file test yang memang dijalankan node bukan tsc)
  Kalau bisa tanpa dependency -> perbaiki. Kalau butuh dependency -> LAPOR, jangan install.

### 0B — F-97 utang browser
3 item (2 HARDEN + counter menick) + sekarang dashboard = 4 hal belum dilihat browser.
Chrome extension tersedia? Kalau ya, ini saat verifikasi (dashboard admin-facing,
paling penting dilihat). Kalau tidak, nyatakan "masih tertunda", lanjut.

Lalu LAPORKAN checklist Fase A-B.
BERHENTI. Tunggu Boss bilang "LANJUT".

## KONTEKS

DashboardService sudah benar & teruji (H2, 162 test). Endpoint dashboard/summary
mengembalikan JSON. Hari ini: MERENDER-nya. Nol rumus baru. Kalau butuh mengubah
angka, itu tanda ada yang salah di H2 -> LAPOR, jangan tambal di UI.

## FASE A — HALAMAN DASHBOARD (F-52)

A1. Halaman /dashboard (permission dashboard.view, sudah ada dari H2).
    Redirect admin ke sini setelah login (member tetap ke My Tasks).

A2. 🔴 F-52 — TAMPILKAN TIGA ANGKA, JANGAN SATU:
    Tabel tim, satu baris per user:
      Nama | Aktif | Beban hari ini (idle plan) | Backlog | Anomali
    Contoh baris: "Budi | 2j 15m | 6j (idle 2j) | 20j | 0"
    Satu angka bohong; tiga angka jujur (F-52).

A3. Visualisasi idle:
    - IDLE_PLAN menonjol (ini yang dipakai admin untuk assign)
    - user overload (beban > kapasitas) ditandai jelas (mis. warna)
    - user idle tinggi juga ditandai (kapasitas nganggur)
    - IDLE_REAL ditampilkan tapi sekunder (efisiensi, bukan keputusan assign)

A4. 🔴 KEJELASAN MULTI-ASSIGNEE (F-96): kalau admin bingung kenapa task 4 jam
    cuma menyumbang 2 jam ke beban seseorang, beri tooltip/catatan kecil:
    "beban dibagi rata antar assignee". Jangan biarkan angka tampak salah tanpa
    penjelasan -> admin akan lapor bug yang bukan bug.

A5. Anomali (F-53): tampilkan daftar/badge task anomali (realisasi > 3x estimasi).
    🔴 JANGAN gambarkan sebagai pelanggaran/hukuman. Label netral:
    "perlu ditinjau", bukan "melanggar". Ini rem Goodhart (F-4), bukan vonis.

A6. Filter: per tanggal (default hari ini WIB), per user opsional.
    Filter tercermin di URL (pola v0.5 Hari-5) supaya bisa di-bookmark/refresh.

A7. 🔴 JANGAN tampilkan rupiah / skor / reward / punishment. Itu v1.5/v2.0.
    Dashboard v0.8 = cermin waktu & beban, BUKAN penilaian. F-4.

## FASE B — TEST

B1. tests/Feature (render + akses):
    - admin buka /dashboard -> 200, data tampil
    - member buka /dashboard -> 403 (F-95, dashboard.view)
    - angka yang dirender = angka dari DashboardService (tidak dihitung ulang di UI)

B2. Kalau ada logika format di frontend (jam:menit, warna threshold):
    unit test kecil (pola use-live-counter H1, node --test, nol dependency).

B3. 162 test lama tetap lulus. F-78 berlaku.

## DILARANG KERAS

JANGAN hitung ulang angka di UI (angka dari service — kalau salah, lapor bukan tambal)
JANGAN tampilkan rupiah/skor/reward (F-4, v1.5/v2.0)
JANGAN gambarkan anomali sebagai hukuman (F-53)
JANGAN tampilkan cuma 1 angka idle (F-52 — tiga angka)
JANGAN beri akses dashboard ke member (F-95)
JANGAN ubah DashboardService (itu H2, sudah teruji)
JANGAN buat recurring/attachment/extension -> hari berikutnya
JANGAN scheduler/cron counter (F-38)
JANGAN deploy/L13
JANGAN install dependency tanpa approval
JANGAN edit dokumen docs/

## STANDAR KOMENTAR
CLAUDE.md §3. Header klasifikasi tiap file baru.

## DEFINITION OF DONE

🔴 F-83 test MySQL. F-75 [BROWSER] kalau extension tersedia.

[ ] tsc direkonsiliasi (F-99): berapa error, kenapa, dibawa ke 0 tanpa dependency ATAU dilaporkan
[ ] [BROWSER] /dashboard tampil 3 angka per user (kalau extension ada)
[ ] admin -> 200, member -> 403
[ ] angka UI = angka service (tidak dihitung ulang)
[ ] tooltip multi-assignee ada (F-96 — cegah lapor-bug-palsu)
[ ] anomali label netral, bukan hukuman (F-53)
[ ] tidak ada rupiah/skor di UI (F-4)
[ ] filter tercermin di URL
[ ] php artisan test -> SEMUA lulus MySQL (162 lama + baru)
[ ] pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI (+browser jika ada) / DEVIASI (nol -> "NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0 (0A rekonsiliasi tsc, 0B browser). Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Hari-3 murni merender.** Angka sudah dibuktikan benar di Hari-2 (162 test). Aturan kerasnya: **kalau UI perlu mengubah angka, itu tanda ada yang salah di backend — lapor, jangan tambal di tampilan.** Menambal angka di UI menyembunyikan bug rumus di balik layar cantik.

**Dua hal yang Jarvis jaga ketat di UI ini:**

**F-52 — tiga angka, bukan satu.** Kalau dashboard cuma menampilkan "idle 2 jam", itu bohong: tidak membedakan orang yang idle karena belum di-assign (bisa terima kerja) dari orang yang idle karena efisien (kerjanya cepat). Aktif + Beban + Backlog bersama baru jujur.

**F-4 — nol rupiah, nol skor.** Ini cermin waktu, bukan vonis. Begitu angka dipetakan ke uang, tim berhenti mengoptimalkan hasil dan mulai mengoptimalkan angka (Goodhart). Itu v1.5, lewat review manusia — bukan v0.8.

**Tooltip multi-assignee (A4) kecil tapi penting.** Tanpa itu, admin lihat task 4 jam menyumbang 2 jam ke beban Budi, mengira sistem salah hitung, lalu lapor "bug". Satu kalimat penjelasan mencegah jam kerja terbuang mengejar bug yang bukan bug.

**F-99 direkonsiliasi di LANGKAH 0.** Bukan karena 3 error tsc di file test itu berbahaya — tapi karena "tsc 0" harus tetap jadi klaim yang bisa dipercaya. Begitu satu klaim hijau terbukti tidak akurat dan dibiarkan, semua klaim hijau kehilangan bobot. Boss sudah kena versi besarnya sekali (F-73).

**Peta v0.8:** ~~H1 counter~~ ~~H2 dashboard backend~~ -> H3 dashboard UI -> H4 recurring (terbesar) -> H5 attachment -> H6 extension -> H7 buffer.
