# 07 — DEPLOYMENT SELF-HOST: PC Rumah + Cloudflare Tunnel + Domain Rumahweb

> Ditulis untuk Boss, bukan programmer. Skenario ini **beda** dari `06-DEPLOYMENT.md`
> (yang ditulis untuk VPS/hosting biasa) — di sini server-nya PC milik sendiri di
> rumah, tanpa IP publik tetap, diekspos ke internet lewat Cloudflare Tunnel.
> Domain (`deevatech.my.id`) tetap terdaftar di rumahweb sebagai registrar.
>
> Untuk hal yang **sama** dengan deployment biasa (isi `.env` produksi, aturan
> `migrate --force`, seeder, backup, checklist keamanan) — **rujuk `06-DEPLOYMENT.md`**,
> tidak diulang di sini supaya tidak ada dua sumber kebenaran yang bisa beda isi.

---

## 0. KEPUTUSAN YANG SUDAH DIAMBIL (jangan diulang tiap deploy)

| Keputusan | Pilihan | Kenapa |
|---|---|---|
| OS server | Ubuntu Server 22.04/24.04 LTS | Standar ekosistem Laravel, lebih ringan & stabil untuk servis 24/7 dibanding Windows |
| Cara ekspos ke internet | Cloudflare Tunnel | PC di rumah tidak punya IP publik tetap & tidak perlu buka port router (NAT) |
| Setup DNS domain | **Full setup** — nameserver `deevatech.my.id` pindah ke Cloudflare | Partial/CNAME setup di Cloudflare **hanya tersedia di plan Business/Enterprise**, bukan Free. Di plan Free, cuma ada opsi pindah nameserver seluruh domain |

🔴 **Konsekuensi keputusan DNS di atas:** SEMUA record DNS `deevatech.my.id` yang aktif sekarang di rumahweb (website, email MX/SPF/DKIM/DMARC, subdomain lain) harus direkreasi di Cloudflare **sebelum** nameserver diganti — kalau ada yang kelewat, layanan itu mati begitu propagasi selesai. Lihat §6.

---

## 1. STACK YANG DIINSTAL DI PC SERVER

| Software | Versi | Fungsi |
|---|---|---|
| Ubuntu Server | 22.04/24.04 LTS | OS dasar |
| PHP-FPM | 8.3 | Jalankan kode Laravel |
| MySQL | 8.0 | Database (native, bukan MariaDB — lihat `06-DEPLOYMENT.md` §1) |
| Nginx | terbaru repo Ubuntu | Web server + reverse proxy ke PHP-FPM & Reverb |
| Node.js | 20 LTS | `npm run build` sekali saat deploy (bukan proses yang jalan terus) |
| Composer | 2.x | Install dependency PHP |
| Supervisor | terbaru repo Ubuntu | Jaga proses Reverb & queue worker tetap hidup, auto-restart kalau crash |
| cloudflared | terbaru repo Cloudflare | Jembatan Tunnel dari PC ke jaringan Cloudflare |

```bash
sudo apt update && sudo apt upgrade -y

sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl \
  php8.3-common php8.3-opcache

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

sudo apt install -y mysql-server
sudo mysql_secure_installation

sudo apt install -y nginx

curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

sudo apt install -y supervisor git
```

---

## 2. STRUKTUR FOLDER

```bash
sudo mkdir -p /var/www/task-management
sudo chown -R $USER:www-data /var/www/task-management
cd /var/www/task-management
git clone <url-repo-anda> .
```

Deploy dipakai **satu folder tunggal** (bukan pola `releases/` bergaya Forge/Envoyer) — cukup untuk tim ±10 orang, sesuai prinsip "fungsionalitas dulu, optimasi kemudian". Kalau nanti butuh rollback instan tanpa downtime, itu perubahan arsitektur terpisah yang perlu didiskusikan dulu.

Setelah clone, ikuti **`06-DEPLOYMENT.md` §2–§4** untuk: isi `.env` produksi, `composer install`, `npm run build`, migrate, dan seeder. Tambahan khusus setup ini yang **tidak ada** di `06-DEPLOYMENT.md` karena project ini pakai Reverb (WebSocket komentar realtime):

```env
APP_URL=https://app.deevatech.my.id

REVERB_HOST=app.deevatech.my.id
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_HOST=app.deevatech.my.id
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

> Nilai di atas adalah alamat yang dilihat **browser** lewat Cloudflare (443/https).
> Proses Reverb sendiri tetap listen di `127.0.0.1:8080` secara lokal — Nginx yang
> menjembatani dua alamat ini (lihat §3).

```bash
php artisan reverb:install    # generate APP_ID/KEY/SECRET sendiri, JANGAN pakai contoh
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 3. NGINX — satu domain, dua backend

`/etc/nginx/sites-available/task-management`:

```nginx
server {
    listen 80;
    server_name app.deevatech.my.id;
    root /var/www/task-management/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # SUMBER : path default WebSocket Laravel Echo/Reverb.
    # DIPAKAI: proxy ke proses Reverb (port 8080) supaya browser cukup
    #          konek ke satu domain publik, tidak perlu port terpisah.
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/task-management /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

🔴 Nginx cukup `listen 80` di `127.0.0.1` — **jangan** buka port 80/443 ke router/internet. Cloudflare Tunnel yang jadi satu-satunya pintu masuk; membuka port tambahan di router hanya menambah celah serangan tanpa manfaat (lihat §7).

---

## 4. PROSES YANG HARUS SELALU HIDUP: Reverb & Queue Worker

`/etc/supervisor/conf.d/task-management.conf`:

```ini
[program:tm-reverb]
command=php /var/www/task-management/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/task-management/storage/logs/reverb.log

