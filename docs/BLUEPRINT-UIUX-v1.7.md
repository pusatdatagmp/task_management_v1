# BLUEPRINT UI/UX — TEMPO "Master Workspace" v1.7
### Sumber Kebenaran Pengembangan · dikunci bersama Boss · 2026-07-25

> **Cara pakai dokumen ini.** Ini gambaran OUTPUT AKHIR yang Boss harapkan — fungsi,
> alur, dan aturan tiap bagian. Claude Code memakai ini untuk menyusun langkah agar
> linear dengan tujuan Boss. Ini **melengkapi**, bukan menggantikan, FINDING REGISTRY
> (04-FINDING-REGISTRY.md) yang tetap otoritatif untuk aturan teknis. Kalau blueprint
> dan registry tampak bertentangan → LAPOR ke Jarvis, jangan tebak.
>
> **Prinsip induk (F-121): mockup HTML = REFERENSI VISUAL & FUNGSI, bukan pengganti
> harfiah.** Fitur yang sudah dibangun (activity log, komentar, RBAC, jam kerja, libur,
> template, perpanjangan) DIPERTAHANKAN. Yang di-stub di HTML tetap dibangun penuh.

---

## 1. FILOSOFI YANG TIDAK BOLEH DILANGGAR

Sistem ini mengukur **item pekerjaan** dan **waktu pengerjaan** secara jujur, supaya
kelak bisa jadi dasar penilaian & payroll yang adil. Kejujuran angka > fitur cantik.

| Prinsip | Finding | Aturan mati |
|---|---|---|
| Realisasi = DIHITUNG dari segmen waktu, bukan input manual | F-38, F-41, F-132 | Tak ada angka waktu yang bisa diketik langsung |
| Sekali disetujui, angka BEKU selamanya | F-39, F-107 | Realisasi/rating/lampiran task selesai tak bisa diubah siapa pun |
| Beban disebar ke hari kerja (bukan menumpuk di tenggat) | F-118 | Satu sumber beban `workloadSpread`, dipakai semua widget |
| Beban (rencana) ≠ Realisasi (aktual) — jangan dicampur | F-94, F-131 | Heatmap hari-lewat NETRAL, bukan realisasi |
| Nol duplikasi kalkulator | F-72, F-76, F-109 | Widget baru REUSE service lama, tak menghitung ulang |
| Poin→skor HANYA di leaderboard management-only, provisional | F-4, F-134, F-2 | Member tak lihat skor; kalibrasi nyata di v1.5 |
| Aditif — tambah, jangan hapus | F-121 | Nol fitur/kolom lama dihapus |

---

## 2. PERAN & AKSES

- **Member** — mengerjakan tugasnya. Lihat: Tugas Saya, Proyek Saya, Perpanjangan Saya.
  Tak lihat dashboard tim, leaderboard, log global, atau data user lain.
- **Admin** — kelola tugas/proyek/user/jadwal, review & setujui, lihat dashboard.
- **Akses Leaderboard & analisa penuh** BUKAN tier hardcoded. Ia = **permission
  `leaderboard.view`** (baru, ditambah ke katalog) yang diberikan ke user/peran terpilih
  lewat **UI RBAC yang SUDAH ADA** (F-135). Bisa admin, atau peran baru yang Boss buat.
  "Top-management" hanyalah sebutan untuk siapa pun yang dapat permission ini.

> **RBAC (Pengguna & Peran) yang sudah dibangun Claude Code SUDAH BENAR — JANGAN diubah
> (F-135, F-121).** Form "Role Baru" di HTML v1.7 kurang lengkap → ABAIKAN; yang di dev
> Claude Code lebih lengkap dan itu rujukannya.
>
> Login role-picker di HTML = **DEMO SAJA**. Produksi pakai auth asli (F-91): admin
> meng-onboard user, tak ada registrasi mandiri. JANGAN bangun role-picker sebagai auth.

---

## 3. JANTUNG SISTEM — MODEL WAKTU KERJA (F-132)

Tombol di sisi **user**, di halaman detail tugas (pojok kanan bawah). Ini menggantikan
"Start/Stop Timer" di HTML (itu salah desain). Realisasi = penjumlahan segmen, DIHITUNG.

**State machine tombol:**

