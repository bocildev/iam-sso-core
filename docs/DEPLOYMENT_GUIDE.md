# 🚀 PANDUAN LENGKAP DEPLOYMENT EKOSISTEM KE HOSTING & CLOUD
> **Ecosystem Hub**: IAM-SSO-CORE, Motion Portfolio & Mochi Finance  
> **Arsitek / Owner**: Moch. Adam Athallahrich (`athallahrich@gmail.com`)  
> **Target Lingkungan**: Production Cloud / VPS / Shared Hosting / Vercel Edge

---

## 🧭 1. Peta Ekosistem & Perumpamaan Awam

Agar Anda yang sedang belajar tidak bingung, mari kita bayangkan ekosistem ini seperti sebuah **Kompleks Perkantoran Digital**:

```
┌────────────────────────────────────────────────────────────────────────┐
│               🌐 DOMAIN UTAMA ANDA (misal: adamath.dev)                │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
    ┌───────────────────────────────┼───────────────────────────────┐
    ▼                               ▼                               ▼
[auth.adamath.dev]          [porto.adamath.dev]            [mochi.adamath.dev]
  IAM SSO CORE              PORTOFOLIO MOTION               MOCHI FINANCE PWA
  (Pos Satpam Pusat)        (Galeri Pameran Depan)          (Aplikasi Dompet Klien)
  Laravel 12 + MySQL        Nuxt 3 Kinetic (Vercel/Node)    Nuxt 3 PWA (Vercel/Node)
    │                               ▲                               ▲
    │                               │ Headless API                  │ Sync & OIDC
    ▼                               │                               │
[admin-porto.adamath.dev] ──────────┘               [mochi-admin.adamath.dev]
  PORTFOLIO ADMIN CMS                                 MOCHI FINANCE ADMIN
  (Ruang Kurator Galeri)                              (Ruang Pengelola Keuangan)
  Laravel 11 + MySQL                                  Laravel 11 + MySQL
```

### 💡 Kamus Perumpamaan Awam
1. **Hosting / VPS (Server)**: Ibarat **menyewa sebidang tanah & bangunan ruko** di internet yang menyala 24 jam non-stop.
2. **Domain & Subdomain (`auth.`, `porto.`)**: Ibarat **alamat surat & plang nama cabang ruko** agar pengunjung tidak tersesat (`auth.adamath.dev` untuk pos satpam, `porto.adamath.dev` untuk ruang galeri).
3. **Nginx (Web Server / Reverse Proxy)**: Ibarat **petugas lobi / resepsionis** yang menyambut pengunjung di gerbang, membaca alamat tujuan mereka, lalu mengantarkan mereka ke ruangan aplikasi yang tepat tanpa memperlihatkan dapur server.
4. **SSL / HTTPS (Gembok Hijau)**: Ibarat **jalur pipa berkunci anti-sadap**. Ini **wajib hukumnya** untuk SSO/OIDC karena token keamanan dan cookie sesi hanya mau dikirim lewat jalur aman.
5. **PM2**: Ibarat **asisten otomatis yang memastikan aplikasi Nuxt 3 selalu berjalan**. Jika aplikasi tiba-tiba mogok/restart, PM2 langsung membangunkannya kembali dalam 1 detik.
6. **Vercel / Edge Hosting**: Ibarat **jaringan etalase kaca instan di seluruh dunia**. Sangat ideal untuk frontend Nuxt karena cepat, gratis, dan otomatis mendapat SSL.

---

## 🏗️ 2. Pilihan Strategi Deployment

Tergantung anggaran dan preferensi Anda, ada 2 opsi terbaik:

### ⭐ Opsi A (Sangat Direkomendasikan): Hybrid Cloud (VPS Ringan + Vercel)
- **Frontend (2 Aplikasi)**: `portofolio-motion` & `management-keuangan` di-deploy ke **Vercel** (Gratis, ultra-cepat via CDN Global, otomatis HTTPS).
- **Backend (3 Aplikasi)**: `iam-sso-core`, `portfolio-admin`, `mochi-finance-admin` di-deploy ke **1 buah VPS Ubuntu 24.04 LTS (RAM 2 GB, CPU 1-2 Core)** seperti di DigitalOcean ($12/bln), Hetzner (€4/bln), atau DomaiNesia/Biznet Gio.

