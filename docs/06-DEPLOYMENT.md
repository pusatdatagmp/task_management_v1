# 06 — DEPLOYMENT (Panduan untuk Boss)

> Ditulis untuk Boss, bukan programmer. Tiap langkah pakai bahasa awam + alasan
> KENAPA, bukan cuma perintahnya. Kalau ragu di tengah jalan: **BERHENTI, jangan tebak.**

---

## 1. SYARAT SERVER

Sebelum instal apa pun, pastikan server (VPS/hosting) punya:

| Butuh | Versi minimal | Kenapa |
|---|---|---|
| PHP | 8.2 (proyek ini dikunci di Laravel 12, lihat F-65) | Laravel 12 tidak jalan di PHP lebih lama |
| MySQL | 8.0 | F-7 (search), F-37 (kolom `json` native) butuh MySQL 8 asli — bukan MariaDB (lihat Hari-2, MariaDB bikin `json` diam-diam jadi `longtext`) |
| Node.js | 20+ | Untuk `npm run build` (build aset React sekali saat deploy, bukan jalan terus) |
| Composer | 2.x | Install dependency PHP |

---

## 2. VARIABEL `.env` PRODUKSI

Salin `.env.example` jadi `.env`, lalu **WAJIB** ubah baris-baris ini (jangan percaya nilai bawaan `.env.example` — beberapa di antaranya sengaja/tidak sengaja masih nilai default Laravel, bukan nilai proyek ini):

```
APP_NAME="DEEVATECH Task Management"
APP_ENV=production
APP_KEY=                      # kosongkan, lalu jalankan: php artisan key:generate
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta      # F-69 — JANGAN UTC, JANGAN dikosongkan
APP_URL=https://domain-asli-anda.com

DB_CONNECTION=mysql            # BUKAN sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_produksi
DB_USERNAME=user_produksi
DB_PASSWORD=password_produksi_yang_kuat

SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

### 🔴 `APP_DEBUG=false` WAJIB

Kalau `APP_DEBUG=true` di produksi, setiap error PHP menampilkan **stack trace lengkap** ke siapa pun yang memicunya lewat browser — termasuk isi file `.env` (password database, `APP_KEY`) kalau error-nya menyentuh koneksi DB. Ini bukan teori: pola ini persis yang bikin `/login` "kelihatan aneh" tapi sebenarnya bocor data kalau `APP_DEBUG` lupa dimatikan.

### `APP_KEY`

Jangan copy-paste `APP_KEY` dari server dev/staging. Generate baru khusus produksi:
```
php artisan key:generate
```
`APP_KEY` dipakai untuk enkripsi session & cookie — kalau bocor, orang lain bisa memalsukan session login.

---

## 3. LANGKAH DEPLOY (URUTAN INI, JANGAN DIBALIK)

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build

php artisan migrate --force
```

### 🔴 `php artisan migrate --force` — BUKAN `migrate:fresh`

- `migrate` = menambah tabel/kolom yang belum ada. **Aman**, data lama tidak disentuh.
- `migrate:fresh` = **MENGHAPUS SEMUA TABEL lalu buat ulang dari nol.** Kalau ini dijalankan di produksi yang sudah punya data tim (task, jam kerja, riwayat approve), **semua hilang permanen, tidak bisa dikembalikan** kecuali dari backup (lihat §5).
- `--force` dibutuhkan karena Laravel akan menolak `migrate` di `APP_ENV=production` tanpa flag ini (pengaman bawaan Laravel — jangan dimatikan lewat cara lain).

Setelah tabel siap, jalankan seeder produksi **SEKALI SAJA** (baca §4 dulu):
```bash
php artisan db:seed --class=ProductionSeeder --force
```

---

## 4. SEEDER PRODUKSI

`database/seeders/ProductionSeeder.php` mengisi **hanya** yang wajib ada supaya aplikasi bisa dipakai:
- 1 organisasi ("DEEVATECH")
- 1 jendela jam kerja (Sen–Jum 08:00–17:00, kapasitas 480 menit/hari)
- 1 akun admin pertama (`admin@deevatech.com`)

**Tidak ada** project/task contoh — data nyata dimulai dari nol saat tim mulai pakai.

Saat dijalankan, terminal akan mencetak **password admin acak SEKALI** — salin dan simpan di tempat aman (password manager), lalu login dan ganti password lewat halaman Profil. Password ini **tidak disimpan di mana pun** dalam bentuk terbaca — kalau lupa dicatat, satu-satunya jalan adalah reset password lewat database langsung.