| Kondisi | Tombol tampil | Klik → efek |
|---|---|---|
| status `todo` | **Mulai** | timestamp OPEN (segmen 1 mulai) · status → `dikerjakan` · tombol **Submit** muncul · tombol berubah jadi **Hold** |
| sedang `dikerjakan`, segmen terbuka | **Hold** + Submit | timestamp CLOSE (segmen jeda) · tombol berubah jadi **Lanjut** |
| `dikerjakan`, dijeda | **Lanjut** + Submit | timestamp OPEN (segmen baru) · tombol berubah jadi **Hold** |
| kapan pun saat `dikerjakan` | **Submit** | **CEK GATE F-127 dulu:** bila ada item checklist belum dicentang → **Submit GAGAL** (toast, tetap `dikerjakan`). Bila semua tercentang / checklist kosong → timestamp CLOSE segmen berjalan · **JUMLAHKAN semua segmen = realisasi** · status → `review` |

**Aturan:**
- Realisasi = Σ durasi semua segmen aktif (F-38). Tak pernah diketik manual.
- Jeda (Hold) tidak dihitung sebagai waktu kerja — hanya segmen OPEN→CLOSE yang dijumlah.
- Segmen dibuka atas nama **assignee-pekerja** (F-112), bukan pelaku lain.
- Business-hours cap tetap berlaku (F-57): waktu di luar jam kerja/hari libur tak dihitung.
- Di **Board/Kanban**: menyeret kartu ke kolom `dikerjakan` = setara **Mulai** (buka segmen);
  ke `review` = setara **Submit**. Hold/Lanjut hanya via tombol detail (tak ada di board).
  Semua lewat service yang sama (F-111), drag hanya UI.

---

## 4. SIKLUS STATUS TUGAS

| Status | Kapan | Siapa memicu |
|---|---|---|
| **todo** | admin membuat & assign tugas → muncul di sisi user | admin |
| **dikerjakan** | user klik **Mulai** (timestamp pertama terbuka) | user (assignee) |
| **review** | user klik **Submit** — LOLOS gate checklist F-127 dulu (kalau ada item kosong, Submit gagal), lalu timestamp ditutup & realisasi dijumlah | user (assignee) |
| **selesai** | admin/super-admin review hasil & klik **Setujui** | admin |

- Transisi berurutan (F-45): maju +1, mundur bebas. Di board, kolom lompat-maju di-disable saat drag (F-110).
- Saat **Setujui**: realisasi & rating **BEKU** (F-39), lampiran **terkunci** (F-107),
  task **diturunkan dari daftar aktif user** tapi **tetap muncul di sisi admin** (F-133).
- Tolak (admin) → kembali ke `dikerjakan`, hitung revisi/ditolak (metrik, bukan hukuman — F-62).

---

## 5. PRIORITAS EISENHOWER (F-122, F-126)

Field `priority_quadrant` (baru, di samping enum lama yang disembunyikan):

| Kode | Label | Bobot (prio_score) | Warna |
|---|---|---|---|
| p1 | #1 Penting – Mendesak | 4 | merah |
| p2 | #2 Penting – Tdk Mendesak | 3 | amber |
| p3 | #3 Tdk Penting – Mendesak | 2 | biru |
| p4 | #4 Tdk Penting – Tdk Mendesak | 1 | abu |

`prio_score` = bobot untuk **mengurutkan** daftar (top-10 dashboard). Ini **urutan prioritas,
BUKAN skor kinerja** (F-4 aman).

---

## 6. CHECKLIST + GATE REVIEW (F-123, F-127)

- Checklist = daftar item ringan dalam tugas (beda dari subtask F-20).
- **Gate (F-127, RESOLVED = gate-only):** transisi ke `review` DITOLAK bila ada item
  checklist yang belum dicentang. **Checklist kosong → LOLOS** (tak setiap task wajib punya item).
- **Ditegakkan tepat di aksi Submit (F-132)** dan di semua jalur lain (drag ke review),
  **server-side** — F-111. Submit yang gagal gate TIDAK menutup segmen & TIDAK mengubah status.
- Template berulang menyalin item checklist ke tiap instance (F-46 + generator).

---

## 7. VIEW DEMI VIEW (fungsi → cara kerja)