### Opsi B: All-in-One VPS (Seluruh 5 Aplikasi dalam 1 Server)
- Menggunakan Nginx Reverse Proxy di Ubuntu untuk mengarahkan port internal:
  - Port `8000`: `iam-sso-core` (PHP-FPM)
  - Port `8001`: `mochi-finance-admin` (PHP-FPM)
  - Port `8002`: `portfolio-admin` (PHP-FPM)
  - Port `3000`: `portofolio-motion` (Node PM2)
  - Port `3001`: `management-keuangan` (Node PM2)

### Opsi C: Shared Hosting / cPanel Biasa
- Jika Anda menggunakan cPanel (seperti Niagahoster, DomaiNesia cPanel, Rumahweb):
  - Buat 3 subdomain untuk backend Laravel: `auth.domain.com`, `admin-porto.domain.com`, `mochi-admin.domain.com`.
  - **PENTING**: Document Root di cPanel wajib diarahkan ke folder `/public` (misal: `public_html/auth/public`).
  - Frontend Nuxt di-build statis (`npx nuxi generate`) atau di-host di Vercel agar tidak membebani limit proses cPanel.

---

## 📋 3. Persiapan Domain & DNS

Di dashboard penyedia domain Anda (misal: Cloudflare, Niagahoster, Namecheap), tambahkan **DNS Record (Tipe A)** yang mengarah ke IP Server Publik Anda:

| Tipe | Nama Host / Subdomain | Target IP / Nilai | Keterangan |
| :--- | :--- | :--- | :--- |
| **A** | `auth` | `IP_VPS_ANDA` | Pusat SSO & Media Storage (`iam-sso-core`) |
| **A** | `admin-porto` | `IP_VPS_ANDA` | Backoffice CMS Portofolio (`portfolio-admin`) |
| **A** | `mochi-admin` | `IP_VPS_ANDA` | Backoffice SaaS Mochi (`mochi-finance-admin`) |
| **CNAME** / **A** | `porto` (atau `@`) | `cname.vercel-dns.com` (atau IP VPS) | Frontend Nuxt 3 Portofolio |
| **CNAME** / **A** | `mochi` | `cname.vercel-dns.com` (atau IP VPS) | Frontend Nuxt 3 Mochi Finance PWA |

---

## 🛠️ 4. Panduan Step-by-Step (Opsi VPS Ubuntu 22.04/24.04)

### Langkah 1: Install Paket Server yang Dibutuhkan
Jalankan di terminal SSH VPS Anda:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git curl unzip ufw

# Pasang PHP 8.3 & Ekstensi Wajib Laravel
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl \
    php8.3-gd php8.3-mbstring php8.3-xml php8.3-zip php8.3-bcmath php8.3-intl

# Pasang Composer
curl -sS https://getcomposer.org/installer -o composer-setup.php
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Pasang Node.js & PM2 (untuk aplikasi Nuxt)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
sudo npm install -g pm2
```

---

### Langkah 2: Buat 3 Database MySQL Terpisah
Masuk ke MySQL server:
```bash
sudo mysql
```
Jalankan query SQL berikut (ganti `PasswordKuatAnda123!` dengan password rahasia Anda):
```sql
CREATE DATABASE iam_sso_core CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE portfolio_admin_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE mochi_finance_admin_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'ecosystem_user'@'localhost' IDENTIFIED BY 'PasswordKuatAnda123!';

GRANT ALL PRIVILEGES ON iam_sso_core.* TO 'ecosystem_user'@'localhost';
GRANT ALL PRIVILEGES ON portfolio_admin_db.* TO 'ecosystem_user'@'localhost';
GRANT ALL PRIVILEGES ON mochi_finance_admin_db.* TO 'ecosystem_user'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

---

### Langkah 3: Deploy Aplikasi 1 — `iam-sso-core`
Letakkan project di `/var/www/iam-sso-core`:
```bash
cd /var/www
sudo git clone https://github.com/bocildev/iam-sso-core.git
cd iam-sso-core

# Salin dan sesuaikan .env
cp .env.example .env
nano .env
```

