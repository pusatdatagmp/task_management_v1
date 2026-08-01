# TEMPO — TRACKER PROGRES (per 2026-07-28, upd malam)
### Rujukan harian Boss: sudah sampai mana, kurang berapa.

> % di bawah = estimasi bobot-usaha, bukan angka pasti. Sisa mencakup fase terberat
> (editor tema, model waktu). Setiap fase = 1 sesi Claude Code (diaudit Jarvis).

---

## 📊 RINGKASAN

- **Fondasi task-management (v0.5 → v1.0.1):** ✅ **100% SELESAI** — inti sistem solid, teruji, di-commit.
- **v1.2 "integrasi template v1.7":** 🔨 **~68% selesai** (10 fase; Setelan aktif).
- **Test saat ini:** 351 lulus, nol regresi. **Semua kerja di-commit** (terakhir c972b04).

Bar v1.2: `██████████████░░░░░░` ~68%

---

## ✅ SUDAH SELESAI (v1.2)

| Fase | Isi | Bukti |
|---|---|---|
| H1 | Audit gap mockup v1.7 | ✓ |
| H2 | Migration aditif (quadrant, checklist, meetings, projects.goal) | ✓ |
| H3 | Dashboard backend (donut/heatmap/top-10/workload/recent + 5 kartu) | ✓ 282 test |
| H4 | Dashboard frontend (command-center) | ✓ |
| H5 | Leaderboard Level 1 (management-only, permission) | ✓ 295 test |
| H6 | Eisenhower + Checklist gate | ✓ 318 test |
| DS-1 | Fondasi token TEMPO + sidebar bergrup | ✓ terverifikasi browser |
| DS-4 | Filter per-widget + widget Status Project + fix F-148 | ✓ 330 test, committed |
| Aktivasi | Semua Tugas (List/Kanban+filter quadrant) + Tugas Berulang aktif | ✓ 341 test, 5f245fe |
| DS-2 | Setelan + Branding (logo/nama/kontak/sosmed) — menu Setelan aktif | ✓ 351 test, c972b04 |

---

## 🔨 TERSISA (v1.2) — urut rencana

| # | Fase | Isi | Berat | Merealisasikan |
|---|---|---|---|---|
| 1 | **DS-3** | Editor tema warna + gradasi (tab Tema di Setelan) | **berat** | Setelan lengkap |
| 2 | **H7** | Model waktu Mulai/Hold/Lanjut/Submit | **berat/rawan** | fitur inti |
| 3 | **H8** | Meetings (CRUD + notif + kalender) | sedang | fitur |
| 4 | **H9** | Buffer + regresi + **tutup F-97** | sedang | penutup |
(H7b terserap ✓ · DS-2 branding ✓ · 3 menu disabled semua aktif ✓)

---

## 🔓 3 MENU DISABLED → kapan aktif

1. **Semua Tugas** → ✅ AKTIF (5f245fe)
2. **Tugas Berulang** → ✅ AKTIF (5f245fe)
3. **Setelan** → ✅ AKTIF (c972b04); tab Tema menyusul DS-3

*(SEMUA 3 aktif ✅ — F-147 tutup.)*

---

## 🔴 UTANG TERBUKA (dibawa)

- **F-97** — verifikasi browser manual, kini **17 item** (dashboard, leaderboard, gate, checklist, token, filter, dst). **Belum ditutup** — menunggu Boss jalankan `composer run dev` + klik. Ini gerbang "v1.2 tuntas".
- **F-146** bug laten sidebar.tsx (shadow rail) · **F-150** 3 lint error DS-1 · **F-117/F-119** utang v1.1 · **F-129** pra-multi-org (v3.0).

---

## 🗓️ CARA PAKAI HARIAN

Tiap sesi Claude Code:
1. Boss salin `docs/` terbaru dari Jarvis + **commit**.
2. Paste prompt fase berjalan → tunggu LANGKAH 0 → "LANJUT".
3. Tempel laporan ke Jarvis → Jarvis audit + update registry + centang fase di tracker ini.
4. Tiap ~2 fase: `composer run dev` + verifikasi browser (jangan menumpuk F-97).

**Perkiraan kasar ke "v1.2 tuntas":** ~7 fase tersisa + 1 sesi verifikasi F-97. Kalau 1 fase/sesi, **± 4 sesi lagi** (fase berat seperti DS-3 tema & H7 bisa perlu 1–2 sesi masing-masing).

---

*Tracker ini diperbarui Jarvis tiap fase selesai. Angka test & % mencerminkan yang SUDAH di-commit (F-149) — bukan working tree.*
