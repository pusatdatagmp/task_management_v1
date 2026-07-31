# PROMPT v1.2 HARI-3 — FIX F-129/F-130 + DASHBOARD COMMAND-CENTER (BACKEND)

> Fase 0: bereskan 2 bug pre-existing DULU (Boss putuskan). Lalu backend agregasi
> untuk widget dashboard v1.7. Semua reuse service existing — nol rumus KPI baru (F-109).
> Data lokal. Registry dipegang Jarvis.

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-129 (Role bug), F-130 (flaky), audit H2
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/03-BUSINESS-FLOW.md §5 (dashboard) · docs/04-FINDING-REGISTRY.md
(F-129, F-130, F-128, F-118, F-96, F-52, F-116, F-106, F-109, F-4, F-85, F-121, F-122).
Baca kode: DashboardService, ActivityLogPresenter, WorkSchedule/OrganizationScope,
RolePermissionSeeder, BoardViewTest.

LAPORKAN:
- Rencana fix F-130 (travelTo hari kerja) & F-129 (withoutGlobalScope saat seed per-org)
- Konfirmasi: widget dashboard v1.7 REUSE service existing (F-109) — heatmap pakai
  beban F-118, recent activity pakai ActivityLogPresenter F-106, workload pakai
  DashboardService F-96/F-118. NOL rumus KPI baru
- Konfirmasi: dashboard 3-angka lama (F-52) DIPERTAHANKAN, command-center MENAMBAH (F-121)
- Checklist Fase 0-B
BERHENTI. Tunggu Boss "LANJUT".

## FASE 0 — BERESKAN 2 BUG DULU (Boss putuskan: sebelum dashboard)

0A. F-130 (flaky BoardViewTest): test "live counter identik" pakai now() tanpa travelTo.
    Fix: travelTo() ke HARI KERJA tetap (mis. Rabu jam kerja) supaya deterministik
    tiap hari. Verifikasi: jalankan test — lulus di hari apa pun (termasuk akhir pekan).
    Ini menutup pola F-73 (hijau bersyarat-hari).

0B. F-129 (Role × OrganizationScope): RolePermissionSeeder::seedSystemRolesForOrganization()
    pakai Role::firstOrCreate() yang kena OrganizationScope → tak match org target saat
    actingAs org lain → INSERT ganda → crash.
    Fix KODE-APP: saat seed role UNTUK org tertentu, lepas scope pada query cari/buat
    (Role::withoutGlobalScope(OrganizationScope::class)->firstOrCreate([...,'organization_id'=>$orgId]))
    ATAU pola setara yang JELAS men-target org yang benar. Jangan cuma di test.
    Test regresi: seed role utk org B sambil actingAs user org A → TIDAK crash, role
    org B tercipta benar, org A tak tercampur (F-5/F-15 isolasi tetap utuh).
    🔴 HANYA perbaiki jalur ini. JANGAN ubah OrganizationScope global (dipakai luas).

0C. Setelah Fase 0: jalankan SELURUH test → semua lulus, deterministik. Lapor.
    (Jarvis akan tutup F-129/F-130 di registry setelah lihat bukti.)

## FASE A — DASHBOARD COMMAND-CENTER (BACKEND / JSON)

🔴 PRINSIP (F-109/F-121): ini AGREGASI TAMPILAN. Reuse service existing. NOL rumus KPI
baru. Dashboard 3-angka lama (F-52) TETAP — ini MENAMBAH widget, bukan mengganti.
🔴 F-4: nol rupiah/skor-kinerja/reward. (Catatan: "prio_score" di bawah = BOBOT URUTAN
prioritas Eisenhower, BUKAN skor kinerja. Ini untuk mengurutkan daftar, bukan menilai orang.)

A1. Endpoint agregasi (gated dashboard.view, seperti v0.8). Kembalikan JSON untuk widget:

A2. Distribusi prioritas (donut): hitung task per priority_quadrant (F-122). p1-p4 + null.
    Reuse kolom quadrant, bukan enum lama.

A3. Progress ring: % task selesai vs total (per project atau global — ikuti mockup).
    Reuse flag is_completed/status done (F-44).

A4. Kategori breakdown: kelompokkan sesuai definisi mockup (per tipe/project). Agregasi baca.

A5. 🔴 MASTER CALENDAR HEATMAP: beban harian per tanggal. WAJIB pakai beban F-118
    (sebar ke hari kerja) — BUKAN hitung ulang. Klasifikasi warna pakai threshold F-128:
    Aman <210m · Tengah 210-419m · Overload >=420m (per user aktif; agregat = ×N user).
    Ini titik paling rawan duplikasi rumus — REUSE workloadSpread F-118, jangan tulis lagi.

A6. Recent activity feed: N event terbaru dari activity_logs. 🔴 REUSE ActivityLogPresenter
    (F-106) untuk label manusiawi — JANGAN tulis pelabelan baru (satu sumber label).

A7. Top-10 tasks: urutkan by prio_score (bobot quadrant p1=4..p4=1, F-122) lalu tenggat.
    prio_score = BOBOT URUTAN, bukan skor kinerja (F-4 aman). Sertakan filter sesuai mockup.