**Konfigurasi Wajib di `.env` `iam-sso-core`:**
```ini
APP_NAME="IAM SSO Core"
APP_ENV=production
APP_KEY=base64:... # Generate via: php artisan key:generate
APP_DEBUG=false
APP_URL=https://auth.domainanda.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iam_sso_core
DB_USERNAME=ecosystem_user
DB_PASSWORD=PasswordKuatAnda123!

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.domainanda.com

# Master Password Pengelola
IAM_MASTER_PASSWORD=PasswordSaktiSuperKuat2026!
IAM_ADMIN_EMAIL=athallahrich@gmail.com
```

Jalankan instalasi dependensi dan migrasi:
```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set hak akses folder storage & bootstrap
sudo chown -R www-data:www-data /var/www/iam-sso-core/storage /var/www/iam-sso-core/bootstrap/cache
sudo chmod -R 775 /var/www/iam-sso-core/storage /var/www/iam-sso-core/bootstrap/cache
```

---

### Langkah 4: Deploy Aplikasi 2 — `portfolio-admin`
```bash
cd /var/www
sudo git clone https://github.com/bocildev/portofolio-admin.git portfolio-admin
cd portfolio-admin

cp .env.example .env
nano .env
```

**Konfigurasi Wajib di `.env` `portfolio-admin`:**
```ini
APP_NAME="Motion Portfolio Admin"
APP_ENV=production
APP_KEY=base64:... # Generate via: php artisan key:generate
APP_DEBUG=false
APP_URL=https://admin-porto.domainanda.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=portfolio_admin_db
DB_USERNAME=ecosystem_user
DB_PASSWORD=PasswordKuatAnda123!

# Hubungan ke IAM SSO Core Pusat
IAM_BASE_URL=https://auth.domainanda.com
IAM_CLIENT_ID=app_portfolio_admin
IAM_CLIENT_SECRET=whsec_rahasia_dari_iam_console
IAM_REDIRECT_URI=https://admin-porto.domainanda.com/auth/callback
```

Jalankan build & optimasi:
```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache

sudo chown -R www-data:www-data /var/www/portfolio-admin/storage /var/www/portfolio-admin/bootstrap/cache
sudo chmod -R 775 /var/www/portfolio-admin/storage /var/www/portfolio-admin/bootstrap/cache
```

---

### Langkah 5: Deploy Aplikasi 3 — `mochi-finance-admin`
```bash
cd /var/www
sudo git clone https://github.com/bocildev/mochi-finance-admin.git mochi-finance-admin
cd mochi-finance-admin

cp .env.example .env
nano .env
```

**Konfigurasi Wajib di `.env` `mochi-finance-admin`:**
```ini
APP_NAME="Mochi Finance Admin"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://mochi-admin.domainanda.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mochi_finance_admin_db
DB_USERNAME=ecosystem_user
DB_PASSWORD=PasswordKuatAnda123!

# Hubungan ke IAM SSO Core Pusat
IAM_BASE_URL=https://auth.domainanda.com
IAM_CLIENT_ID=app_mochi_finance_admin
IAM_CLIENT_SECRET=whsec_rahasia_dari_iam_console
IAM_REDIRECT_URI=https://mochi-admin.domainanda.com/auth/callback
```

Jalankan build & optimasi:
```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache

sudo chown -R www-data:www-data /var/www/mochi-finance-admin/storage /var/www/mochi-finance-admin/bootstrap/cache
sudo chmod -R 775 /var/www/mochi-finance-admin/storage /var/www/mochi-finance-admin/bootstrap/cache
```

---

### Langkah 6: Konfigurasi Nginx & SSL Gratis (Certbot Let's Encrypt)

