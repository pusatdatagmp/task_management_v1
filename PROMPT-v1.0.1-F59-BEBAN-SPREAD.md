# PROMPT v1.0.1 — SEBAR BEBAN (F-59 opsi B, F-118)

> Tugas terfokus, BUKAN fitur baru. Mengubah rumus BEBAN dashboard supaya task besar
> disebar ke hari kerja sampai tenggat. KPI-calc — hati-hati. Data lokal, dummy (aman
> ubah rumus sekarang, sebelum deploy — F-39 belum berlaku).

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- F-59 ditutup (opsi B), +F-118 (mekanik sebar), audit H5
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/03-BUSINESS-FLOW.md §5 (rumus dashboard)
· docs/04-FINDING-REGISTRY.md (F-59, F-118, F-96, F-52, F-43, F-85, F-94, F-53).
Baca kode: app/Services/DashboardService.php (rumus beban H2 v0.8).

LAPORKAN:
- Ringkas dengan kata-katamu rumus beban SEKARANG vs yang DIMINTA (sebar)
- Konfirmasi paham F-118: (estimasi÷assignee)÷hari-kerja(today→tenggat), estimasi PENUH,
  overdue→hari ini, hanya hari kerja (F-43)
- 🔴 PENTING: konfirmasi ini TIDAK menyentuh REALISASI (F-94 counter=dashboard=freeze).
  Beban = perencanaan (estimasi). Realisasi = eksekusi. Sebar hanya ubah BEBAN
- Rencana jaga N+1 (F-85): hari libur di-load SEKALI, hitung hari-kerja murni tanpa query/task
- Checklist Fase A-C
BERHENTI. Tunggu Boss "LANJUT".

## KONTEKS

Beban sekarang (F-96): Σ(estimasi÷assignee) untuk task yang jatuh tempo. Masalah:
task 40 jam di-assign 1 orang tampil "beban 40 jam" lawan kapasitas 8 jam/hari →
orang tampak overload padahal kerjanya tersebar seminggu.

Boss putuskan opsi B (F-59): SEBAR beban ke hari kerja. Task 40 jam, 1 assignee,
tenggat 5 hari kerja lagi → 8 jam/hari.

## FASE A — RUMUS SEBAR (F-118) — INTI

A1. Kontribusi beban HARI INI dari sebuah task, untuk tiap assignee:
    per_assignee_total = estimasi ÷ jumlah_assignee            (F-96, divide DULU)
    hari_kerja = jumlah HARI KERJA dari today s/d tenggat inklusif
                 (lewati weekend + hari libur, pakai logika F-43 yang ADA)
    kontribusi_hari_ini = per_assignee_total ÷ hari_kerja

    🔴 URUTAN: bagi antar-assignee DULU (F-96), BARU sebar antar-hari (F-118).

A2. ESTIMASI PENUH, bukan sisa-kerja. Task 90% selesai tetap pakai estimasi penuh.
    Beban = metrik PERENCANAAN, tidak dikurangi realisasi. Progres tampil terpisah
    lewat counter (F-94). JANGAN campur realisasi ke beban.

A3. Kasus tepi:
    - Tenggat HARI INI → hari_kerja=1 → seluruh per_assignee_total hari ini
    - OVERDUE (tenggat lewat) → tidak bisa sebar ke masa lalu → seluruh
      per_assignee_total hari ini (memang harus tampak berat)
    - Tenggat di weekend/libur → hari kerja terakhir dihitung sesuai F-43

A4. 🔴 REUSE logika hari-kerja/libur yang ADA (BusinessHoursCalculator / Holiday,
    F-43). JANGAN tulis kalkulator hari-kerja baru (F-72/F-76 — kalkulator kembar).

A5. Backlog & IDLE_PLAN ikut menyesuaikan (jaga F-52 tiga angka tetap koheren):
    - beban_hari_ini = Σ kontribusi_hari_ini semua task berjalan assignee itu
    - backlog = Σ (per_assignee_total − kontribusi_hari_ini) = porsi hari-hari MENDATANG
    - idle_plan = kapasitas − beban_hari_ini
    Pastikan tiga angka tetap jujur & jumlahnya masuk akal. Jelaskan di laporan.

## FASE B — UI + SEMANTIK

B1. 🔴 Tooltip/label dashboard DIPERBARUI: jelaskan "beban hari ini = porsi hari ini
    dari kerja berjalan (disebar sampai tenggat)", bukan "kerja jatuh tempo hari ini".
    Tanpa ini admin bingung kenapa task 40 jam cuma nyumbang 8 jam (sama seperti
    tooltip F-96 mencegah lapor-bug-palsu).
B2. Task overload (beban > kapasitas) tetap ditandai — tapi sekarang overload BERARTI
    sesuatu (kerja hari ini benar-benar melebihi kapasitas), bukan artefak task besar.

## FASE C — TEST (MySQL, F-83)

