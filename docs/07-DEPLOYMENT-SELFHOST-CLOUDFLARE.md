# 07 — DEPLOYMENT SELF-HOST: PC Rumah + Docker + Cloudflare Tunnel

> Ditulis untuk Boss, bukan programmer. Skenario ini **beda** dari `06-DEPLOYMENT.md`
> (yang ditulis untuk VPS/hosting native tanpa Docker) — di sini server-nya PC
> milik sendiri di rumah, tanpa IP publik tetap, seluruh aplikasi jalan di
> **Docker container**, diekspos ke internet lewat Cloudflare Tunnel.
> Domain (`deevatech.my.id`) tetap terdaftar di rumahweb sebagai registrar.
>
> **REVISI 2026-09-22:** dokumen ini sebelumnya menjelaskan instalasi native
> (PHP-FPM + Nginx + Supervisor langsung di OS). Boss memutuskan pindah ke
> **full Docker** — alasan utama: PC yang sama akan menjalankan **dua aplikasi**
> (task-management ini + sistem absensi), dan Docker mengisolasi versi
> PHP/dependency tiap app supaya tidak rebutan/bentrok di level OS. Langkah
> native yang lama **tidak berlaku lagi** untuk task-management.

---

## 0. KEPUTUSAN YANG SUDAH DIAMBIL (jangan diulang tiap deploy)

| Keputusan | Pilihan | Kenapa |
|---|---|---|
| OS server | Ubuntu Server 22.04/24.04 LTS | Standar ekosistem Docker/Laravel, ringan & stabil untuk servis 24/7 |
| Cara jalankan aplikasi | Docker + Docker Compose | PC yang sama akan menjalankan task-management **dan** absensi — Docker isolasi dependency tiap app, tidak bentrok versi PHP/library di level OS |
| Cara ekspos ke internet | Cloudflare Tunnel | PC di rumah tidak punya IP publik tetap & tidak perlu buka port router (NAT) |
| Setup DNS domain | **Full setup** — nameserver `deevatech.my.id` pindah ke Cloudflare | Partial/CNAME setup di Cloudflare **hanya tersedia di plan Business/Enterprise**, bukan Free. Di plan Free, cuma ada opsi pindah nameserver seluruh domain |
| Tunnel untuk multi-app | **Satu tunnel terpisah per aplikasi** (bukan satu tunnel gabungan) | task-management dan absensi masing-masing punya container `cloudflared` + token sendiri. Konsekuensinya masing-masing app compose stack **tidak perlu** saling share docker network — benar-benar independen, tapi berarti 2 tunnel jalan terus & 2 tempat setting token untuk dikelola |

🔴 **Konsekuensi keputusan DNS di atas:** SEMUA record DNS `deevatech.my.id` yang aktif sekarang di rumahweb (website, email MX/SPF/DKIM/DMARC, subdomain lain) harus direkreasi di Cloudflare **sebelum** nameserver diganti — kalau ada yang kelewat, layanan itu mati begitu propagasi selesai. Lihat §7.

---

## 1. PRASYARAT DI PC SERVER

| Software | Fungsi |
|---|---|
| Ubuntu Server 22.04/24.04 LTS | OS dasar |
| Docker Engine + Docker Compose plugin | Menjalankan seluruh stack (app, nginx, mysql, reverb, queue, scheduler, cloudflared) sebagai container |
| Git | Clone & update source code |

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git

# Docker Engine resmi (bukan docker.io bawaan Ubuntu -- versi lebih baru)
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER   # logout/login ulang supaya berlaku
```

**Catatan:** Node.js/npm dan PHP/Composer **tidak perlu diinstal di host** — semuanya jalan di dalam container atau lewat image sekali-pakai (lihat §3). Ini yang membuat PC bisa menjalankan task-management (PHP 8.2) dan absensi (versi PHP berapa pun) tanpa konflik di level OS.

---

## 2. STRUKTUR FOLDER

```bash
sudo mkdir -p /opt/apps/task-management
sudo chown -R $USER:$USER /opt/apps/task-management
cd /opt/apps/task-management
git clone <url-repo-anda> .
```

Kalau absensi juga dideploy di PC yang sama, taruh di folder **sejajar**, bukan di dalam folder ini — masing-masing app adalah docker-compose stack independen:

```
/opt/apps/task-management/   <- repo ini
/opt/apps/absensi/           <- repo absensi, compose stack terpisah
```

---

## 3. SETUP ENV & BUILD PERTAMA KALI

```bash
cd /opt/apps/task-management
cp .env.example .env
nano .env
```

Isi/ubah nilai berikut di `.env` (lihat komentar terkait di `.env.example` untuk baris persisnya):

```env
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_URL=https://task.deevatech.my.id

# HARUS sama dengan env service `mysql` di docker-compose.yml
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=task_management
DB_USERNAME=laravel
DB_PASSWORD=laravel_password

# Nilai yang dilihat BROWSER lewat Cloudflare (443/https). Proses Reverb
# sendiri tetap listen di 0.0.0.0:8080 di dalam docker network -- nginx yang
# menjembatani dua alamat ini (lihat docker/nginx/default.conf, sudah ada).
REVERB_HOST=task.deevatech.my.id
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_HOST=task.deevatech.my.id
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