Buat file konfigurasi Nginx untuk masing-masing backend:
`/etc/nginx/sites-available/iam-sso-core.conf`:
```nginx
server {
    listen 80;
    server_name auth.domainanda.com;
    root /var/www/iam-sso-core/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

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

Buat juga konfigurasi serupa untuk `portfolio-admin` (`admin-porto.domainanda.com`) dan `mochi-finance-admin` (`mochi-admin.domainanda.com`).

Aktifkan konfigurasi:
```bash
sudo ln -s /etc/nginx/sites-available/iam-sso-core.conf /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/portfolio-admin.conf /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/mochi-finance-admin.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Pasang Sertifikat SSL Otomatis (HTTPS):
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d auth.domainanda.com -d admin-porto.domainanda.com -d mochi-admin.domainanda.com
```

---

### Langkah 7: Deploy Frontend Nuxt 3 (`portofolio-motion` & `management-keuangan`)

#### 🌟 Opsi Termudah & Terbaik: Deploy ke Vercel (Gratis & Cepat)
1. Buka [https://vercel.com](https://vercel.com) dan login dengan akun GitHub Anda.
2. Klik **Add New Project** -> Impor repositori `portofolio-motion`.
3. Tambahkan **Environment Variable** di Vercel:
   - `IAM_API_BASE`: `https://admin-porto.domainanda.com`
   - `IAM_API_TOKEN`: *(Isi jika Anda menggunakan token API backoffice)*
4. Klik **Deploy**. Selesai! Sambungkan custom domain Anda (`porto.domainanda.com`).
5. Ulangi hal yang sama untuk `management-keuangan` (Mochi Finance):
   - `IAM_BASE_URL`: `https://auth.domainanda.com`
   - `IAM_CLIENT_ID`: `app_mochi_finance`
   - Sambungkan domain `mochi.domainanda.com`.

#### Opsi Mandiri: Deploy Nuxt di VPS menggunakan PM2
Jika ingin semuanya di dalam VPS:
```bash
cd /var/www/portofolio-motion
npm install
npm run build

# Jalankan via PM2
pm2 start .output/server/index.mjs --name "portofolio-motion" --env PORT=3000
pm2 save
pm2 startup
```
Lalu buat Nginx reverse proxy dari `porto.domainanda.com` ke `http://127.0.0.1:3000`.

---

## 🔒 5. Checklist Wajib Sebelum Go-Live (SSO & Production)

Setelah seluruh domain aktif dan ber-HTTPS, login ke **IAM SSO Core Admin Panel** (`https://auth.domainanda.com/admin`) dan pastikan:

1. **Perbarui Daftar Redirect URI di Aplikasi Klien (`/admin/apps`)**:
   - Untuk `motion-portfolio`:
     - Allowed Origins: `https://porto.domainanda.com`
   - Untuk `portfolio-admin`:
     - Redirect URI: `https://admin-porto.domainanda.com/auth/callback`
     - Post Logout: `https://admin-porto.domainanda.com`
     - Allowed Origins: `https://admin-porto.domainanda.com`
   - Untuk `mochi-finance`:
     - Redirect URI: `https://mochi.domainanda.com/auth/callback`
     - Post Logout: `https://mochi.domainanda.com`
     - Allowed Origins: `https://mochi.domainanda.com`
   - Untuk `mochi-finance-admin`:
     - Redirect URI: `https://mochi-admin.domainanda.com/auth/callback`
     - Post Logout: `https://mochi-admin.domainanda.com`
     - Allowed Origins: `https://mochi-admin.domainanda.com`

2. **Cron Job Outbox Event & Scheduler**:
   Buka crontab VPS (`crontab -e`) dan tambahkan baris ini agar pengiriman webhook dan tugas terjadwal berjalan otomatis:
   ```cron
   * * * * * cd /var/www/iam-sso-core && php artisan schedule:run >> /dev/null 2>&1
   * * * * * cd /var/www/iam-sso-core && php artisan iam:outbox-work >> /dev/null 2>&1
   ```

3. **Backup Rutin Database**:
   Simpan script backup otomatis mingguan dengan perintah:
   ```bash
   mysqldump -u ecosystem_user -p iam_sso_core > /backup/iam_sso_core_$(date +\%F).sql
   mysqldump -u ecosystem_user -p portfolio_admin_db > /backup/portfolio_admin_$(date +\%F).sql
   mysqldump -u ecosystem_user -p mochi_finance_admin_db > /backup/mochi_finance_$(date +\%F).sql
   ```

---
> 💡 Panduan ini siap dijadikan SOP (Standar Operasional Prosedur) resmi saat Anda ingin meluncurkan seluruh karya hebat Anda ke publik!
