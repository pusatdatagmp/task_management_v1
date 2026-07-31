# 01 — PRD (REVISI 2)

> **Proyek:** Task Management Internal → Performance Management → Freelance Marketplace
> **Owner:** GusFAHMY ("Boss") · **Revisi:** 2026-07-15
> **Stack:** Laravel 12 + Inertia v2 + React 19 + shadcn/ui + MySQL 8

---

## 1. KENAPA PROYEK INI ADA

**Ini BUKAN kloning ClickUp.** Yang dibangun Boss adalah **performance management system** yang kebetulan punya task manager di dalamnya.

| # | Alasan | Bobot |
|---|--------|-------|
| 1 | **Budget waktu + KPI + scoring → reward/punishment → gaji.** Tidak ada di produk mana pun | **JANTUNG** |
| 2 | **v3.0 marketplace freelance** — driver Nutrition Hub, komisi per tugas terukur | **UTAMA** |
| 3 | **Biaya** — ClickUp ±Rp350rb/user/bln × 10 user × 100+ bln = **±Rp350jt** | Pendukung |
| 4 | **Branding internal** — tim solid, bangga pada sistem sendiri | Pendukung |

> **F-1:** Alasan #4 hanya tercapai bila aplikasi **cepat dan stabil**. Aplikasi internal yang lambat/buggy menghasilkan **kebalikan** dari kebanggaan.

> **F-55 — KOREKSI PRIORITAS (temuan penting):** Jarvis awalnya menaruh **Kanban** di core dan **time budget** di buangan. **Itu terbalik.** Jantung sistem Boss adalah **budget waktu 8 jam** — dari situ lahir idle detection, estimasi vs realisasi, kapasitas, scoring, lalu gaji. **Board View cuma tiruan ClickUp.** Kalau harus mengorbankan satu, korbankan Board View.

---

## 2. ROADMAP — TERKUNCI

| Versi | Isi | Durasi |
|-------|-----|--------|
| **v0.5** | Foundation · **skema 100% lengkap** · Task CRUD · List View · notifikasi · activity log | **4–5 hari** |
| **v0.8** | Recurring engine · counter & realisasi · dashboard 3 metrik · attachment · extension flow · holidays | **+6–8 hari** |
| **v1.0** | Board View (Kanban) · polish | **+3 hari** |
| **v1.5** | **Formula scoring** — dikalibrasi dari data nyata | setelah ≥1 bln data |
| **v2.0** | Absensi + payroll | **setelah clearance legal** |
| **v3.0** | Freelance marketplace + komisi | — |

**Total ke v1.0: ±3 minggu kerja.**

> **F-56 — KENAPA BUKAN 3 HARI (jujur):** Target 3 hari mati saat 6 fitur kelas berat masuk — terutama **recurring engine** (subsistem sendiri, bukan kolom). Jarvis lebih baik Boss marah sekarang daripada kecewa di minggu ketiga.
> **Yang diselamatkan: skema lengkap sejak Hari-1.** Data KPI mulai terkumpul **hari ke-5**, bukan minggu ke-3. Nol data hilang.

### F-2 — KENAPA SCORING DITUNDA KE v1.5
Boss tidak pernah set threshold indikator tanpa 182 hari data. **Prinsip sama di sini.** Formula scoring sebelum ada data task nyata = **menebak parameter tanpa backtest**. v0.5–v1 mengumpulkan data → v1.5 mengkalibrasi di atasnya.

### F-3 — RISIKO LEGAL (v2.0)
Pemotongan upah di Indonesia diatur ketat. Tidak boleh berdasar skor aplikasi semata — butuh dasar di perjanjian kerja / peraturan perusahaan. **WAJIB clearance HR/legal sebelum v2.0.** Bukan nasihat hukum.

### F-4 — GOODHART'S LAW
Begitu skor menentukan uang, tim berhenti mengoptimalkan **hasil**, mulai mengoptimalkan **skor**. Analog **overfitting**: metrik cantik, edge nol.
**Mitigasi tertanam:** F-53 (flag anomali, bukan penalti otomatis) · F-31 (due_date wajib) · F-29 (member tidak boleh geser deadline) · F-47 (`original_due_date`) · F-45 (transisi berurutan).

---

## 3. KEPUTUSAN ARSITEKTUR