C1. tests/Feature/DashboardBebanSpreadTest.php
    - task 40 jam (2400m), 1 assignee, tenggat 5 hari kerja lagi → beban hari ini 480m (8j)
    - task 40 jam, 2 assignee, tenggat 5 hari kerja → 240m per orang hari ini (F-96 lalu sebar)
    - tenggat HARI INI → seluruh estimasi hari ini
    - OVERDUE → seluruh estimasi hari ini (A3)
    - tenggat menyeberang weekend → hari kerja saja yang dihitung (F-43)
    - hari libur di rentang → dilewati (F-43)
    - 🔴 N+1: beban banyak task banyak user → jumlah query KONSTAN (F-85), buktikan angka
    - 🔴 REALISASI tidak berubah: test F-94 lama (counter=dashboard=freeze) tetap lulus
C2. Test dashboard LAMA yang mengasumsikan beban=estimasi-penuh-jatuh-tempo:
    sesuaikan (F-78 — perilaku sengaja berubah, update SETUP/assertion sesuai rumus baru,
    JANGAN tambal supaya hijau). Laporkan mana yang diubah & kenapa.
C3. IntegrationV10Test: sesuaikan kalau angka beban di dalamnya berubah (F-78).
C4. 251 test lama tetap lulus (kecuali yang sengaja disesuaikan di C2/C3). F-78.

## DILARANG KERAS

JANGAN campur realisasi ke beban (F-118 — estimasi penuh, beban=perencanaan)
JANGAN sentuh rumus/angka REALISASI (F-94 — cuma beban yang berubah)
JANGAN tulis kalkulator hari-kerja/libur baru (F-43/F-72 — reuse)
JANGAN sebar ke masa lalu untuk overdue (A3 — hari ini)
JANGAN bagi antar-hari SEBELUM bagi antar-assignee (A1 urutan: assignee dulu)
JANGAN tambal test supaya hijau (F-78 — sesuaikan jujur, laporkan)
JANGAN fitur baru · JANGAN deploy/L13 · JANGAN edit docs/ · JANGAN dependency

## STANDAR KOMENTAR
CLAUDE.md §3. Sebut F-59/F-118 di komentar rumus sebar (urutan assignee→hari,
kenapa estimasi penuh, kenapa overdue→hari ini).

## DEFINITION OF DONE

🔴 F-83 test MySQL. F-75 [BROWSER] dashboard masuk F-97 (sudah antre).

[ ] beban disebar: 40j/5 hari kerja → 8j/hari (F-118)
[ ] urutan assignee(F-96)→hari(F-118) benar
[ ] overdue & tenggat-hari-ini → seluruh estimasi hari ini
[ ] weekend/libur dilewati saat sebar (F-43, reuse)
[ ] backlog & idle_plan koheren, F-52 tiga angka tetap jujur
[ ] tooltip diperbarui (B1 — cegah lapor-bug-palsu)
[ ] N+1 konstan dibuktikan angka (F-85)
[ ] REALISASI tak berubah — test F-94 tetap lulus
[ ] test lama yang berubah: disesuaikan jujur + dilaporkan (F-78)
[ ] php artisan test → SEMUA lulus MySQL
[ ] npx tsc 0, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / TEST YANG DISESUAIKAN (F-78, mana+kenapa) / DEVIASI (nol→"NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Ini pekerjaan kecil tapi penting, dan waktunya sengaja sekarang — sebelum deploy.** Mengubah cara beban dihitung setelah data nyata masuk akan membelah riwayat: laporan bulan lalu memakai rumus lama, bulan ini rumus baru, dan dashboard jadi tidak bisa dibandingkan. Selagi data masih dummy, perubahan ini bersih total — `migrate:fresh` dan semua konsisten. Ini insting yang sama yang menyelamatkan empat angka KPI sebelumnya (F-57/69/93/112): perbaiki rumus selagi belum ada yang bergantung padanya.

**Yang berubah cuma beban, bukan realisasi — dan itu batas yang Jarvis jaga ketat.** Realisasi (waktu kerja nyata) adalah fakta terukur; konsistensinya lintas counter/dashboard/freeze sudah dibuktikan (F-94) dan tidak boleh tersentuh. Beban adalah perkiraan perencanaan; itu yang disebar. Prompt melarang keras mencampur keduanya. Kalau sampai tercampur, angka "jam kerja" seseorang jadi bergantung pada tenggat — itu rusak.

**Konsekuensi yang Boss akan lihat di dashboard:** angka beban akan turun untuk task besar (tersebar) dan IDLE_PLAN jadi lebih jujur — orang dengan satu task besar tenggat minggu depan tidak lagi tampak "overload hari ini". Overload yang tersisa jadi bermakna: benar-benar kerja hari ini melebihi kapasitas, bukan artefak task gemuk. Tooltip diperbarui supaya tim paham kenapa angkanya begitu.

---

**Setelah tugas ini + manual pass F-97, v1.0 TUNTAS.** Dua hal tersisa untuk menutup:
1. **F-59 (tugas ini)** — sebar beban, dieksekusi Claude Code
2. **F-97 (Boss klik manual)** — 10 item, ±15 menit, checklist sudah siap & terverifikasi terhadap kode

Begitu keduanya beres, Jarvis update registry ke 🟢 dan v1.0 resmi tuntas. Jalur berikutnya (L13/deploy atau v1.5 scoring) menunggu arahan Boss — tidak didorong.