### 7.1 DASHBOARD "Command Center" (admin) — F-52/F-118/F-128/F-106/F-121
**Fungsi:** tinjauan operasional & beban tim. **MENAMBAH** di sekitar 3-angka lama
(Aktif/Beban/Backlog F-52 tetap ada).
**Widget:**
- **5 kartu ringkas**: Beban Kerja Harian (X/Y jam), To Do, In Progress, Review, Overdue. Klik → filter tugas.
- **Donut Prioritas**: jumlah task per quadrant (F-122).
- **Distribusi Progress**: bar % TODO/PROGRESS/REVIEW/SELESAI.
- **Kategori Tugas**: breakdown per tipe/proyek.
- **Master Calendar Heatmap** (F-131): grid BULAN, navigasi prev/next. Warna beban harian
  (Aman <210m / Tengah 210–419m / Overload ≥420m per user — F-128). **Sumber = `workloadSpread`
  F-118** (angka WAJIB identik, bukan hitung ulang). **Hari lewat = NETRAL, bukan realisasi.**
- **Top-10 Tasks**: urut `prio_score` lalu tenggat, dengan filter.
- **Workload Top-5**: reuse DashboardService (F-96 dibagi assignee, F-118 disebar).
- **Recent Activity**: N event terbaru, label manusiawi via ActivityLogPresenter (F-106).
**Aturan:** nol rupiah/skor-kinerja (F-4). Read-only agregasi. N+1 konstan (F-85).

### 7.2 LEADERBOARD (top-management only) — F-134/F-4/F-2/F-62
**Fungsi:** analisa manajemen atas produktivitas tim. **BUKAN untuk member** (mereka tak
melihatnya → tak bisa menggame → data tetap jujur).
**Cara kerja (Level 1, data dummy):**
- **Point** = Σ `pts` task **disetujui** dalam periode. Ranking by Point.
- Kolom konteks (tampilan, TIDAK dibaur ke Point): **Rating** (rata-rata quality),
  **Revisi**, **Ditolak**, **On-time%** (selesai ≤ tenggat asli F-47). Menghormati F-62.
- Filter periode (hari/minggu/bulan/custom) + sorotan Top-3 / Bottom-3.
- **Top-3** (🥇🥈🥉) & **Bottom-3** (untuk manajemen mengidentifikasi siapa perlu dibantu —
  bukan papan malu member).
**Guardrail:** ini exception SADAR terhadap F-4. Skor **provisional** sampai v1.5 dikalibrasi
data nyata + review manusia (F-2). Level 2 (komposit berbobot pts×rating×ontime) = evolusi v1.5.
**Akses = permission `leaderboard.view`** (BARU, ditambah ke katalog permission — INSERT baris,
F-90/F-135), diberikan lewat **UI RBAC yang SUDAH ADA** (jangan bikin mekanisme akses baru).
Default: tidak ada yang punya kecuali diberikan Boss. Member biasa tak pernah melihat leaderboard.

### 7.3 TUGAS (Semua Tugas / Tugas Saya) — F-109/F-110/F-111
**Fungsi:** kelola & lihat tugas. **Toggle List ⇄ Kanban.**
- **List**: tabel dengan filter (tanggal, user, prioritas, tipe, status) + sort. Server-side, URL.
- **Kanban**: kolom = status (F-44), kartu = tugas, drag-drop (F-110 kolom tak-sah disable saat seret;
  F-111 drop lewat service; F-33 optimistic + revert). Reuse Board v1.0.
- **Detail tugas**: deskripsi, penerima, estimasi/realisasi/poin, checklist (gate F-127),
  lampiran (F-104/105/107), komentar+mention (F-113/114/115), tombol Mulai/Hold/Lanjut/Submit (F-132),
  approve/reject (admin), riwayat per-task (F-95).
- Member hanya seret/kerjakan task yang di-assign ke dia (F-95).

### 7.4 PROYEK — F-125/F-133
**Fungsi:** wadah tugas. Field: nama, deskripsi, **goal**, **due_date**, anggota, `is_archived`.
- **status** (todo/aktif/selesai) **DITURUNKAN** dari agregasi task (F-125), bukan disimpan/di-set manual di sisi user.
- `is_archived` tetap aksi manual admin, terpisah dari status.
- Admin/top-management lihat seluruh task proyek termasuk selesai (F-133).

### 7.5 TUGAS BERULANG (template) — F-46/F-100/F-101/F-102/F-104
Harian/mingguan/bulanan. Aturan tanggal: daily skip di libur; weekly/monthly geser hari kerja;
clamp tanggal 31; no-backfill; idempotency. Template menyalin checklist ke instance (F-123).

### 7.6 PERPANJANGAN DEADLINE — F-47/F-50/F-62/F-108
Ajukan (assignee) + evidence opsional + reason wajib. Approve (admin) simpan `original_due_date`
(F-47), tenggat ≥ saat ini (F-108). Jumlah = metrik, bukan hukuman (F-62).