| ID | Keputusan |
|----|-----------|
| **F-5** | Single-tenant, **tenant-aware**. `organization_id` sejak hari pertama. Retrofit v3.0 = bongkar DB total |
| **F-6** | Notifikasi = **database**, bukan Firebase. FCM masuk saat mobile v3.0 |
| **F-7** | Search = **MySQL FULLTEXT**. Bukan Elasticsearch/Algolia |
| **F-8** | Hierarki dipangkas: ClickUp 8 level → **kita 4 level** (`Org → Project → Task → Subtask`) |
| **F-38** | **Counter = calculated, bukan stateful.** Nol scheduler. Timestamp tidak bisa korup |
| **F-39** | **Freeze `actual_minutes` + `rejection_count` saat approve.** Angka penilaian tidak boleh berubah retroaktif |
| **F-40** | `work_schedules` **versioned**. Ubah setting = insert baru |
| **F-69** | **TZ = `Asia/Jakarta` di DB.** Bukan UTC — retrofit deterministik, kompleksitas UTC dibayar tiap hari oleh tim fresh entry |
| **F-55** | Time budget = core. Board View = pelengkap |

---

## 4. KEPUTUSAN BOSS — TERKUNCI

| ID | Pertanyaan | **Keputusan** |
|----|-----------|---------------|
| **F-28** | Siapa reviewer? | **A — Admin/owner project** |
| **F-29** | Member boleh buat task / geser due_date? | **TIDAK. Admin only** |
| **F-30** | Description rich text? | **YA** |
| **F-31** | `due_date` wajib? | **WAJIB** |
| **F-32** | Transisi status? | **BERURUTAN** (maju +1, mundur bebas) → F-45 |
| **F-35** | Notifikasi lengkap? | **WAJIB** — 10 trigger |
| **F-37** | Data KPI? | **6 metrik, semua dibutuhkan** |

> **F-29 punya dampak besar:** member **tidak bisa** membuat task sendiri dan **tidak bisa** menggeser deadline. Ini menutup dua celah Goodhart terbesar sekaligus — orang tidak bisa mengarang task mudah untuk kejar poin, dan tidak bisa menggeser deadline agar selalu on-time.

---

## 5. FITUR — PETA LENGKAP

### 5.1 v0.5 (4–5 hari)

| ID | Fitur | Ket |
|----|-------|-----|
| **V05-1** | **SKEMA DATABASE 100% LENGKAP** | 17 migration. **Item terpenting** |
| V05-2 | Auth + Role (admin/member), tanpa self-signup | |
| V05-3 | Pengaturan jam kerja (`work_schedules` versioned) | F-40 |
| V05-4 | Project CRUD + assign member | Admin |
| V05-5 | Task CRUD — 5 `task_type`, points, estimasi, rich text | Admin only (F-29) |
| V05-6 | Custom Status + 3 flag | F-44 |
| V05-7 | Transisi berurutan + validasi | F-45 |
| V05-8 | List View + filter + sort | |
| V05-9 | Search FULLTEXT | F-7 |
| V05-10 | My Tasks | |
| V05-11 | Notifikasi in-app — 10 trigger | F-35 |
| V05-12 | **Activity Log via Observer** | F-22, F-51 |
| V05-13 | **Time segments tercatat** (belum ada UI counter) | F-41 |

### 5.2 v0.8 (+6–8 hari)

Recurring engine (F-46) · Counter UI + realisasi (F-41) · **Dashboard 3 metrik + idle** (F-52) · Attachment output/evidence (F-49) · Extension workflow (F-50) · Holidays (F-43) · Flag anomali (F-53) · Freeze on approve (F-39)

### 5.3 v1.0 (+3 hari)
Board View (Kanban drag-drop) · polish · error handling

### 5.4 ⛔ TIDAK ADA SAMPAI v1+
❌ Comment & @mention · ❌ Custom Fields · ❌ Tags · ❌ List/Folder layer · ❌ Dependencies · ❌ Gantt/Calendar · ❌ Automation · ❌ Email notif · ❌ Firebase · ❌ Template task manual · ❌ Bulk edit · ❌ Import/export · ❌ Dark mode · ❌ Mobile app · ❌ Guest role · ❌ Reporting lanjutan · ❌ S3 storage

---

