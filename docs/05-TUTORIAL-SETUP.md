# 05 — TUTORIAL SETUP & RUNNING (WINDOWS)

> **Untuk Boss.** Ditulis dengan asumsi **nol pengalaman terminal**.
> **Ikuti berurutan. Jangan lompat.** Setiap langkah punya cara verifikasi — kalau verifikasi gagal, **BERHENTI**, jangan lanjut.
> **Total waktu: 1–2 jam** (mayoritas menunggu download).

---

## §0. YANG PERLU BOSS TAHU DULU

**Keputusan terkunci:** Laravel **12** + PHP **8.3** + MySQL 8 + Windows.

> 🔴 **F-65 — JEBAKAN PALING BERBAHAYA DI TUTORIAL INI**
> Perintah `laravel new` yang ada di semua tutorial internet **sekarang meng-install Laravel 13**, bukan 12.
> **JANGAN PERNAH pakai `laravel new` di proyek ini.** Kita pakai `composer create-project laravel/laravel:^12.0`.
> Kalau salah, Boss dapat Laravel 13 tanpa sadar dan tidak cocok dengan spec kita.

**Istilah yang akan muncul:**

| Istilah | Artinya |
|---------|---------|
| **Terminal / CMD** | Jendela hitam tempat mengetik perintah |
| **Composer** | Pemasang paket PHP |
| **npm** | Pemasang paket JavaScript |
| **Migration** | Skrip pembuat tabel database |
| **Seeder** | Pengisi data contoh |

---

## §1. INSTALL LARAGON (satu paket: PHP + MySQL + Composer + Node)

Laragon memasang semua kebutuhan sekaligus. **Jangan install PHP/MySQL terpisah** — nanti bentrok.

**1.1** Buka https://laragon.org/download/ → unduh **Laragon Full**

**1.2** Jalankan installer → **Next → Next → Install → Finish**
- Lokasi install: biarkan default `C:\laragon`
- **JANGAN ubah path.** Path dengan spasi (`Program Files`) sering bikin error

**1.3** Buka Laragon → klik **Start All**
- Tombol Apache & MySQL harus menyala hijau
- Kalau merah → §9 Troubleshooting

**1.4** ✅ **VERIFIKASI:** klik tombol **Terminal** di Laragon (bukan CMD Windows biasa), ketik:
```
php -v
```
**Harus muncul:** `PHP 8.3.x` atau `PHP 8.4.x`

> 🔴 **Kalau muncul PHP 8.1 atau 8.2 → WAJIB diganti.** Laravel 12 butuh **minimal PHP 8.2**, tapi 8.3 disarankan.
> Caranya: klik kanan ikon Laragon → **PHP → Version → pilih 8.3**.
> Kalau 8.3 tidak ada di daftar: unduh dari https://windows.php.net/download/ (pilih **Thread Safe x64**), ekstrak ke `C:\laragon\bin\php\php-8.3.x`, lalu ulangi klik kanan → PHP → Version.

---

## §2. VERIFIKASI SEMUA TOOL

Di **Terminal Laragon**, jalankan satu per satu:

```
php -v
composer --version
node -v
npm -v
git --version
```

**Hasil yang benar:**

| Perintah | Minimal | Kalau gagal |
|----------|---------|-------------|
| `php -v` | **8.2**, disarankan 8.3 | §1.4 |
| `composer --version` | 2.x | Laragon → Tools → Composer |
| `node -v` | **v20** ke atas | unduh https://nodejs.org (LTS), install, **restart Laragon** |
| `npm -v` | 10.x | ikut Node |
| `git --version` | 2.x | https://git-scm.com/download/win |

> ⛔ **JANGAN LANJUT kalau ada satu pun yang gagal.** Semua langkah berikutnya akan error beruntun dan Boss akan sulit melacak sumbernya.

---

## §3. BUAT DATABASE

**3.1** Di Laragon: klik kanan ikon → **MySQL → CLI**

**3.2** Ketik (tekan Enter tiap baris):
```sql
CREATE DATABASE taskapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SHOW DATABASES;
EXIT;
```

**3.3** ✅ **VERIFIKASI:** `taskapp` muncul di daftar.