A8. Workload top-5: reuse DashboardService beban (F-96 dibagi assignee, F-118 sebar).
    JANGAN hitung beban dengan cara baru.

A9. 🔴 N+1 (F-85): semua agregasi query konstan. Buktikan angka (bukan klaim).
    Heatmap & workload jangan loop-query per hari/user.

## FASE B — TEST (MySQL, F-83)

B1. Fase 0: test F-130 deterministik (lulus akhir pekan), test F-129 regresi (no crash).
B2. Agregasi: donut/progress/heatmap/recent/top10/workload → angka benar dari data seed.
B3. 🔴 Heatmap = beban F-118: buktikan angka heatmap IDENTIK dengan workloadSpread
    (satu sumber, F-109). Task 40j/5hari → tiap hari kerja masuk kelas yang benar (F-128).
B4. 🔴 N+1 konstan, dibuktikan angka (F-85).
B5. 🔴 F-4: nol field rupiah/skor-kinerja di output. prio_score jelas = urutan, bukan nilai.
B6. Dashboard 3-angka lama (F-52) tetap lulus test lama (F-121 — tak diregres).
B7. SEMUA test lama lulus (regresi nol).

## DILARANG KERAS
JANGAN ubah OrganizationScope global (0B — hanya jalur seeder)
JANGAN hitung ulang beban/realisasi di dashboard (F-109 — reuse F-118/F-96)
JANGAN tulis pelabelan event baru (F-106 — reuse ActivityLogPresenter)
JANGAN ganti/hapus dashboard 3-angka lama (F-52/F-121 — MENAMBAH)
JANGAN keluarkan rupiah/skor-kinerja (F-4); prio_score = urutan saja
JANGAN bangun frontend dashboard (H4) — hari ini BACKEND/JSON
JANGAN bangun gate checklist/meeting (H5/H6)
JANGAN edit docs/ (registry Jarvis) · JANGAN deploy/L13 · JANGAN dependency

## DEFINITION OF DONE
🔴 F-83 MySQL.
[ ] F-130 fixed: BoardViewTest deterministik (lulus akhir pekan) — travelTo
[ ] F-129 fixed di KODE-APP: seed role lintas-org tak crash, isolasi F-5 utuh, scope global tak disentuh
[ ] SELURUH test lama lulus setelah Fase 0 (regresi nol)
[ ] agregasi donut/progress/kategori/heatmap/recent/top10/workload → JSON benar
[ ] heatmap = beban F-118 (angka identik dibuktikan), threshold F-128
[ ] recent activity reuse ActivityLogPresenter (F-106)
[ ] workload reuse DashboardService (F-96/F-118)
[ ] N+1 konstan dibuktikan (F-85)
[ ] F-4: nol rupiah/skor-kinerja; prio_score=urutan
[ ] dashboard 3-angka lama utuh (F-52/F-121)
[ ] npx tsc 0, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / FASE 0 (F-129+F-130 status) / BUKTI HEATMAP=F-118 / DEVIASI (nol->"NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Fase 0 lebih dulu — dan Boss benar menempatkannya di depan.** Membereskan test flaky (F-130) sebelum menambah fitur berarti setiap angka test H3 dan seterusnya benar-benar bisa dipercaya, bukan "hijau kalau dijalankan Senin-Jumat". Dan bug Role (F-129) diperbaiki di kode-app kali ini, bukan cuma ditambal di test — jadi landmine multi-org itu benar-benar dilucuti, bukan disembunyikan dari test.

**Fase A punya satu bahaya yang Jarvis jaga paling ketat: heatmap kalender.** Widget itu menampilkan beban harian dengan warna (aman/tengah/overload). Godaannya besar untuk "hitung saja beban per hari di sini" — dan itu akan menciptakan **kalkulator beban kedua** yang lambat laun menyimpang dari F-118 yang baru kita bangun. Prompt mewajibkan heatmap membaca `workloadSpread` (F-118) apa adanya, dan test B3 membuktikan angkanya **identik**. Satu sumber beban, ditampilkan dua cara (tabel + heatmap) — bukan dua perhitungan.

**Soal "prio_score" — Jarvis tegaskan supaya tidak melanggar F-4:** angka itu **bobot urutan prioritas** (Eisenhower p1=4..p4=1, untuk mengurutkan daftar top-10), **bukan skor kinerja orang**. F-4 melarang memetakan kinerja ke skor/rupiah; mengurutkan task berdasarkan prioritas bukan itu. Prompt menandai perbedaan ini eksplisit supaya tidak ada yang salah mengira dashboard mulai "menilai orang".

**Dashboard lama tidak hilang.** Tiga angka Aktif/Beban/Backlog (F-52) tetap — command-center v1.7 menambah di sekitarnya (F-121). Ini bukan penggantian; ini pengayaan.

**Peta v1.2:** H1 audit ✓ · H2 migration ✓ · **H3 fix F-129/130 + dashboard backend** -> H4 dashboard frontend -> H5 checklist+Eisenhower (konfirmasi makna "wajib" dulu!) -> H6 meeting -> H7 timer+kanban -> H8 buffer+regresi.

**Pengingat: makna "checklist wajib" (F-127) masih terbuka — konfirmasi sebelum H5.**