## 6. LOGIKA BUDGET WAKTU — INTI SISTEM

**Penjelasan Boss (verbatim, jangan diparafrase):**
1. Budget 8 jam/hari, dikurangi **estimasi** task yang di-set → sisa = idle **planned**
2. Waktu kerja dihitung **saat tugas masuk REVIEW** — realisasi bisa < estimasi
3. Setelah semua di-approve → akumulasi **sisa waktu REAL** berdasarkan realisasi → **ini dasar idle real**

**Terjemahan ke sistem:**

```
KAPASITAS = users.daily_capacity_minutes ?? work_schedules.daily_capacity_minutes  (default 480)

IDLE_PLAN = KAPASITAS - Σ estimated_minutes (task due hari ini + overdue, belum selesai)
IDLE_REAL = KAPASITAS - Σ realisasi (dari task_time_segments, hanya jam dalam jendela kerja)
```

**Selisih `IDLE_PLAN` vs `IDLE_REAL` = sinyal KPI** (estimasi kelebihan / orangnya cepat).

> **F-57 — CAP JENDELA KERJA:** realisasi **hanya** menghitung jam di dalam jendela kerja.
> Contoh: IN_PROGRESS Jum 16:00 → REVIEW Sen 09:00. Jendela Sen–Jum 08–17.
> Jum 1j + Sabtu 0 + Minggu 0 + Sen 1j = **2 jam** (bukan 65 jam).
> **Pause/resume terjadi sendirinya** — bukan dieksekusi scheduler, tapi karena jam di luar jendela tidak dihitung (F-38).

---

## 7. DEFINISI "JADI" — F-10

| Fase | Waktu |
|------|-------|
| v0.5 jalan di **localhost** | 4–5 hari |
| Deploy + seed + fix bug pemakaian nyata | **+3–5 hari** |

**JANGAN samakan keduanya.** Sumber kekecewaan paling umum di proyek software.

---

## 8. PRASYARAT SEBELUM HARI-1
- [ ] PHP 8.2+, Composer, Node 20+, MySQL 8 terpasang & jalan
- [ ] Claude Code login (`unset ANTHROPIC_API_KEY` dulu)
- [ ] `CLAUDE.md` + `docs/` di root proyek
- [ ] Boss standby untuk keputusan (blocker >30 mnt = hari hangus)

---

## 9. RENCANA v0.5

| Hari | Target | Bukti |
|------|--------|-------|
| **1** | Scaffold · **17 migration** · model + relasi + global scope · observer · seeder | `migrate:fresh --seed` sukses |
| **2** | Auth · pengaturan jam kerja · Project CRUD · Status + 3 flag | CRUD project jalan |
| **3** | Task CRUD (5 tipe, points, estimasi, rich text) · validasi transisi F-45 | CRUD task jalan |
| **4** | List View + filter · My Tasks · Search · notifikasi 10 trigger | Semua V05 lolos |
| **5** | Time segments · buffer bug · verifikasi | `TaskTimeSegment::count() > 0` |

> **Buffer Hari-5 sengaja ada.** Estimasi tanpa buffer = estimasi bohong.

---

## 10. NON-FUNCTIONAL
- **Skala:** 10 user, ~5rb task/thn. **Tidak perlu** caching/queue/optimasi
- **Browser:** Chrome/Edge desktop. Mobile → v3.0
- **Bahasa UI:** Bahasa Indonesia · **TZ:** **`Asia/Jakarta` (WIB) di DB dan tampilan** — F-69
- **Uang:** IDR. **Tidak ada perhitungan uang sampai v2.0**

---

## 11. BELUM DIPUTUSKAN

| ID | Item | Kapan |
|----|------|-------|
| **F-11** | Nama produk & branding | sebelum deploy |
| **F-12** | Hosting | sebelum deploy |
| **F-13** | Backup DB | sebelum produksi |
| **F-58** | Formula scoring: bobot points vs on-time vs quality vs rejection | **v1.5 — dari data nyata** |
| **F-59** | Task 40 jam > kapasitas 8 jam/hari — pecah subtask wajib? | sebelum v0.8 |

> **F-59 penting:** task `project` bisa jauh melebihi kapasitas harian. Belum ada aturan apakah wajib dipecah jadi subtask. Berdampak pada rumus BEBAN.