# Diisi setelah bikin tunnel di §5
CLOUDFLARE_TUNNEL_TOKEN=
```

🔴 **`DB_PASSWORD` dan `MYSQL_ROOT_PASSWORD` bawaan di `docker-compose.yml` (`laravel_password` / `root_password`) adalah nilai contoh dari development.** Ganti ke password kuat sebelum expose ke internet — ubah di **kedua tempat** (`.env` dan blok `environment:` service `mysql` di `docker-compose.yml`, keduanya harus tetap sama).

Build & jalankan:

```bash
docker compose build
docker compose up -d mysql
docker compose ps   # tunggu mysql sampai status "healthy"

docker compose up -d app nginx scheduler queue-worker reverb

# vendor/ dipasang lewat bind mount host -- install sekali di sini supaya
# cocok dengan environment container (bukan cuma andalkan hasil build image)
docker compose exec app composer install --no-dev --optimize-autoloader --no-interaction

docker compose exec app php artisan key:generate
docker compose exec app php artisan reverb:install    # generate APP_ID/KEY/SECRET sendiri, JANGAN pakai contoh
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

# permission storage/bootstrap-cache -- container jalan sebagai www-data,
# tapi file datang dari bind mount host yang dimiliki user biasa
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

**Build asset frontend (Vite/React)** — container `app` cuma image PHP-FPM, tidak ada Node. Pakai image Node sekali-pakai (bukan container permanen):

```bash
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html node:20 sh -c "npm ci && npm run build"
```

Jalankan ulang perintah ini setiap kali ada perubahan kode frontend saat deploy berikutnya.

---

## 4. UPDATE KODE SAAT DEPLOY BERIKUTNYA

```bash
cd /opt/apps/task-management
git pull
docker compose exec app composer install --no-dev --optimize-autoloader --no-interaction
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html node:20 sh -c "npm ci && npm run build"
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose restart app scheduler queue-worker reverb
```

---

## 5. CLOUDFLARE TUNNEL (dashboard, bukan CLI di server)

Container `cloudflared` di `docker-compose.yml` pakai mode **token dari dashboard** (bukan file `config.yml` lokal) — routing hostname → service diatur di Cloudflare, bukan di server.

1. Login **Cloudflare Zero Trust dashboard** → **Networks → Tunnels → Create a tunnel**.
2. Pilih connector type **Docker**. Cloudflare menampilkan token panjang di command contoh — **copy tokennya saja**, bukan seluruh command.
3. Tempel token itu ke `.env` project ini: `CLOUDFLARE_TUNNEL_TOKEN=<token>`.
4. Masih di dashboard, tab **Public Hostname** pada tunnel yang sama:
   - Subdomain: `task`, Domain: `deevatech.my.id`
   - Service type: `HTTP`, URL: `nginx:80`
   
   *(`nginx:80` merujuk ke nama container `nginx` di dalam docker network `task-management` — bukan port host `127.0.0.1:8081` yang cuma untuk debug lokal.)*
5. Jalankan container: `docker compose up -d cloudflared`
6. `docker compose logs -f cloudflared` — tunggu sampai baris `Registered tunnel connection`.

🔴 **Public Hostname (langkah 4) hanya bisa disimpan setelah** `deevatech.my.id` aktif sebagai zona penuh di akun Cloudflare (nameserver sudah pindah — lihat §7).

---

## 6. DEPLOY ABSENSI DI PC YANG SAMA — POLA COEXISTENCE

Sesuai keputusan §0, absensi adalah **docker-compose stack terpisah**, punya **tunnel & token sendiri**. Supaya tidak bentrok dengan stack task-management ini di satu PC:

| Hal | task-management (repo ini) | absensi (harus beda) |
|---|---|---|
| Nama network compose | `task-management` | `absensi` (jangan reuse nama sama) |
| Prefix `container_name` | `task-management-*` | `absensi-*` |
| Port nginx ke host | `127.0.0.1:8081:80` | port lain, mis. `127.0.0.1:8082:80` |
| Volume MySQL | `mysql_data` (scoped ke project ini) | volume/nama beda, jangan pakai nama sama persis di compose project berbeda |
| Container `cloudflared` | milik task-management, token sendiri | container `cloudflared` sendiri di compose stack absensi, token tunnel sendiri dari dashboard |
| Public hostname | `task.deevatech.my.id` → tunnel task-management → `nginx:80` (network `task-management`) | mis. `absensi.deevatech.my.id` → tunnel absensi → `nginx:80` (network `absensi`) |

Karena masing-masing tunnel connect ke container lewat **docker network internal stack-nya sendiri** (bukan lewat port host), dua stack ini **tidak perlu** saling terhubung sama sekali — cukup jalan berdampingan di Docker Engine yang sama. Kalau nanti absensi juga Laravel + Docker, folder-nya cukup mengikuti struktur `docker-compose.yml` repo ini sebagai referensi, dengan penyesuaian nama di atas.

---

