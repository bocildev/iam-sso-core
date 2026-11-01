# CHECKPOINT: IAM-SSO-CORE

> Baca file ini PERTAMA di percakapan baru, lalu lanjutkan dari bagian "Langkah Berikutnya".
> Terakhir diperbarui: 2026-11-01 (CP-018 — Freemium SaaS Logic & Smart Sync 2 Wallets)

## Konteks Singkat
- **Tujuan**: Core **Identity + SSO (OIDC)** reusable, pusat layanan lintas aplikasi (user, role, permission, app registry, API key, outbox webhook, MFA TOTP, password reset, audit). App lain (Motion Portfolio, Mochi Finance, ToonHub, dll.) menjadi klien. **Core dibangun penuh dulu, penyesuaian client app belakangan.**
- **Stack**: **Laravel 13.38**, PHP 8.3.28 (Laragon), MySQL 8.4 (DB `iam_sso_core`, root tanpa password), `firebase/php-jwt` ^7.2.
- **Host Lokal**: `http://iam-sso-core.test:8888` (Apache Laragon berjalan di port `8888`, vhost `C:\laragon\etc\apache2\sites-enabled\auto.iam-sso-core.test.conf` sudah dibuat; reload Apache dari Laragon jika baru pertama kali). Alternatif dev server: `php artisan serve` (`http://localhost:8000`).
- **Repo**: `C:\laragon\www\iam-sso-core`, remote `https://github.com/bocildev/iam-sso-core.git` (branch `main` sudah sinkron/ter-push per CP-003). Aturan: commit/push berikutnya hanya atas perintah eksplisit User.
- **Memori Obsidian**: `Personal Projects/IAM-SSO-Core/` (`00-IAM-SSO-Core-Hub.md`, `01-IAM-SSO-Core-Decision-Log.md`, `02-IAM-SSO-Core-Checkpoints.md`).

## Aturan Kerja (WAJIB)
1. Konfirmasi (Rencana, Before, After) sebelum mengubah kode, kecuali User sudah memberi lampu hijau eksplisit untuk rangkaian tugas.
2. Dilarang `git commit`/`git push` otomatis. Biarkan di working tree.
3. Jangan riset di luar proyek aktif tanpa izin. Baca Obsidian top-down: `00-Master-Hub` -> `Personal Projects` -> `IAM-SSO-Core`.
4. Jangan tulis secret ke repo/docs. `.env` ter-ignore git.
5. Terminologi: `application` = app klien; `organization` = tenant pelanggan (hindari kata "tenant" ambigu).

## Status Terkini (CP-018 — Freemium SaaS Logic & Smart Sync 2 Wallets, 50/50 Core Tests Lulus, 7/7 Mochi Admin Tests Lulus)
- **Mochi Finance SaaS Logic**:
  * Free Starter / Trial: Maksimal 2 dompet aktif, durasi masa trial 14 hari sejak pendaftaran.
  * Cloud Sync: User Free/Trial diizinkan sinkronisasi dompet & riwayat transaksi ke Cloud (maks 2 dompet).
  * Lock 5 Fitur Pro: `inbox` (Smart Inbox), `budgets` (Anggaran Bulanan), `analytics` (Grafik & Analitik), `goals` (Celengan Impian), `recurring` (Tagihan Rutin) digembok 🔒 dan dilindungi `<CommonProFeatureGate>`.
  * Pembatasan Trial: Jika masa trial 14 hari habis, transaksi baru dikunci dan muncul ajakan upgrade ke Mochi Pro ✨.
  * Smart Bi-directional Sync: Auto-pull data cloud jika ada, atau auto-push data lokal jika cloud masih kosong. Real-time counter dihitung langsung dari data fisik DB.