> **Kenapa `utf8mb4_unicode_ci`?** Wajib per `02-DATA-MODEL.md §0`. Tanpa ini, emoji & karakter khusus rusak.
> **Kredensial default Laragon:** user `root`, password **kosong**. Aman untuk lokal — **WAJIB diganti sebelum produksi** (F-13).

---

## §4. INSTALL LARAVEL 12 + REACT STARTER KIT

**4.1** Di Terminal Laragon:
```
cd C:\laragon\www
```

**4.2** 🔴 **PERINTAH KRITIS — salin PERSIS, jangan diketik ulang dari ingatan:**
```
composer create-project laravel/laravel:^12.0 taskapp
```

> **JANGAN pakai `laravel new taskapp`** — itu memasang Laravel 13 (F-65).
> Bagian `:^12.0` itulah yang mengunci versi 12.

**4.3** Installer akan **bertanya beberapa hal**. Jawab begini:

| Pertanyaan | Jawab |
|-----------|-------|
| *Which starter kit would you like?* | **react** |
| *Which authentication provider?* | **Laravel's built-in** (BUKAN WorkOS) |
| *Which testing framework?* | **PHPUnit** |
| *Which database?* | **MySQL** |
| *Run default migrations?* | **No** ← 🔴 **PENTING** |

> 🔴 **Kenapa "No" pada migration?**
> Kita punya **17 migration sendiri** (`02-DATA-MODEL.md §6`) dengan urutan dependency yang ketat. Migration bawaan akan bentrok. **Claude Code yang akan membuatnya di Hari-1.**

**4.4** Tunggu. Ini mengunduh ratusan paket — **5–15 menit** tergantung internet.

**4.5** ✅ **VERIFIKASI — INI PALING PENTING DI SELURUH TUTORIAL:**
```
cd taskapp
php artisan --version
```
**HARUS muncul:** `Laravel Framework 12.x.x`

> 🔴 **Kalau muncul `13.x.x` → SALAH.** Hapus folder `taskapp`, ulangi dari §4.2 dan pastikan ada `:^12.0`.

---

## §5. SAMBUNGKAN KE DATABASE

**5.1** Buka `C:\laragon\www\taskapp\.env` dengan **Notepad**

> File `.env` mungkin tidak terlihat. Di File Explorer: **View → Show → Hidden items**.

**5.2** Cari bagian `DB_` → ubah jadi persis:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taskapp
DB_USERNAME=root
DB_PASSWORD=
```
`DB_PASSWORD` memang **dikosongkan**.

**5.3** Ubah juga (per `01-PRD.md §10`):
```
APP_TIMEZONE=UTC
APP_LOCALE=id
```

> **Kenapa UTC, bukan WIB?** Aturan: **UTC di database, WIB di tampilan**. Konversi ditangani aplikasi. Menyimpan waktu lokal di DB = mimpi buruk saat hitung KPI lintas zona nanti (v3.0 freelance).

**5.4** **Simpan** (Ctrl+S).

**5.5** ✅ **VERIFIKASI:**
```
php artisan migrate:status
```
Muncul daftar migration (mungkin kosong) = **koneksi DB berhasil**.
Muncul `SQLSTATE[HY000] [1045]` atau `Connection refused` = §9.

---

## §6. TARUH FILE DOKUMENTASI

**6.1** Salin **6 file** kita ke lokasi berikut:

```
C:\laragon\www\taskapp\
├── CLAUDE.md                      ← taruh di SINI (root)
└── docs\
    ├── 01-PRD.md
    ├── 02-DATA-MODEL.md
    ├── 03-BUSINESS-FLOW.md
    ├── 04-FINDING-REGISTRY.md
    ├── 05-TUTORIAL-SETUP.md       ← file ini
    └── PROMPT-HARI-1.md