## 7. MIGRASI DNS DOMAIN KE CLOUDFLARE (dieksekusi Boss — menyentuh domain live)

Langkah ini **tidak bisa diotomasi dari sini** karena menyentuh akun rumahweb & Cloudflare milik Boss langsung, dan berisiko mematikan email/website eksisting kalau ada langkah yang salah/kelewat.

1. Buat akun Cloudflare (plan Free cukup) → **Add a Site** → masukkan `deevatech.my.id`.
2. Cloudflare akan otomatis scan DNS record yang ada di rumahweb sekarang. **Cocokkan satu-satu secara manual** dengan panel DNS rumahweb — auto-scan tidak dijamin menangkap semua record (terutama TXT/SPF/DKIM/DMARC untuk email).
3. Kalau ada yang meleset/hilang, tambahkan manual di Cloudflare sebelum lanjut.
4. Cloudflare akan memberi 2 nameserver baru. Catat.
5. Login panel domain di rumahweb → ganti nameserver domain ke 2 nameserver Cloudflare tersebut.
6. Tunggu propagasi (biasanya 1–24 jam; Cloudflare kirim email begitu zona aktif).
7. Setelah aktif, lanjut ke §5 (Public Hostname baru bisa disimpan).

🔴 **Sebelum eksekusi langkah ini, konfirmasi dulu:** apakah saat ini ada website atau email aktif (`@deevatech.my.id`) yang jalan di rumahweb? Kalau ada, daftar lengkap record-nya harus dipastikan aman dulu sebelum nameserver diganti.

---

## 8. FIREWALL PC SERVER

```bash
sudo ufw allow OpenSSH
sudo ufw enable
```

Tidak perlu allow port 80/443 — Cloudflare Tunnel bekerja lewat koneksi **outbound** dari PC ke jaringan Cloudflare, jadi PC bisa sepenuhnya di belakang NAT rumah tanpa port forwarding apa pun di router.

🔴 **Docker menulis rule iptables sendiri dan bisa melewati `ufw`.** Kalau ada `ports:` di compose yang di-bind tanpa `127.0.0.1:` di depan (mis. `"8081:80"` alih-alih `"127.0.0.1:8081:80"`), port itu **tetap bisa diakses dari LAN/WAN meski `ufw deny` aktif** — `ufw` tidak mengontrol chain yang dipakai Docker. `docker-compose.yml` di repo ini sudah di-bind ke `127.0.0.1` untuk port nginx; kalau menambah service baru dengan `ports:`, pastikan pola yang sama diikuti kecuali memang sengaja mau diakses dari jaringan lain.

---

## 9. CHECKLIST VERIFIKASI SETELAH SETUP (F-73/F-75 — bukti nyata, bukan asumsi)

- [ ] `https://task.deevatech.my.id` bisa dibuka, sertifikat SSL valid (terbit dari Cloudflare)
- [ ] Login berhasil; buka detail task → tambah komentar dari 2 browser berbeda → realtime muncul tanpa refresh (bukti Reverb jalan lewat Tunnel)
- [ ] `docker compose ps` → semua service `Up`/`healthy`, tidak ada yang `Restarting`
- [ ] `docker compose logs cloudflared --tail=50` → ada baris `Registered tunnel connection`, tidak ada error berulang
- [ ] Reboot PC penuh sekali → setelah nyala, `docker compose ps` menunjukkan semua service otomatis `Up` lagi tanpa campur tangan manual (butuh Docker daemon `enable` saat boot — default aktif setelah instalasi `get.docker.com`)
- [ ] Dari device lain di LAN yang sama, coba akses `http://<ip-pc-server>:8081` → **harus GAGAL connect** (bukti port nginx benar-benar cuma listen di `127.0.0.1`, bukan ke internet lewat jalur lain selain Tunnel)

## 10. RISIKO OPERASIONAL YANG PERLU DISADARI (bukan bug, tapi konsekuensi arsitektur)

| Risiko | Dampak | Mitigasi |
|---|---|---|
| PC rumahan di ISP residensial: listrik/internet mati | Aplikasi down total untuk seluruh tim sampai PC/internet nyala lagi | UPS kecil untuk PC + router/modem, minimal cukup untuk shutdown aman |
| IP rumah dinamis (bukan IP publik tetap) | **Tidak masalah** — Tunnel tidak bergantung pada IP publik statis, jadi ini bukan risiko nyata di arsitektur ini | — |
| Nameserver domain sepenuhnya di Cloudflare | Kalau perlu ubah DNS apa pun di masa depan (tambah subdomain lain, email baru), dilakukan di dashboard Cloudflare, **bukan** lagi di panel rumahweb | Ingat titik kendali DNS sudah pindah — jangan cari-cari di rumahweb saat butuh ubah DNS nanti |
| 2 aplikasi (task-management + absensi) rebutan resource CPU/RAM di 1 PC | Kalau PC spek pas-pasan, salah satu/keduanya bisa lambat saat sama-sama load tinggi | Pantau `docker stats`; kalau jadi masalah nyata, itu keputusan upgrade hardware/pisah server — bukan sesuatu yang bisa diakali di level compose |