### 7.7 PENGGUNA & PERAN (RBAC) — F-88/F-90/F-91/F-135 — **SUDAH BENAR, JANGAN DIUBAH**
🔴 **Yang sudah dibangun Claude Code adalah rujukan & SUDAH BENAR** (F-135, F-121):
- **User list**: Nama · Email · Role · Tipe (Internal) · Status (Aktif/Nonaktif) · Aksi (Edit / Nonaktifkan). Tombol "Kelola Role" + "User Baru".
- **Role list**: Nama · Tipe (Sistem) · Default · Jumlah user · Aksi (Edit / Hapus — peran Sistem tak bisa dihapus).
- **Form Role Baru**: Nama role + checkbox permission per-modul (ACTIVITY, DASHBOARD, PROJECT, STATUS, TASK, USER, WORKSCHEDULE).

Peran dinamis, permission data-driven (F-90). Admin onboard user (tak ada self-signup, F-91).
**Form "Role Baru" di HTML v1.7 KURANG lengkap → ABAIKAN.** Yang perlu DITAMBAH ke katalog: permission
baru **`leaderboard.view`** (untuk F-134) — otomatis muncul di form yang ada. Peran baru = INSERT baris, nol deploy.

### 7.8 JAM KERJA & HARI LIBUR — F-40/F-43/F-66/F-69
Jam kerja berversi (perubahan = versi baru, tak menimpa). Hari libur = 0 menit, memengaruhi
recurring & business-hours.

### 7.9 LOG ACTIVITY — F-51/F-106/F-116/F-95
Global (gated `activity.view`, top-management) + timeline per-task (membership F-95). Read-only,
label manusiawi (F-106). Komentar TIDAK masuk sini (tabel terpisah F-113).

### 7.10 MEETINGS — F-124
Admin buat rapat, undang peserta, proyek opsional, start/end. Notif "diundang meeting" = kategori
**KOLABORASI** (keluarga F-114), BUKAN trigger lifecycle ke-11 (F-35 tetap 10). Pembuat tak dapat
notif sendiri (F-36). Tampil di kalender.

---

## 8. YANG DIPERTAHANKAN (F-121) — JANGAN DIHAPUS/REGRES

Meski tak semua menonjol di HTML v1.7: **UI RBAC/Peran (SUDAH BENAR — F-135), Activity Log UI,
Komentar+Mention, Jam Kerja, Hari Libur, Tugas Berulang, Perpanjangan (detail), Board drag-drop,
attachment, notifikasi 10-trigger.** Semua sudah teruji (~270 test) — pertahankan utuh, perkaya
tampilannya sesuai v1.7.

**Permission BARU yang ditambahkan (bukan mengganti):** `leaderboard.view` (F-134) — masuk katalog
RBAC yang ada, muncul otomatis di form Role.

---

## 9. DEMO-ONLY — JANGAN DIBANGUN HARFIAH
- Login role-picker → pakai auth asli (F-91).
- Data acak `Math.random()` / auto-gen 100 task → hanya untuk demo visual; produksi pakai data nyata/seed terkendali.
- Nilai leaderboard random di HTML → ganti dengan Level 1 (Σ pts disetujui).
- "Start/Stop Timer" → ganti Mulai/Hold/Lanjut/Submit (F-132).

---

## 10. GUARDRAIL DATA-INTEGRITY (ringkas untuk tiap sesi)
1. Realisasi hanya dari segmen (F-38/F-132) — tak ada input manual.
2. Beku saat approve (F-39/F-107) — tak bisa diubah.
3. Satu sumber beban `workloadSpread` (F-118) — heatmap/workload/kartu reuse, tak hitung ulang.
4. Beban ≠ realisasi (F-94/F-131) — jangan dicampur di visual mana pun.
5. Leaderboard management-only (F-134) — member tak lihat, tak bisa game.
6. Reuse service, jangan duplikasi kalkulator (F-72/F-76/F-109).

---

## 11. STATUS & UTANG TERBUKA (per 2026-07-25)
- **F-97** (v1.0): 10 layar belum diverifikasi mata manusia — menunggu klik manual Boss.
- **F-117** (v1.1): label event `deleted` belum sebut nama objek.
- **F-119** (v1.1): `DashboardService::aktif()` dead code.
- **v1.2 berjalan**: H1 audit ✓ · H2 migration ✓ · H3 Fase 0 (F-129/130 fixed) ✓ · H3 Fase A (dashboard backend) menunggu jalan · lalu checklist/Eisenhower, meeting, timer(F-132), kanban, leaderboard, buffer.