```

> 🔴 **`CLAUDE.md` WAJIB di root**, sejajar dengan folder `app` dan `config` — **bukan** di dalam `docs`. Kalau salah taruh, Claude Code **tidak akan membacanya otomatis**, dan seluruh protokol kita tidak berlaku.

**6.2** ✅ **VERIFIKASI** di Terminal:
```
dir CLAUDE.md
dir docs
```
Keduanya harus menampilkan file, bukan `File Not Found`.

---

## §7. INSTALL & LOGIN CLAUDE CODE

**7.1** Install:
```
npm install -g @anthropic-ai/claude-code
```

**7.2** ✅ **VERIFIKASI:**
```
claude --version
```
Muncul nomor versi = berhasil. `'claude' is not recognized` → §9.

**7.3** 🔴 **LANGKAH YANG PALING SERING DILEWAT — CEK API KEY:**
```
echo %ANTHROPIC_API_KEY%
```

| Hasil | Artinya | Tindakan |
|-------|---------|----------|
| `%ANTHROPIC_API_KEY%` | Kosong ✅ | Lanjut |
| Muncul teks `sk-ant-...` | 🔴 **BAHAYA** | Jalankan perintah di bawah |

**Kalau ada isinya, hapus:**
```
set ANTHROPIC_API_KEY=
setx ANTHROPIC_API_KEY ""
```
Lalu **tutup Terminal, buka lagi**, ulangi cek.

> 🔴 **KENAPA INI PENTING:** kalau variable itu ada, Claude Code **memakai API key** (bayar per token), **bukan langganan Pro Boss**. Boss bisa kena tagihan tanpa sadar.

**7.4** Login:
```
cd C:\laragon\www\taskapp
claude
```
Browser terbuka → login dengan **akun Pro Boss** → kembali ke Terminal.

**7.5** ✅ **VERIFIKASI:** di dalam Claude Code, ketik:
```
/status
```
Harus tertulis langganan **Pro**, bukan API. Kalau tertulis API → ulangi §7.3.

---

## §8. EKSEKUSI HARI-1

**8.1** Pastikan berada di folder proyek dan Claude Code terbuka:
```
cd C:\laragon\www\taskapp
claude
```

**8.2** Buka `docs\PROMPT-HARI-1.md` → salin blok antara `=== MULAI ===` dan `=== SELESAI ===` → **tempel** ke Claude Code → **Enter**.

**8.3** **Claude Code akan BERHENTI dan melapor** (LANGKAH 0). Ini **disengaja** — Mandatory #7, tidak ada eksekusi unilateral.

**8.4** **Baca laporannya.** Periksa:
- Apakah versi yang dilaporkan **Laravel 12.x**? (kalau 13 → STOP)
- Apakah checklistnya masuk akal?
- Apakah ada yang dia bilang tidak jelas?

**8.5** Kalau setuju, ketik:
```
LANJUT
```

**8.6** Claude Code mulai bekerja. **Boss cukup mengawasi.**

---

## §8B. KALIMAT SIAP PAKAI SAAT CLAUDE CODE MELENCENG

Simpan ini. Tempel apa adanya kalau perlu.

**Kalau mulai bikin controller/UI di Hari-1:**
```
STOP. Itu di luar scope Hari-1 (docs/01-PRD.md §5.1). Kembali ke daftar A-F.
```

**Kalau klaim "selesai" tanpa bukti:**
```
Tempel output asli setiap item Definition of Done. Klaim tanpa bukti tidak diterima (CLAUDE.md §2 no.11).
```

**Kalau bikin scheduler/cron untuk counter:**
```
STOP. F-38: counter = calculated, BUKAN stateful. Simpan timestamp, hitung saat ditanya.
Tidak ada scheduler di v0.5.
```

**Kalau hardcode nama status:**
```
STOP. F-44: JANGAN hardcode nama status. Pakai flag is_work_state / is_review / is_completed.
```

**Kalau mau install paket baru:**
```
STOP. Dependency baru butuh approval Boss (CLAUDE.md §4). Jelaskan kenapa perlu, tunggu keputusan.
```

**Kalau Boss bingung dia sedang apa:**
```
Berhenti sebentar. Jelaskan dalam bahasa awam: kamu sedang mengerjakan apa, kenapa, dan sudah sampai mana di checklist.
```

---

## §8C. BUKTI HARI-1 SELESAI

Claude Code **wajib** menempel output asli dari semua ini:

```
php artisan migrate:fresh --seed
php artisan tinker
```
Di dalam tinker:
```php
Task::count();                                    // = 35
TaskStatus::count();                              // = 8
TaskTemplate::count();                            // = 3
TaskTimeSegment::count();                         // = 10
ActivityLog::count();                             // > 0   <- BUKTI observer jalan
Task::whereNotNull('actual_minutes')->count();    // = 3   <- BUKTI freeze F-39
```

> **Angka tidak bisa diklaim — harus dibuktikan.** `ActivityLog::count() > 0` membuktikan observer (F-22) benar-benar berjalan, bukan sekadar filenya ada. Ini tulang punggung seluruh KPI Boss.

---

## §9. TROUBLESHOOTING

| Gejala | Sebab | Solusi |
|--------|-------|--------|
| `php artisan --version` → **13.x** | Lupa `:^12.0` | Hapus folder `taskapp`, ulangi §4.2 |
| `'php' is not recognized` | Pakai CMD Windows, bukan Terminal Laragon | Pakai tombol **Terminal** di Laragon |
| `'claude' is not recognized` | npm global tidak di PATH | Tutup & buka Terminal. Masih gagal → restart Windows |
| MySQL merah di Laragon | Port 3306 dipakai aplikasi lain | Matikan MySQL/XAMPP lain, atau Laragon → Menu → MySQL → ganti port |
| `SQLSTATE[HY000] [1045]` | Kredensial `.env` salah | Cek §5.2 — `DB_PASSWORD` harus **kosong** |
| `SQLSTATE[HY000] [2002]` | MySQL mati | Laragon → **Start All** |
| `Class "X" not found` | Autoload basi | `composer dump-autoload` |
| `npm run build` gagal | Node terlalu lama | `node -v` harus **v20+** |
| `/status` → tertulis API | `ANTHROPIC_API_KEY` masih ada | Ulangi §7.3, **restart Terminal** |
| Claude Code kena limit | Kuota 5 jam habis | Kerjaan **aman di disk**. Tunggu reset, lalu §10 |

---

## §10. MELANJUTKAN SESI BERIKUTNYA

Kalau kena limit atau berhenti di tengah jalan, **jangan ulang dari nol**. Buka Claude Code di folder proyek, lalu:

```
Baca CLAUDE.md dan docs/. Lanjutkan Hari-1 dari checklist yang belum selesai.
Laporkan dulu apa yang sudah ada dan apa yang belum sebelum menulis kode.
```

**File sudah tersimpan di disk.** Claude Code tidak mengingat percakapan sebelumnya, **tapi dia membaca kode dan dokumen** — itulah gunanya `CLAUDE.md` dan folder `docs`.

---

## §11. CHEAT SHEET

```
# Buka proyek
cd C:\laragon\www\taskapp
claude

