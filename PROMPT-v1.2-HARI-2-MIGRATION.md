# PROMPT v1.2 HARI-2 — MIGRATION ADITIF + MODEL (F-122..F-128)

> Backend murni aditif: field & tabel baru. NOL drop kolom, NOL hapus fitur.
> Data lokal. Registry dipegang Jarvis — Claude Code JANGAN edit docs/.

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-124..F-128 (keputusan v1.7), koreksi framing meeting+checklist
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/02-DATA-MODEL.md · docs/04-FINDING-REGISTRY.md
(F-122, F-123, F-124, F-125, F-126, F-127, F-128, F-5, F-121, F-72).

🔴 KOREKSI PENTING dari audit H1 (Jarvis sudah catat di registry — pakai ini):
- Notif meeting = kategori KOLABORASI (F-114 family), BUKAN trigger lifecycle ke-11.
  F-35 "10 lifecycle" TETAP. (Ini relevan H6, bukan H2 — tapi ketahui sekarang)
- Makna "checklist wajib" masih TERBUKA (F-127) — resolusi sebelum H5. H2 cuma bikin
  TABEL-nya (sama untuk kedua tafsir), JANGAN bangun gate/logika wajib hari ini.

LAPORKAN:
- Konfirmasi paham: semua migration hari ini ADITIF (nol drop kolom lama, F-121)
- Daftar migration yang akan dibuat + field-nya
- 🔴 Registry F-124..F-128 sudah dicatat Jarvis — kamu DILARANG edit docs/. Kalau ada
  finding baru muncul saat kerja, LAPOR ke Jarvis, jangan tulis sendiri
BERHENTI. Tunggu Boss "LANJUT".

## KONTEKS

Integrasi mockup v1.7 (F-121 = pengayaan visual, bukan pengganti). Hari ini fondasi
data: field & tabel baru yang dibutuhkan widget v1.7. Semua ADITIF.

## FASE A — MIGRATION ADITIF

A1. tasks.priority_quadrant enum('p1','p2','p3','p4') nullable (F-122/F-126).
    🔴 JANGAN hapus/ubah kolom priority lama (low/normal/high/urgent) — dipertahankan
    (F-121). priority_quadrant kolom TERPISAH. Default: null atau p4 (pilih, jelaskan).

A2. task_checklist_items (F-123/F-127): id, task_id FK, organization_id (F-5),
    text, is_done bool default false, position int, timestamps.
    🔴 HARI INI CUMA TABEL + MODEL. JANGAN bangun gate transisi (itu H5, & maknanya
    masih terbuka F-127).

A3. Checklist config di template (F-127): kolom/tabel child supaya template bisa
    menyimpan daftar checklist yang nanti disalin ke instance. Bikin STRUKTURnya saja;
    logika copy-on-generate = H5. (mis. task_template_checklist_items).

A4. projects: tambah goal (text nullable), due_date (date nullable) (F-125).
    🔴 JANGAN tambah kolom status — status DITURUNKAN dari agregasi task (F-125),
    bukan disimpan. is_archived lama TETAP terpisah.

A5. tasks: tambah due_at (datetime nullable) untuk tenggat berjam (mockup datetime-local).
    🔴 JANGAN hapus/ubah due_date lama kalau ada — aditif. Jelaskan relasi due_date vs
    due_at (mana yang dipakai ke depan, mana legacy) di laporan, jangan putuskan
    sepihak menghapus.

A6. meetings (F-124): id, organization_id (F-5), project_id FK NULL-able, title,
    description nullable, start_at datetime, end_at datetime, created_by FK users, timestamps.
    meeting_user: meeting_id FK, user_id FK, timestamps (pivot peserta).
    🔴 HARI INI CUMA TABEL + MODEL + relasi. CRUD/notif/kalender = H6.

## FASE B — MODEL + VALIDASI DASAR

B1. Model tiap tabel baru: BelongsToOrganization (F-5), SerializesDatesInAppTimezone
    (F-72) untuk yang punya kolom tanggal, relasi Eloquent yang benar.
B2. Task model: relasi checklistItems(). Project: goal/due_date fillable. Meeting:
    relasi participants() + creator() + project().
B3. Validasi dasar di model/cast (enum quadrant, datetime). Belum ada Request/Controller
    baru hari ini kecuali diperlukan untuk migrate jalan.