🔴 **Jangan jalankan seeder ini dua kali** di database yang sama — tidak ada pengaman anti-duplikat, organisasi & admin akan dobel.

---

## 5. BACKUP (F-13) — WAJIB, BUKAN OPSIONAL

**Alasan ini bukan basa-basi:** data KPI (jam kerja, rejection count, quality rating) adalah dasar keputusan reward/punishment/gaji di roadmap v2.0. Data itu **tidak bisa dihitung ulang** kalau hilang — timestamp yang sudah lewat tidak bisa direkonstruksi (F-51).

### Backup manual (`mysqldump`)

```bash
mysqldump -u user_produksi -p nama_database_produksi > backup-2026-07-18.sql
```

### Backup harian otomatis (cron di server, bukan Laravel scheduler)

```bash
# crontab -e
0 2 * * * mysqldump -u user_produksi -p'password_produksi' nama_database_produksi | gzip > /path/backup/db-$(date +\%Y\%m\%d).sql.gz
```
Jalan tiap hari jam 02:00. Simpan minimal 30 hari ke belakang, lalu hapus yang lebih lama (supaya disk tidak penuh) — bisa ditambahkan `find /path/backup -mtime +30 -delete` di baris cron yang sama.

### Cara restore (kalau terjadi kesalahan fatal)

```bash
gunzip < db-20260718.sql.gz | mysql -u user_produksi -p nama_database_produksi
```
⚠️ Restore **menimpa** data yang ada saat ini dengan isi backup. Pastikan itu memang yang diinginkan sebelum menjalankan perintah ini.

---

## 6. CRON UNTUK SCHEDULER (NOTIFIKASI)

Aplikasi ini punya 2 command notifikasi harian (F-35 #4/#5, dijadwalkan di `routes/console.php`):
- `tasks:notify-due-soon` — jam 06:00, ingatkan task jatuh tempo besok
- `tasks:notify-overdue` — jam 06:05, ingatkan task yang sudah lewat deadline

Di server produksi (beda dari dev di Windows tadi yang dites manual), Laravel Scheduler **wajib** didaftarkan lewat 1 baris cron:

```bash
# crontab -e
* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

Baris ini jalan **tiap menit** tapi hanya benar-benar mengeksekusi command di atas pada jam yang dijadwalkan — bukan berarti notifikasi terkirim tiap menit (F-80 sudah menjaga idempotency, aman kalau `schedule:run` kebetulan overlap).

---

## 7. CHECKLIST KEAMANAN SEBELUM MENGUMUMKAN KE TIM

- [ ] `APP_DEBUG=false`
- [ ] Password admin pertama sudah diganti dari yang dicetak seeder
- [ ] HTTPS aktif (sertifikat SSL, misal lewat Let's Encrypt) — jangan kirim password login lewat HTTP polos
- [ ] `.env` **tidak** ikut ter-commit ke git (`git status` harus bersih dari `.env`) — cek `.gitignore` sudah memuat `.env`
- [ ] Backup harian (§5) sudah aktif dan **sudah dites restore minimal sekali** di server terpisah — backup yang belum pernah dites restore adalah backup yang belum terbukti berfungsi
- [ ] Cron scheduler (§6) sudah aktif — cek besok paginya notifikasi due-soon/overdue benar-benar masuk

---

## 8. 🔴 DAFTAR "JANGAN PERNAH DI PRODUKSI" (E3)

| Larangan | Akibat kalau dilanggar |
|---|---|
| `php artisan migrate:fresh` | **Menghapus SELURUH data** — semua task, riwayat, jam kerja tim hilang permanen, tidak bisa dikembalikan tanpa backup |
| `APP_DEBUG=true` | Stack trace error (termasuk kredensial database di `.env`) terbuka ke siapa pun yang memicu error |
| `.env` ikut ter-commit ke git / terupload ke repo publik | Password database & `APP_KEY` bocor — siapa pun yang lihat repo bisa akses data & memalsukan session |
| Menjalankan `ProductionSeeder` lebih dari sekali | Organisasi & admin dobel, data jadi berantakan |
| Mengedit baris `work_schedules` lama langsung di database | Melanggar F-40 (versioned) — riwayat jam kerja untuk perhitungan KPI lama jadi salah. Ubah jam kerja **selalu** lewat halaman Pengaturan (insert baris baru) |
| Backup tanpa pernah dites restore | Backup yang gagal di-restore ditemukan justru saat sedang butuh — sudah terlambat |