---
*Blueprint ini dikunci bersama Boss. Perubahan arah = keputusan Boss, dicatat sebagai finding baru oleh Jarvis. Claude Code tidak mengedit dokumen ini maupun registry.*

---

## §12. LAPISAN VISUAL & DESIGN SYSTEM (koreksi celah — F-140..F-144)

> Blueprint awal menangkap fungsi/data tapi under-specify tampilan. Bagian ini menutup itu.
> Dikerjakan sebagai FONDASI sebelum lanjut fitur task-mgmt (F-144).

### 12.1 Design Token (default TEMPO, overridable — F-144/F-143)
Palet "cockpit": **sidebar navy gelap + workspace terang + aksen amber**. Token via CSS variable
supaya editor tema (DS-3) bisa override per-org:
- ink #0f1523 / ink2 #161d30 (sidebar) · paper #f5f6f9 / card #ffffff (workspace terang)
- amber #e0a012 (aksi/nilai) · emerald #0ea371 (tepat waktu/aman) · rose #e8385a (telat/overload)
- blue #2f6bde · violet #6d4bd8 · slate #64748b · teks tx/tx2/tx3
Komponen bersama (card, badge, button, stat, counter) memakai token — bukan warna hardcode.

### 12.2 Struktur Sidebar (bergrup, role-appropriate, gated — F-144)
Header: **logo + nama** (dinamis dari Branding DS-2). Grup:
- **RINGKASAN**: Dashboard · Leaderboard *(gated `leaderboard.view` — F-134, hanya muncul bila punya)*
- **KERJA**: Proyek · Semua Tugas · Tugas Berulang · Perpanjangan
- **ORGANISASI**: Pengguna & Peran · Jam Kerja · Hari Libur · Log Activity · **Setelan** *(branding+tema)*
- Member: **KERJA SAYA** (Tugas Saya, Proyek Saya, Perpanjangan Saya) saja.
Item digating permission (F-95) — yang tak berhak tak melihat menu.

### 12.3 Fitur Custom BRANDING (Settings submenu — F-142)
Org-scoped (F-5). Input: **upload logo** (file, pola F-104/105), **nama perusahaan, alamat,
wa.me, sosmed (Facebook/Instagram/LinkedIn)**. Ganti "Laravel Starter Kit". Tampil di sidebar/shell
(+ footer/kontak bila relevan). Permission-gated (mis. `settings.manage`).

### 12.4 Fitur Custom TEMA (Settings submenu — F-143)
Editor override token warna komponen + **GRADASI**, per-org, via CSS variable. WAJIB fondasi
token (§12.1) dulu. Live preview kalau memungkinkan. Reset ke default TEMPO tersedia.

### 12.5 Tambahan widget Dashboard yang TERLEWAT (koreksi §7.1)
- **Widget "Status Project"**: tabel top-5 proyek — kolom Task/Todo/Progress/Selesai/Overdue/
  Deadline, sortable, status DITURUNKAN (F-125). "Show More" → halaman proyek.
- **Filter per-widget**: tiap widget (donut/progress/kategori/kalender/workload/recent/top-10)
  punya selector periode (Bulan/Minggu/Hari/custom) + user/tim (All User). Plus tombol global
  **Last Week / Last Month / Pilih Tanggal**. (Backend agregasi sudah ada — tambah parameter filter.)

### 12.6 🔴 PROTEKSI — beda-dari-mockup yang JUSTRU BENAR (F-141) — JANGAN "diperbaiki"
- **Heatmap netral/kosong** di hari tanpa task & hari lewat = BENAR (F-131 + data nyata). Mockup all-warna = data acak palsu.
- **Donut "Belum ditandai"** = BENAR (quadrant NULL, belum ada klasifikasi). Pie warna mockup = palsu.
- **Leaderboard tak muncul utk admin** = BENAR (F-134, belum di-assign leaderboard.view). Untuk lihat: assign permission lewat Pengguna & Peran.
Membuat ketiganya "seperti mockup" = MELANGGAR finding. Saat pass visual, sentuh STYLING, bukan mengganti data/logika ini.

### 12.7 Urutan design (F-144)
DS-1 token+sidebar+shell → DS-2 branding → DS-3 tema+gradasi → DS-4 fidelity dashboard
(Status Project + filter per-widget). Lalu resume H7 (timer) dst dalam gaya baru.