## FASE C — TEST (MySQL, F-83)

C1. Migration jalan bersih (migrate:fresh + seed existing tetap sukses).
C2. Test model dasar: buat task dgn priority_quadrant, checklist item, meeting +
    peserta, project goal/due_date — tersimpan & relasi terbaca.
C3. 🔴 REGRESI: SEMUA test lama tetap lulus (259+). Migration aditif TIDAK boleh
    memecah apa pun. Ini kunci F-121.
C4. organization_id ter-scope benar di tabel baru (F-5/F-15).

## DILARANG KERAS
JANGAN drop/ubah kolom lama (priority enum, due_date) — ADITIF (F-121)
JANGAN tambah projects.status (derived, F-125)
JANGAN bangun gate checklist / logika wajib (H5, F-127 terbuka)
JANGAN bangun CRUD/notif meeting (H6)
JANGAN bangun copy-on-generate checklist (H5)
JANGAN hapus/regres fitur/halaman yang sudah ada (F-121)
JANGAN edit docs/ — registry dipegang Jarvis (lapor finding baru, jangan tulis)
JANGAN deploy/L13 · JANGAN dependency tanpa approval

## STANDAR KOMENTAR
CLAUDE.md §3. Header klasifikasi tiap file baru. Sebut F-N (F-122/123/124/125) di
komentar migration menjelaskan kenapa aditif.

## DEFINITION OF DONE
🔴 F-83 test MySQL.
[ ] migration aditif: priority_quadrant, checklist items (+template child), projects.goal/due_date, tasks.due_at, meetings+meeting_user
[ ] NOL kolom lama di-drop/diubah (grep buktikan priority enum & due_date utuh)
[ ] NOL projects.status column (derived)
[ ] model + relasi + BelongsToOrganization + SerializesDatesInAppTimezone
[ ] migrate:fresh+seed sukses, 259+ test lama LULUS (regresi nol, F-121)
[ ] org scope benar tabel baru
[ ] npx tsc 0, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / RELASI due_date vs due_at (penjelasan) / DEVIASI (nol->"NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Hari ini fondasi data — dan aturannya satu kata: aditif.** Setiap kolom dan tabel baru ditambahkan; nol yang lama disentuh. Ini penerjemahan langsung prinsip Boss "ADD, DON'T DELETE" ke level database. Test regresi (Fase C3) adalah penjaminnya: 259+ test lama harus tetap hijau setelah migration — kalau ada satu yang pecah, berarti migration menyentuh sesuatu yang seharusnya utuh.

**Tiga hal sengaja DITAHAN ke hari lain**, meski tabelnya dibuat hari ini:
- **Gate checklist** (H5) — karena maknanya masih terbuka (F-127). Hari ini cuma bikin tabel; tabelnya sama entah gate-only atau wajib-item. Jadi kita tidak terkunci ke tafsir yang salah.
- **CRUD + notif meeting** (H6) — hari ini cuma struktur data.
- **Copy checklist saat recurring generate** (H5) — logika, bukan skema.

Menahan logika sampai skema stabil mencegah menulis dua kali.

**Satu titik Jarvis minta Claude Code lapor, bukan putuskan (A5):** relasi `due_date` lama vs `due_at` baru (berjam). Menghapus `due_date` lama akan melanggar aditif dan bisa memecah kode yang memakainya. Claude Code harus menjelaskan mana yang jadi utama dan mana legacy — bukan diam-diam menghapus.

**Dua framing yang Jarvis kunci hari ini (relevan nanti, tapi dicatat sekarang):**
- **Meeting-invite = notifikasi kolaborasi** (seperti mention F-114), bukan "trigger lifecycle ke-11". Angka 10 milik Boss tetap utuh.
- **"Checklist wajib" = Jarvis baca gate-di-semua-jalur** (task tanpa item tetap lolos, seperti mockup). **Konfirmasi sebelum H5** kalau Boss maksudnya setiap task wajib punya item — itu perubahan besar.

**Peta v1.2:** H1 audit ✓ -> **H2 migration aditif** -> H3 dashboard backend -> H4 dashboard frontend -> H5 checklist+Eisenhower -> H6 meeting -> H7 timer+kanban -> H8 buffer+regresi.