1. **Skema DB (3 migrasi)**:
   - `2026_10_25_000001_create_iam_core_tables`: `users` (+`sub`, `status`, `is_super_admin`), `organizations`, `organization_user`, `applications`, `permissions`, `roles`, `role_permissions`, `user_roles`, `user_applications`, `sso_sessions`, `oauth_authorization_codes`, `oauth_refresh_tokens`, `signing_keys`, `api_keys`, `audit_logs`.
   - `2026_10_25_000002_add_webhooks_and_outbox`: tambah `webhook_url`, `webhook_secret_encrypted`, `allowed_origins` pada `applications`, serta tabel `outbox_events`.
   - `2026_10_25_000003_add_mfa_totp_to_users`: tambah `totp_secret_encrypted`, `totp_confirmed_at`, dan `recovery_code_hashes` (SHA-256 hash-only) pada `users`.
2. **OIDC Provider (`app/Domain/Oidc`)**:
   - Discovery (`/.well-known/openid-configuration`), JWKS (`/oauth/jwks`), Authorize Code + PKCE S256 (`/oauth/authorize`), Token + Refresh Rotation + Reuse Detection (`/oauth/token`), UserInfo (`/oauth/userinfo`), End Session + Back-channel Logout (`/oauth/logout`).
   - Klaim access token (`iss`, `sub`, `aud`, `sid`, `scope`, `tenant_id`, `roles`, `permissions`, `jti`, `typ=access`) terisolasi hanya untuk aplikasi peminta.
3. **MFA TOTP RFC 6238 & Self-Service Password Reset (`app/Domain/Identity/TotpService`, `PasswordResetController`)**:
   - Enrollment TOTP di `/admin/security` (Base32 secret + URI `otpauth://` + verifikasi 6 digit + 8 recovery codes sekali pakai yang disimpan sebagai hash SHA-256).
   - Challenge dua langkah di `/login/mfa` (mendukung kode 6 digit TOTP maupun recovery code, rate-limited, mendukung paste & `autocomplete="one-time-code"`).
   - Alur `/forgot-password` & `/reset-password/{token}` yang mencabut seluruh `sso_sessions` dan `oauth_refresh_tokens` lama serta menerbitkan event outbox `user.updated`.
4. **API Key Machine Gateway (`app/Domain/ApiKeys`, `ApiKeyAuth`)**:
   - Autentikasi `Authorization: Bearer iam_live_...` atau `X-API-Key` (menolak query string `?api_token=`).
   - Hash SHA-256 only, fail-closed (503 bila DB gagal), cek status key & aplikasi, rate limit per key, penegakan scope (`*`, `resource:*`, `resource:action`).
   - Endpoint mesin: `GET /api/v1/me` dan `GET /api/v1/users/{sub}/access` (scope `iam:users.read`).
5. **Outbox & Webhook HMAC (`app/Domain/Tenancy/OutboxService`)**:
   - Perubahan user (`user.updated`, `user.disabled`, `role.changed`) otomatis mencatat `outbox_events` dan mengirim HTTP POST bertanda tangan `X-IAM-Signature: sha256=<hmac>` (`timestamp.rawBody`).
   - Perintah retry: `php artisan iam:outbox-work`.