# Cek kuota
/status

# Reset database + isi data contoh
php artisan migrate:fresh --seed

# Jalankan aplikasi
composer run dev
# lalu buka http://localhost:8000

# Cek isi database
php artisan tinker

# Rapikan kode
./vendor/bin/pint
npm run lint

# Build frontend
npm run build
```

---

## §12. YANG TIDAK BOLEH BOSS LAKUKAN

| ❌ Jangan | Kenapa |
|-----------|--------|
| **`laravel new`** | Memasang Laravel 13, bertentangan dengan spec (F-65) |
| Install PHP/MySQL terpisah dari Laragon | Bentrok port & PATH |
| Edit file di folder `vendor/` | Tertimpa setiap `composer install` |
| Set `ANTHROPIC_API_KEY` | Boss dibilling per token, bukan pakai Pro |
| Percaya klaim "selesai" tanpa output | CLAUDE.md §2 no.11 |
| Menambah fitur di tengah Hari-1 | Scope freeze — PRD §5.1 |
| `php artisan migrate:fresh` di produksi | **MENGHAPUS SEMUA DATA** |

> **Yang terakhir itu serius.** `migrate:fresh` aman selama masih lokal dan datanya cuma seeder. **Begitu tim mulai memakai aplikasi, perintah itu menghapus seluruh data KPI** — dan per F-51, data KPI **tidak bisa direkonstruksi.**