[program:tm-queue]
command=php /var/www/task-management/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/task-management/storage/logs/queue.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start all
```

**Kenapa perlu Supervisor:** `QUEUE_CONNECTION=database` di project ini berarti ada proses `queue:work` yang harus jalan terus-menerus di background memproses job (notifikasi, dsb). Kalau proses ini crash atau PC restart, tanpa Supervisor proses itu **tidak bangkit sendiri** — job menumpuk diam-diam tanpa error yang terlihat.

Untuk cron scheduler (notifikasi due-soon/overdue), ikuti `06-DEPLOYMENT.md` §6 — sama persis, tidak berubah untuk setup ini.

---

## 5. CLOUDFLARE TUNNEL

```bash
curl -L https://pkg.cloudflare.com/cloudflare-main.gpg | sudo gpg --dearmor -o /usr/share/keyrings/cloudflare-main.gpg
echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/cloudflared.list
sudo apt update && sudo apt install cloudflared

cloudflared tunnel login          # buka link yang muncul di browser, pilih zona deevatech.my.id
cloudflared tunnel create task-management
```

`~/.cloudflared/config.yml`:

```yaml
tunnel: <TUNNEL-ID-dari-perintah-create>
credentials-file: /root/.cloudflared/<TUNNEL-ID>.json

ingress:
  - hostname: app.deevatech.my.id
    service: http://localhost:80
  - service: http_status:404
```

```bash
cloudflared tunnel route dns task-management app.deevatech.my.id
sudo cloudflared service install
sudo systemctl enable --now cloudflared
```

🔴 `cloudflared tunnel login` dan `route dns` di atas **hanya berhasil setelah** `deevatech.my.id` aktif sebagai zona penuh di akun Cloudflare (nameserver sudah pindah — lihat §6). Kalau dijalankan sebelum itu, akan gagal karena Cloudflare belum mengenali domain tersebut.

---

## 6. MIGRASI DNS DOMAIN KE CLOUDFLARE (dieksekusi Boss — menyentuh domain live)

Langkah ini **tidak bisa diotomasi dari sini** karena menyentuh akun rumahweb & Cloudflare milik Boss langsung, dan berisiko mematikan email/website eksisting kalau ada langkah yang salah/kelewat.

1. Buat akun Cloudflare (plan Free cukup) → **Add a Site** → masukkan `deevatech.my.id`.
2. Cloudflare akan otomatis scan DNS record yang ada di rumahweb sekarang. **Cocokkan satu-satu secara manual** dengan panel DNS rumahweb — auto-scan tidak dijamin menangkap semua record (terutama TXT/SPF/DKIM/DMARC untuk email).
3. Kalau ada yang meleset/hilang, tambahkan manual di Cloudflare sebelum lanjut.
4. Cloudflare akan memberi 2 nameserver baru. Catat.
5. Login panel domain di rumahweb → ganti nameserver domain ke 2 nameserver Cloudflare tersebut.
6. Tunggu propagasi (biasanya 1–24 jam; Cloudflare kirim email begitu zona aktif).
7. Setelah aktif, lanjut ke §5 (`cloudflared tunnel route dns`).

🔴 **Sebelum eksekusi langkah ini, konfirmasi dulu:** apakah saat ini ada website atau email aktif (`@deevatech.my.id`) yang jalan di rumahweb? Kalau ada, daftar lengkap record-nya harus dipastikan aman dulu sebelum nameserver diganti.

---

## 7. FIREWALL PC SERVER

```bash
sudo ufw allow OpenSSH
sudo ufw enable
```

Tidak perlu allow port 80/443 — Cloudflare Tunnel bekerja lewat koneksi **outbound** dari PC ke jaringan Cloudflare, jadi PC bisa sepenuhnya di belakang NAT rumah tanpa port forwarding apa pun di router.

---

## 8. CHECKLIST VERIFIKASI SETELAH SETUP (F-73/F-75 — bukti nyata, bukan asumsi)

- [ ] `https://app.deevatech.my.id` bisa dibuka, sertifikat SSL valid (terbit dari Cloudflare)
- [ ] Login berhasil; buka detail task → tambah komentar dari 2 browser berbeda → realtime muncul tanpa refresh (bukti Reverb jalan lewat Tunnel)
- [ ] `sudo supervisorctl status` → `tm-reverb` dan `tm-queue` berstatus RUNNING
- [ ] `sudo systemctl status cloudflared` → active (running)
- [ ] Reboot PC penuh sekali → setelah nyala, cek ulang 3 poin di atas tanpa campur tangan manual (bukti semua service auto-start)
- [ ] Cabut/matikan koneksi WAN router sesaat → pastikan tidak ada port yang ter-expose langsung ke internet selain lewat Tunnel

## 9. RISIKO OPERASIONAL YANG PERLU DISADARI (bukan bug, tapi konsekuensi arsitektur)

| Risiko | Dampak | Mitigasi |
|---|---|---|
| PC rumahan di ISP residensial: listrik/internet mati | Aplikasi down total untuk seluruh tim sampai PC/internet nyala lagi | UPS kecil untuk PC + router/modem, minimal cukup untuk shutdown aman |
| IP rumah dinamis (bukan IP publik tetap) | **Tidak masalah** — Tunnel tidak bergantung pada IP publik statis, jadi ini bukan risiko nyata di arsitektur ini | — |
| Nameserver domain sepenuhnya di Cloudflare | Kalau perlu ubah DNS apa pun di masa depan (tambah subdomain lain, email baru), dilakukan di dashboard Cloudflare, **bukan** lagi di panel rumahweb | Ingat titik kendali DNS sudah pindah — jangan cari-cari di rumahweb saat butuh ubah DNS nanti |