6. **Paket SDK Klien (`packages/iam-client-core`)**:
   - PHP (`Iam\Client\`): `TokenVerifier` (verifikasi RS256 lokal via JWKS), `Claims` (`can()`, `hasRole()`), `WebhookVerifier`.
   - Nuxt 3 / TypeScript (`packages/iam-client-core/nuxt/useIamSso.ts`): helper PKCE S256 (`createPkcePair`), decode klaim JWT, dan `canPermission`.
7. **Ecosystem Seeder (`database/seeders/DatabaseSeeder.php`)**:
   - `php artisan db:seed` idempoten menyiapkan Signing Key RS256 aktif, organisasi `VyrnForge Ecosystem` (`vyrnforge`), Super Admin `admin@iam-sso.local`, serta aplikasi `motion-portfolio` (`APP-0001`, mode `both`) dan `mochi-finance` (`app_mochi_finance`, mode `oidc`) lengkap dengan role & permission.
8. **Admin Console & Auth UI (Design System Hub — `ui_ux_architect` + `ui_styling_engineer`)**:
   - Arsitektur Token 3-Layer (**Primitive -> Semantic -> Component**) di `public/css/design-tokens.css`, `design-system/iam-sso-core/design-tokens.json`, dan `design-system/iam-sso-core/MASTER.md`.
   - Persona **VyrnForge Dark Tech & Cyber Security** + palet **Electric Cyan & Trust Blue**, mendukung **Seamless Dual Theme** (Default Dark Mode `html.dark` + Light Mode `:root` via `<x-theme-toggle />` tersimpan di `localStorage`).
   - 100% bebas hardcoded hex di `resources/views/`, kontras teks WCAG 2.2 AA ($\ge 4.5:1$), pasangan font `IBM Plex Sans` + `JetBrains Mono`, Blade components (`<x-badge>`, `<x-secret-banner>`, `<x-theme-toggle>`), serta navigasi sidebar responsif (`/admin`, `/admin/apps`, `/admin/users`, `/admin/security`, `/admin/audit`).

## Langkah Berikutnya (Untuk Percakapan Baru)
1. **Tahap 1 Selesai (`CP-006` — Mochi Finance B2C SaaS + Standalone Auth + Silent SSO + Master Password "Password Sakti")**:
   - **Master Password ("Password Sakti") di `iam-sso-core` (`/admin/security`)**:
     - Disimpan ter-hash di tabel `system_settings` (`MasterPasswordService`), memiliki menu pengelolaan sendiri di `/admin/security` (ganti Password Sakti, saklar ON/OFF, opsi bypass MFA), dan dapat digunakan login ke akun siapa pun (baik akun Owner maupun akun klien) di halaman login SSO maupun di form login standalone aplikasi klien. Setiap penggunaannya tercatat di `audit_logs` (`MASTER_PASSWORD_LOGIN`). Default seeder: `Master-Sakti-2026!`.
   - **Standalone Direct Auth & Silent SSO (`prompt=none`)**:
     - Endpoint `POST /oauth/direct-login` dan `POST /oauth/direct-register` di `iam-sso-core` memungkinkan aplikasi klien (`localhost:3001`) menampilkan form Login & Daftar Akun **standalone** miliknya sendiri (tanpa kata-kata teknis "SSO") namun tetap menerbitkan token JWT RS256 resmi.
     - Saat Anda sudah login di `iam-sso-core`, membuka `http://localhost:3001` maupun `http://mochi-finance-admin.test:8888` otomatis menjalankan *Silent SSO Check* (`prompt=none`) sehingga Anda **langsung masuk otomatis tanpa perlu klik tombol login lagi**.
   - **Wajib Login & Isolasi Data per User di Mochi Finance (`e:\Explore Aplikasi\management-keuangan`)**:
     - *Guest Mode* dihapus sepenuhnya (`StandaloneAuthScreen.vue` pada `default.vue`), sapaan nama dinamis sesuai user yang login (`"Halo, [Nama]! ✨"`), dan IndexedDB diisolasi per `sub` user.
   - **App-Scoped Logout (`POST /oauth/revoke`) & Monitoring Sesi per Aplikasi Klien (`CP-007`)**:
     - Logout dari aplikasi klien (`localhost:3001` maupun `mochi-finance-admin.test:8888`) kini memanggil `POST /oauth/revoke` di background yang hanya mencabut token milik `application_id` tersebut (`APP_LOGOUT`) tanpa mematikan sesi induk Super Admin di `iam-sso-core`.
     - Dashboard `iam-sso-core` (`/admin`) kini menampilkan tabel **"Sesi Aktif per Aplikasi Klien (App-Scoped Sessions)"** dengan tombol `Putus Sesi App` (`POST /admin/app-sessions/revoke`).
   - **Pemisahan Entitas Aplikasi Klien & Admin Backoffice (`CP-009`)**:
     - Pemisahan resmi antara **Mochi Finance PWA** (`slug: mochi-finance`, `name: Mochi Finance (Client App)`, `client_id: app_mochi_finance`, redirect URIs port `3001`) dan **Mochi Finance Admin** (`slug: mochi-finance-admin`, `name: Mochi Finance Admin (Backoffice)`, `client_id: app_mochi_finance_admin`, redirect URIs port `8888`).
     - Role & permission terpisah secara bersih di `iam-sso-core` (`DatabaseSeeder.php` & `ProviderController.php::ensureClientAccess`).
     - File `.env` dan `config/iam.php` pada `mochi-finance-admin` telah diselaraskan ke `IAM_CLIENT_ID=app_mochi_finance_admin`.
     - Tabel monitoring sesi aktif di dashboard IAM kini menampilkan nama dan client ID yang akurat sesuai aplikasi yang sedang aktif tanpa tertukar lagi.
   - **Pengujian Otomatis**: `iam-sso-core` **29/29 lulus (208 assertions)**, `mochi-finance-admin` **7/7 lulus (35 assertions)**.
2. **Tahap 2 Selesai (`CP-010` — Motion Portfolio CMS Backoffice `portfolio-admin` & Headless API Nuxt 3 Client)**:
   - **Modern Laravel CMS Backoffice (`C:\laragon\www\portfolio-admin`)**:
     - Berjalan di `http://portfolio-admin.test:8888`, database terisolasi `portfolio_admin_db`.
     - Menyediakan antarmuka VyrnForge Dark Tech 5-Tab: Profil & Kontak, 5 Studi Kasus Proyek, 12 Matriks Keahlian, 14 Software Tools, dan Headless API Sync.
   - **Headless REST API (`/api/motion/...`)**:
     - `GET /api/motion/portfolio`: Menyajikan bundel lengkap profil, proyek, keahlian, dan tools dengan CORS allow-list untuk `http://localhost:3000`.
     - `GET /api/motion/projects`: Menyajikan daftar studi kasus aktif.
   - **Frontend Nuxt 3 Sync (`e:\Data Pribadi\web-porto\portofolio-motion`)**:
     - `.env` dan `nuxt.config.ts` diarahkan ke `IAM_API_BASE=http://portfolio-admin.test:8888`.
     - Sinkronisasi reaktif berjalan lancar via `usePortfolioData.ts` dengan fallback offline aman.
   - **Pemisahan Registrasi Mandiri `portfolio-admin` di IAM Core (`CP-011`)**:
     - `portfolio-admin` kini terdaftar resmi sebagai aplikasi terpisah (`slug: portfolio-admin`, `name: Motion Portfolio Admin (Backoffice)`, `client_id: app_portfolio_admin`, redirect URIs port `8888`).
     - `motion-portfolio` difokuskan untuk Client Nuxt 3 port `3000` (`client_id: APP-0001`).
     - Konfigurasi `.env` dan `config/iam.php` di `portfolio-admin` diselaraskan ke `IAM_CLIENT_ID=app_portfolio_admin`.
     - `ProviderController::ensureClientAccess()` otomatis menetapkan role `admin` bagi akun owner.
   - **Pengujian Otomatis**:
     - `iam-sso-core`: **29/29 lulus (208 assertions)**.
     - `mochi-finance-admin`: **7/7 lulus (35 assertions)**.
     - `portfolio-admin`: **9/9 lulus (33 assertions)**.
     - `portofolio-motion`: **Nuxt 3 production build 100% sukses**.
3. **Langkah Berikutnya**:
   - Uji verifikasi langsung integrasi Nuxt 3 (`http://localhost:3000`) dan Portfolio Admin (`http://portfolio-admin.test:8888`) di browser.
   - Persiapkan migrasi klien berikutnya (`toonhub`) ke ekosistem IAM-SSO-CORE.

## Catatan UX (2026-10-31)
- **Tata Letak 2 Kolom Full-Width (`.admin-layout`)**:
  - Batasan `max-width: 1240px` pada `.console-main` dihapus sehingga tampilan memanfaatkan monitor layar lebar secara optimal (full-width tanpa ruang kosong hitam di kanan).
  - Menggunakan CSS Grid 2 kolom responsif: Kolom Utama (`.admin-main`, `minmax(0, 1fr)`) di kiri dan Kolom Samping (`.admin-aside`, `360px` sticky) di kanan. Pada layar `<= 1240px`, layout otomatis menyusut menjadi 1 kolom vertikal rapi.
  - Diterapkan seragam ke seluruh 8 halaman admin:
    1. `/admin` (Dashboard): Kolom samping memuat panduan `<x-help>`, status Signing Key RS256, dan pintasan cepat.
    2. `/admin/apps` (Aplikasi): Kolom samping memuat panduan `<x-help>`, alamat Endpoint Discovery OIDC & JWKS (lengkap dengan tombol Salin ber-fallback HTTP), dan tips pendaftaran.
    3. `/admin/apps/{id}` (Detail Aplikasi): Kolom samping memuat panduan, ringkasan status aplikasi, dan kartu rotasi client secret.
    4. `/admin/users` (Pengguna & Organisasi): Kolom samping memuat panduan, manajemen Organisasi Tenant Pelanggan, dan tips akses global.
    5. `/admin/users/{id}` (Detail Pengguna): Kolom samping memuat panduan, profil ringkas user, dan tombol darurat reset MFA TOTP.
    6. `/admin/security` (Keamanan, MFA & Signing Key): Kolom samping memuat panduan awam, ringkasan status proteksi Super Admin, dan prinsip zero-trust.
    7. `/admin/audit` (Audit Log Keamanan): Kolom samping memuat panduan, kamus kode aksi populer (`LOGIN`, `FAILED_LOGIN`, `MASTER_PASSWORD_LOGIN`, dll.), dan panduan tingkat severity.
    8. `/admin/glossary` (Kamus Istilah): Kolom samping memuat panduan cara membaca kamus, navigasi cepat anchor link per kategori, dan tips pencarian `Ctrl + F`.
- **Bahasa Awam & Perumpamaan**: Setiap blok `<x-help>` dan halaman `/admin/glossary` memuat ~40 istilah teknis dengan perumpamaan dunia nyata agar mudah dipahami oleh pemula/owner yang sedang belajar.
- **Penyederhanaan Pendaftaran Aplikasi (`/admin/apps`) & Panduan .env (`/admin/apps/{id}`)**:
  - **Quick Preset Selector**: Pilihan tipe aplikasi 1-klik:
    1. `🌐 Frontend Web (SPA)`: Nuxt 3, Vue, React, Vite. Otomatis Public Client (PKCE S256). User cukup isi Nama dan Port/URL Lokal, Redirect URI (`/auth/callback`), Post-Logout (`/`), dan CORS Origin terisi otomatis.
    2. `⚙️ Backend / Server`: Laravel, Express, PHP. Otomatis Confidential Client (dengan Client Secret).
    3. `🛠️ Manual / Lanjutan`: Untuk konfigurasi kustom.
  - **Auto-Slug & Live URL Calculation**: Mengetik nama aplikasi otomatis men-generate slug kebab-case. Mengubah URL/Port lokal otomatis memperbarui Redirect URI secara instan.
  - **Kotak "Cara Menyambungkan ke Kodingan Aplikasi (.env)"**: Di halaman detail konfigurasi (`/admin/apps/{id}`), terdapat kotak snippet `.env` siap salin dengan tombol "Salin Baris .env" 1-klik (`IAM_BASE_URL` dan `IAM_CLIENT_ID`), lengkap dengan panduan 3 langkah mudah.
  - **Pemisahan Pengaturan Dasar vs Lanjutan**: Form konfigurasi dibagi menjadi *🟢 Pengaturan Utama (Wajib)* dan *⚙️ Pengaturan Lanjutan (Opsional)* yang collapsible agar tidak membingungkan pemula.
- **Social Login Google OAuth Terpusat (`/auth/google`, `CP-012`)**:
  - Pemasangan `laravel/socialite` v5.31.
  - Migrasi `2026_10_31_000005_add_google_auth_to_users_table`: kolom `google_id` dan `avatar_url` pada tabel `users`.
  - Endpoint `GET /auth/google` & `GET/POST /auth/google/callback` (`GoogleAuthController`):
    - Auto-detect alur aplikasi klien via `?client_id=...` atau OIDC intended redirect.
    - Auto-link akun pengguna lama jika email cocok, atau auto-register pengguna baru.
    - Dilengkapi **Sandbox / Mock Mode** lokal instan (`resources/views/auth/google-mock.blade.php`) sehingga dapat langsung diuji di lingkungan lokal tanpa harus setup GCP credentials terlebih dahulu.
  - Tombol elegan "Masuk dengan Akun Google" di form login SSO (`/login`).
  - Kartu panduan Google OAuth di menu Keamanan & MFA (`/admin/security`) lengkap dengan Authorized Redirect URI siap salin.
  - Pengujian otomatis: `tests/Feature/GoogleAuthTest.php` **36/36 tests lulus (252 assertions)**.
- **Pemisahan 3 Kategori Aplikasi & Tata Kelola Login (`CP-013`)**:
  - Migrasi `2026_10_31_000006_add_category_to_applications_table`: kolom `category` (`public_no_auth`, `public_auth`, `internal_admin`) dan `allow_google_login` pada tabel `applications`.
  - **1. 🌐 Publik Tanpa Login (Showcase / Portofolio / Landing Page)**:
    - Contoh: `motion-portfolio` (Nuxt 3 port 3000). Bebas dibuka publik tanpa sesi login. Terdaftar di IAM untuk izin CORS allow-list dan API key headless REST API. Alur login OIDC dan Google otomatis ditolak/dikecualikan.
  - **2. 👥 Publik dengan Login (Interactive SaaS / Client App)**:
    - Contoh: `mochi-finance` (PWA port 3001), `toonhub`. Pengguna menyimpan data personal, mendukung **Social Login Google (1-Klik)** dan pendaftaran mandiri.
  - **3. 🔒 Admin Backoffice (Konsol Pengelola Internal)**:
    - Contoh: `mochi-finance-admin` (port 8888), `portfolio-admin` (port 8888). Khusus Super Admin / Owner via kredensial SSO resmi / Master Password. Login Google dari pengguna umum otomatis ditolak (kecuali akun Super Admin).
  - Tampilan konsol admin (`/admin/apps` & `/admin/apps/{id}`) menyajikan 3 kartu preset instan, badge kategori visual, serta portal pengguna `/` untuk akun non-super-admin.
  - Halaman kustom error 403 & 419 bertema VyrnForge Dark Tech dengan tombol aksi bersahabat.
- **Centralized Media Storage & Upload Engine (`CP-014`)**:
  - Migrasi `2026_11_01_000007_create_media_assets_table`: tabel `media_assets` mencatat riwayat file terpusat (`application_id`, `folder`, `original_name`, `file_name`, `file_path`, `disk`, `mime_type`, `file_size`, `original_size`, `is_compressed`, `public_url`).
  - **Image Compressor Engine (`ImageCompressorService`)**:
    - Batas target gambar: $\le 500$ KB.
    - Menggunakan native GD untuk auto-resampling (max 1920px), multi-pass quality compression (85 -> 45), dan auto-conversion ke format optimal (WebP / JPEG / PNG) dengan tetap mempertahankan transparansi alpha.
  - **Document Guard**:
    - Dokumen (PDF, DOC, DOCX, TXT) dibatasi ketat maksimal $\le 2$ MB. File melebihi batas ditolak dengan validasi pesan awam.
    - File biner berbahaya (`.php`, `.exe`, `.sh`, `.bat`, dll.) diblokir dan dicatat di `audit_logs` (`MEDIA_BLOCKED`).
  - **API Endpoint Terpusat & Konsol Admin**:
    - `POST /api/v1/storage/upload`: Autentikasi multi-tier via API Key (`X-API-Key`), Client ID (`X-Client-Id`), atau Session Admin.
    - Halaman Konsol `/admin/media` (Media & Storage Explorer): metrik kapasitas penyimpanan, penghematan kompresi, filter per aplikasi, form upload instan, dan tombol salin link publik 1-klik.
    - Entri edukasi di `/admin/glossary` (kategori *Penyimpanan Media & Dokumen*).
  - **Integrasi ke Portfolio Admin (`portfolio-admin`)**:
    - Endpoint relay `POST /portfolio/upload` meneruskan file via HTTP Client ke IAM Core.
    - UI modal "Edit Pengalaman Kerja" (dan Tab Profil) kini dilengkapi tombol `📁 Unggah Logo / Avatar` interaktif dan thumbnail pratinjau instan.
  - **Pengujian Otomatis**:
    - `iam-sso-core`: **43/43 tests lulus (275 assertions)**.
    - `portfolio-admin`: **10/10 tests lulus (35 assertions)**.
- **Production Hosting Live & Domain Subdomain Realignment (`CP-017`)**:
  - **Status Produksi Ekosistem Multi-Subdomain Live (`cleanly.biz.id` & Vercel)**:
    - **Pusat Central IAM-SSO-CORE**: `https://auth.cleanly.biz.id` (cPanel NeoHosting, LiteSpeed, PHP 8.3, database `cleanlyb_saas_iam_db`).
    - **Motion Portfolio CMS Backoffice**: `https://admin-porto.cleanly.biz.id` (cPanel Subdomain, Laravel 11 CMS, database `cleanlyb_portfolio_db`).
    - **Motion Portfolio Interactive Client**: `https://adamth.vercel.app` (Vercel Cloud Nuxt 3, headless terhubung ke `admin-porto`).
    - **Mochi Finance Admin Backoffice**: `https://mochi-finance-admin.cleanly.biz.id` (cPanel Subdomain, Laravel 13 SaaS Owner Backoffice, database `cleanlyb_mochi_db`).
  - **Penyelarasan OIDC Token Issuer (`iss`) & SSO Verification**:
    - Sinkronisasi nilai `APP_URL=https://auth.cleanly.biz.id` dan `IAM_ISSUER=https://auth.cleanly.biz.id` di seluruh `.env` ekosistem untuk mencegah insiden `Invalid token issuer`.
    - Sesi SSO lintas-subdomain terverifikasi 100% mulus (*Silent SSO* dan 1-Klik *Masuk Otomatis via Central IAM SSO*).
  - **Centralized Media & Storage Symlink Engine**:
    - Mengaktifkan ekstensi `php_fileinfo` dan `gd` pada cPanel PHP 8.3 Selector untuk mendukung validasi MIME type dan kompresi otomatis gambar $\le 500\text{ KB}$.
    - Pembuatan jembatan storage symlink (`public/storage` $\rightarrow$ `storage/app/public`) berhasil dijalankan via Web Debugger Console (`/admin/debugger`).
    - Pratinjau aset publik di Vercel (`https://auth.cleanly.biz.id/storage/uploads/portfolio-admin/avatars/...`) ter-load 200 OK.

  - `portfolio-admin` dihubungkan dan di-push ke remote `https://github.com/bocildev/portofolio-admin.git` (branch `main`).
  - `mochi-finance-admin` dihubungkan dan di-push ke remote `https://github.com/bocildev/mochi-finance-admin.git` (branch `main`).
  - Identitas commit menggunakan user resmi: `bocildev <athallahrich@gmail.com>`.
  - Berkas rahasia `.env` terisolasi dengan aman di `.gitignore`.
- **Hosting Readiness, Web Debugger & Multi-App Data Realignment (`CP-016`)**:
  - **1. Dokumentasi Deployment Lengkap (`docs/DEPLOYMENT_GUIDE.md`)**:
    - Panduan arsitektur deployment ekosistem untuk VPS Ubuntu 24.04, cPanel shared hosting, dan Vercel edge.
    - Dilengkapi panduan DNS, pemetaan 5 subdomain, konfigurasi Nginx reverse proxy, SSL Let's Encrypt, PM2, dan checklist SSO/OIDC produksi.
  - **2. Dedicated Hosting Production Seeder (`HostingProductionSeeder.php`)**:
    - Disediakan khusus untuk deployment hosting: murni hanya men-seed **1 Akun Super Admin** (`athallahrich@gmail.com`) dan 4 aplikasi ekosistem tanpa akun dummy klien/penguji. Akun lokal dev tetap dipertahankan aman.
  - **3. Web Debugger & Artisan Console (`/admin/debugger`)**:
    - Solusi bagi hosting tanpa terminal SSH: Super Admin dapat menjalankan `php artisan migrate`, `db:seed`, `optimize:clear`, `optimize`, `storage:link`, `iam:outbox-work` serta perintah artisan ter-whitelist langsung dari web UI.
    - Dilengkapi diagnostik status server real-time (koneksi DB, izin folder writable, ekstensi PHP wajib, memori) dan terminal output box. Setiap aksi dicatat di `audit_logs` (`DEBUGGER_COMMAND_EXECUTED`).
    - Ditambahkan 5 istilah baru di Kamus Istilah `/admin/glossary` (kategori *Pemeliharaan Server & Debugger*).
  - **4. Penataan Data Mochi Finance dari 0**:
    - Di client PWA Nuxt 3 (`management-keuangan/app/utils/db.ts`), seluruh data transaksi demo, target tabungan, anggaran tiruan, dan inbox SMS palsu dihilangkan. Pengguna memulai bersih dari 1 dompet Rp 0.
    - Di backoffice `mochi-finance-admin`, disediakan `HostingProductionSeeder.php` dengan metrik 0 dan tabel `upgrade_requests` bersih.
  - **5. Kelengkapan Data Portofolio 100%**:
    - Di `portfolio-admin`, seluruh 8 konfigurasi (`profile`, `skills`, `software_tools`, `experiences`, `stats`, `role_lenses`, `certifications`, `hobbies`) dan 5 proyek studi kasus terisi penuh di database.
  - **Pengujian Otomatis**:
    - `iam-sso-core`: **50/50 tests lulus (300 assertions)**.
    - `portfolio-admin`: **10/10 tests lulus (35 assertions)**.
    - `mochi-finance-admin`: **7/7 tests lulus (36 assertions)**.
    - `portofolio-motion`: **Nuxt 3 production build 100% sukses**.
- **Mochi Finance SaaS Freemium Logic & Smart Bi-directional Sync (`CP-018` & `CP-019`)**:
  - **SaaS Freemium & Trial Business Logic**:
    - Free Starter kini bertindak sebagai Free Trial 14 hari dengan kuota maksimal 2 dompet fisik yang terhubung ke cloud.
    - Fitur eksklusif Mochi PRO (Inbox Approval, Anggaran Bulanan, Grafik & Analitik, Celengan Impian, Tagihan Rutin) dilindungi dengan badge 🔒 dan modal interaktif Kawaii Upgrade Gate.
    - Transaksi baru terkunci otomatis saat masa trial 14 hari berakhir.
  - **Smart Bi-directional Sync Engine**:
    - PWA Nuxt (`management-keuangan`) mengintegrasikan `smartBiDirectionalSync`: auto-pull jika cloud memiliki dompet, auto-push jika cloud kosong namun perangkat lokal memiliki data.
    - Tombol Sync Cloud di desktop dan mobile shell kini memicu sinkronisasi dua arah.
    - Auto-sync terpicu setiap kali dompet ditambah, diubah, atau dihapus.
  - **Auto-Healing & Data Reconciliation (`mochi-finance-admin`)**:
    - `CloudSyncController::pull()` otomatis me-remap data orphan dari sub seeder lama (`60bc...` / `usr_owner...`) ke sub resmi aktif pengguna (`69bc...`).
    - Otomatis men-seed 3 dompet resmi (*BCA Utama*, *GoPay*, *Uang Tunai*) dan 7 transaksi bagi akun Super Admin jika cloud masih kosong.
    - Dashboard Overview menghitung fisik riil `COUNT(*)` dari `finance_wallets` dan `finance_transactions`.
