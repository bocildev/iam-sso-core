# 🛡️ IAM-SSO-CORE
> **Enterprise Identity & Access Management (IAM), Role-Based Access Control (RBAC), & OpenID Connect 1.0 (OIDC) Provider**  
> Pusat Layanan Identitas, Otentikasi, dan Otorisasi Terpadu untuk Seluruh Aplikasi Klien.  
> *Antarmuka Konsol Admin dirancang menggunakan **VyrnForge Design System** (Cyber Dark Tech).*

[![Laravel 13](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![OpenID Connect](https://img.shields.io/badge/OIDC-1.0%20Compliant-0052CC?style=flat&logo=openid&logoColor=white)](https://openid.net/connect/)
[![OAuth 2.0](https://img.shields.io/badge/OAuth-2.0%20%2B%20PKCE-3B82F6?style=flat&logo=auth0&logoColor=white)](https://oauth.net/2/)
[![Tests](https://img.shields.io/badge/Tests-29%2F29%20Passed-10b981?style=flat&logo=checkmarx&logoColor=white)](tests/)
[![Design System](https://img.shields.io/badge/UI-VyrnForge%20Dark%20Tech-06b6d4?style=flat)](design-system/)

---

## 📚 1. Konsep Dasar & Subsistem Keamanan

IAM-SSO-CORE bukan sekadar server login biasa, melainkan penggabungan **9 subsistem dan standar keamanan industri** yang saling terintegrasi:

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                          IAM-SSO-CORE ENGINE                                         │
├──────────────────────────────────────┬──────────────────────────────────┬────────────────────────────┤
│ 1. Identity & Lifecycle (IAM)        │ 4. Authorization Framework (OIDC)│ 7. Server-to-Server (M2M)  │
│ 2. Single Sign-On Orchestrator (SSO) │ 5. Public Client Security (PKCE) │ 8. Event Outbox & Webhooks │
│ 3. Granular Access Control (RBAC)    │ 6. Multi-Factor Auth (MFA TOTP)  │ 9. Immutable Audit Trail   │
└──────────────────────────────────────┴──────────────────────────────────┴────────────────────────────┘
```

---

### 1. IAM (Identity and Access Management)
- **Definisi**: Kerangka kerja terpusat untuk mengelola identitas digital (*Identity*) dan hak akses pengguna (*Access Management*).
- **Otentikasi (*AuthN*)**: Memvalidasi *"Siapa Anda?"* melalui verifikasi email & password, TOTP 2FA, atau Master Password.
- **Otorisasi (*AuthZ*)**: Memvalidasi *"Apa saja hak Anda?"* melalui pemetaan role dan izin (permissions).
- **Di mana & Mengapa Digunakan?**: Digunakan sebagai **Single Source of Truth** seluruh pengguna. Tanpa IAM, setiap aplikasi klien (*Mochi Finance*, *Motion Portfolio*, dll.) harus membuat tabel user, logika registrasi, dan modul reset password sendiri-sendiri sehingga data pengguna terpecah-pecah (*siloed data*) dan rawan celah keamanan.

---

### 2. SSO (Single Sign-On)
- **Definisi**: Mekanisme yang memungkinkan pengguna untuk **cukup masuk (login) satu kali**, lalu secara otomatis dapat mengakses seluruh aplikasi di dalam ekosistem tanpa perlu login berulang-ulang.
- **Sesi Induk (*Parent SSO Session*)**: Dikelola di tabel `sso_sessions` dengan cookie terenkripsi pada domain pusat IAM.
- **Silent SSO (`prompt=none`)**: Saat membuka aplikasi klien, aplikasi langsung melakukan pengecekan ke IAM di background. Jika sesi induk aktif, token diterbitkan dan pengguna **langsung masuk seketika**.
- **App-Scoped Logout vs Global SSO Logout**:
  - *App-Scoped Logout (`/oauth/revoke`)*: Keluar dari satu aplikasi saja tanpa mematikan sesi induk IAM di aplikasi lain.
  - *Global SSO Logout (`/logout`)*: Tombol keluar di IAM yang memutus sesi induk beserta seluruh token di semua aplikasi klien secara serentak (*Real-time Revocation*).

---

### 3. OIDC (OpenID Connect 1.0)
- **Definisi**: Protokol standar global untuk lapisan identitas (*Identity Layer*) yang dibangun di atas OAuth 2.0.
- **Perbedaan dengan OAuth 2.0 Biasa**:
  - *OAuth 2.0 murni*: Hanya mengatur otorisasi akses API (menghasilkan `access_token`).
  - *OIDC 1.0*: Menambahkan otentikasi identitas dengan menerbitkan **`id_token`** (KTP digital pengguna berformat JWT RS256 yang berisi klaim `sub`, `name`, `email`, `roles`, dan `permissions`).
- **Endpoint Standar OIDC**:
  - Discovery Document: `/.well-known/openid-configuration`
  - Kunci Publik JWKS: `/oauth/jwks`
  - Alur Masuk Browser: `/oauth/authorize`
  - Penukaran Kode Token: `/oauth/token`
  - Profil Pengguna: `/oauth/userinfo`
  - Akhiri Sesi: `/oauth/logout`

---

### 4. OAuth 2.0 & Token Lifecycle *(RFC 6749)*
- Mengelola izin aplikasi klien terdaftar (*Client ID & Secret*), masa berlaku token akses (*Access Token TTL* 15 menit), serta *Refresh Token*.
- **Refresh Token Rotation & Reuse Detection**: Setiap kali refresh token digunakan, sistem menerbitkan refresh token baru dan mematikan yang lama. Jika token lama dicoba kembali, sistem mendeteksi pembajakan (*token theft*) dan langsung mencabut seluruh rantai sesi tersebut.

---

### 5. PKCE (Proof Key for Code Exchange - RFC 7636)
- **Definisi**: Mekanisme kriptografi penangkal intersepsi kode otorisasi (*Authorization Code Interception Attack*).
- **Di mana Digunakan?**: Wajib untuk aplikasi web publik (*Single Page Application* seperti Nuxt/Vue atau Mobile App) yang tidak dapat menyimpan *client secret* secara rahasia di browser.
- **Cara Kerja**: Aplikasi klien membuat string acak rahasia (*Code Verifier*) dan mengirimkan versi hash SHA-256 (*Code Challenge*) ke IAM. Saat menukar kode dengan token, klien membuktikan kepemilikan dengan mengirimkan *Code Verifier* asli.

---

### 6. RBAC (Role-Based Access Control) & Permissions Granular
- Struktur otorisasi berbasis peran hierarkis yang terisolasi per aplikasi klien.
- Contoh di Mochi Finance:
  - Role `admin`: memiliki permission `['finance.admin', 'finance.basic', 'finance.sync', 'finance.smart_inbox', 'finance.analytics', 'finance.export', 'finance.unlimited']`.
  - Role `pro_user`: memiliki izin fitur pro tanpa hak akses backoffice admin.
  - Role `free_user`: hanya memiliki izin `finance.basic`.
- Mendukung evaluasi izin bertingkat (*wildcard matching* seperti `*` atau `finance.*`).

---

### 7. MFA / 2FA via TOTP *(RFC 6238)*
- **Time-Based One-Time Password**: Kode otentikasi 6 digit yang dihasilkan oleh aplikasi seperti **Google Authenticator** atau **Microsoft Authenticator** berdasarkan waktu dan kunci rahasia Base32 yang terenkripsi AES-256.
- **Single-Use Recovery Codes**: Disertai 8 kode pemulihan sekali pakai yang disimpan dalam bentuk *hash SHA-256 only* untuk keadaan darurat jika perangkat authenticator hilang.

---

### 8. Asymmetric Cryptography & JWKS *(RFC 7517)*
- **Kriptografi Asimetris RSA-2048**: Server IAM menandatangani JWT menggunakan *Private Key* (disimpan aman di server IAM).
- **JSON Web Key Set (JWKS)**: Kunci publik dipublikasikan secara terbuka di endpoint `/oauth/jwks`. Aplikasi klien dapat memvalidasi keaslian tanda tangan token JWT secara lokal tanpa perlu membagikan kunci rahasia (*shared secret*) yang rawan bocor.
- Dilengkapi fitur **Rotasi Kunci Penandatanganan** nir-downtime dari konsol admin.

---

### 9. Multi-Tenancy (Organizations)
- Pengelompokan akun ke dalam entitas organisasi/perusahaan penyewa (*tenant*) dengan atribut `tenant_id`.
- Memungkinkan penerapan arsitektur B2B SaaS di mana satu akun pengguna dapat memiliki izin yang berbeda pada organisasi yang berbeda.

---

### 10. M2M (Machine-to-Machine) API Key Gateway
- Autentikasi server-to-server nir-layar (*headless*) untuk backend pihak ketiga, daemon, worker, atau cron job.
- Menggunakan API Key ber-prefix `iam_live_...` dengan *SHA-256 hash-only*, rate-limiting per-key, dan penegakan *scope* yang ketat (*fail-closed*).

---

### 11. Transactional Outbox Pattern & Webhook HMAC
- **Event-Driven Data Synchronization**: Saat data profil atau role user diperbarui di IAM, sistem tidak langsung melakukan panggilan HTTP rapuh di tengah transaksi, melainkan menyimpannya ke tabel `outbox_events`.
- Worker outbox (`php artisan iam:outbox-work`) mengirimkan event (`user.updated`, `user.disabled`, `role.changed`) ke webhook aplikasi klien dengan tanda tangan digital `X-IAM-Signature: sha256=<hmac>` untuk menjamin integritas data dan mencegah pemalsuan payload.

---

### 12. Security Audit Logging (Audit Trail)
- Pencatatan seluruh kejadian keamanan penting secara permanen (*immutable*): `LOGIN`, `FAILED_LOGIN`, deteksi `BRUTE_FORCE`, `TOKEN_ISSUED`, `APP_LOGOUT`, hingga `MASTER_PASSWORD_LOGIN` lengkap dengan aktor, target, alamat IP, dan User-Agent.

---

## 🎨 2. Apa itu VyrnForge? (Klarifikasi Desain)

> [!NOTE]  
> **VyrnForge** bukanlah nama ekosistem backend atau cloud, melainkan **Design System & Panduan UI/UX** yang digunakan pada antarmuka Konsol Administrasi IAM-SSO-CORE.

- **VyrnForge Design System**: Standar arsitektur token CSS 3-Lapis (*Primitive -> Semantic -> Component*) yang dirancang khusus untuk tema **Cyber Security & Dark Tech**.
- **Karakter Visual**: Menggunakan latar belakang kanvas *Obsidian Dark* (`#0a0e1a`), surface *Slate Navy* (`#131b2e`), garis batas presisi (`#1e293b`), serta aksen warna fungsional:
  - *Trust Cyan* (`#06b6d4`): Brand, navigasi, dan tombol aksi utama.
  - *Success Emerald* (`#10b981`): Status aktif, sesi valid, dan badge token OIDC.
  - *Warning Amber* (`#f59e0b`): Notifikasi sensitif & Master Password alert.
  - *Danger Rose* (`#f43f5e`): Pencabutan token & penangguhan user.
- **Dual-Theme Support**: Dilengkapi toggle *Dark Mode* dan *Light Mode* yang tersimpan di `localStorage` tanpa ketergantungan utility framework luar.

---

## 🏛️ 3. Diagram Arsitektur Aplikasi Klien

```
                                      ┌─────────────────────────────────────────┐
                                      │              IAM-SSO-CORE               │
                                      │      http://iam-sso-core.test:8888      │
                                      │ (Identity · OIDC 1.0 · RBAC · JWKS Keys)│
                                      └────────────────────┬────────────────────┘
                                                           │
                         ┌─────────────────────────────────┼────────────────────────────────┐
                         │ OIDC Code + PKCE / Silent SSO   │ API Mesin / API Key M2M        │ Webhook HMAC
                         ▼                                 ▼                                ▼
           ┌───────────────────────────┐     ┌───────────────────────────┐    ┌───────────────────────────┐
           │     Mochi Finance PWA     │     │    Mochi Finance Admin    │    │      Motion Portfolio     │
           │   http://localhost:3001   │     │mochi-finance-admin.test   │    │   http://localhost:3000   │
           │  (Nuxt 4 SPA · Dexie IDB) │     │  (Laravel 13 Backoffice)  │    │      (Nuxt 3 · GSAP)      │
           └───────────────────────────┘     └───────────────────────────┘    └───────────────────────────┘
```

---

## 🔌 4. Kontrak Endpoint API & OIDC

| Endpoint | Method | Protokol / Fungsi | Tipe Autentikasi |
| :--- | :---: | :--- | :--- |
| `/.well-known/openid-configuration` | `GET` | Discovery OIDC Provider Metadata | Publik |
| `/oauth/jwks` | `GET` | JSON Web Key Set (Public Key RS256) | Publik (Cacheable) |
| `/oauth/authorize` | `GET` | OIDC Authorization Code Flow + PKCE S256 | Sesi Browser IAM |
| `/oauth/token` | `POST` | Penukaran Authorization Code & Refresh Token | Client Credentials / PKCE |
| `/oauth/userinfo` | `GET/POST`| Verifikasi klaim identitas profil pengguna | `Bearer <access_token>` |
| `/oauth/direct-login` | `POST` | Direct Login standalone untuk aplikasi klien | Client ID + Email/Password |
| `/oauth/direct-register`| `POST` | Direct Register mandiri dari aplikasi klien | Client ID + Data Registrasi |
| `/oauth/revoke` | `POST` | App-Scoped Logout (hanya memutus token aplikasi)| Client ID + Refresh Token |
| `/oauth/logout` | `GET/POST`| End-Session Global Logout OIDC | ID Token Hint / Sesi |
| `/api/v1/me` | `GET` | Identifikasi data aplikasi klien pemanggil | `X-API-Key: iam_live_...` |
| `/api/v1/users/{sub}/access`| `GET` | Pengecekan role & izin user secara M2M | `X-API-Key (iam:users.read)`|

---

## 🚀 5. Panduan Instalasi Lokal

### Prasyarat
- **PHP**: `^8.3` (ekstensi `openssl`, `pdo_mysql`, `mbstring`, `curl` aktif).
- **Composer**: `^2.x`
- **Database**: MySQL `^8.4` (Database: `iam_sso_core`).
- **Web Server**: Apache Laragon (Port `8888`) atau `php artisan serve`.

### Langkah Setup

1. **Clone & Masuk ke Direktori Proyek**:
   ```bash
   cd C:\laragon\www\iam-sso-core
   ```

2. **Instal Dependensi Composer**:
   ```bash
   composer install
   ```

3. **Konfigurasi Environment**:
   Salin `.env.example` ke `.env` dan sesuaikan kredensial:
   ```env
   APP_NAME="IAM-SSO-CORE"
   APP_URL=http://iam-sso-core.test:8888

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=iam_sso_core
   DB_USERNAME=root
   DB_PASSWORD=

   IAM_ISSUER=http://iam-sso-core.test:8888
   IAM_ADMIN_PASSWORD=AdminIamSso!2026
   IAM_MASTER_PASSWORD=Master-Sakti-2026!
   ```

4. **Generate Application Key**:
   ```bash
   php artisan key:generate
   ```

5. **Jalankan Migrasi Database & Seeder**:
   ```bash
   php artisan migrate --seed
   ```

---

## 🔑 6. Kredensial Bawaan Seeder

| Pengguna / Fitur | Alamat Email | Password Default | Hak Akses |
| :--- | :--- | :--- | :--- |
| **Super Admin IAM** | `admin@iam-sso.local` | `AdminIamSso!2026` | Super Admin IAM (Akses Penuh Konsol) |
| **Owner Platform** | `athallahrich@gmail.com` | Sesuai saat register | Super Admin IAM & Admin Mochi Finance |
| **Contoh Klien Pro** | `nadia.putri@gmail.com` | `ClientMochi!2026` | `pro_user` (Mochi Pro Member ✨) |
| **Contoh Klien Free**| `budi.santoso@gmail.com` | `ClientMochi!2026` | `free_user` (Mochi Free Starter) |
| **🔑 Password Sakti**| *Semua Email di Atas* | **`Master-Sakti-2026!`** | *Bypass login darurat untuk semua akun* |

---

## 🧪 7. Pengujian Otomatis (Automated Testing)

Seluruh subsistem (OIDC Code Flow, PKCE S256, Refresh Token Rotation, Master Password, App-Scoped Logout, MFA TOTP, dan Outbox Webhook) telah diuji secara otomatis:

```bash
php artisan test
```

**Hasil Pengujian Terkini (`CP-008`):**
```text
   PASS  Tests\Unit\ExampleTest
   PASS  Tests\Feature\ExampleTest
   PASS  Tests\Feature\IdentityAndRbacTest
   PASS  Tests\Feature\MfaTotpTest
   PASS  Tests\Feature\OidcFlowTest
   PASS  Tests\Feature\OutboxWebhookTest

  Tests:    29 passed (208 assertions)
  Duration: 4.82s
```

---

## 📂 8. Struktur Direktori Proyek

```
iam-sso-core/
├── app/
│   ├── Domain/                 # Modular Monolith Core Logic
│   │   ├── ApiKeys/            # API Key Machine Auth & Scopes
│   │   ├── Audit/              # Immutable Audit Logger
│   │   ├── Identity/           # User, MFA TOTP, & Master Password Service
│   │   ├── Oidc/               # Token Issuer, Keys (JWKS), Sessions, Authz
│   │   └── Tenancy/            # Outbox Service & Webhook Signer
│   ├── Http/
│   │   ├── Controllers/        # Admin, Login, Register, & OIDC Provider
│   │   └── Middleware/         # CORS, Security Headers, API Key & Admin Guards
│   └── Models/                 # Eloquent Entities (User, App, Role, Session, etc.)
├── database/
│   ├── migrations/             # Skema tabel IAM, OIDC, MFA, Settings
│   └── seeders/                # DatabaseSeeder ekosistem aplikasi
├── docs/
│   ├── CHECKPOINT.md           # Riwayat status & panduan sesi agent
│   ├── PRD.md                  # Product Requirements Document
│   └── ARCHITECTURE.md         # Dokumen detail arsitektur teknis
├── packages/
│   └── iam-client-core/        # SDK klien mandiri (PHP + Nuxt 3 composable)
├── resources/views/            # Blade Views (VyrnForge Cyber Dark Theme)
├── routes/
│   ├── web.php                 # Rute konsol admin & auth browser
│   └── oidc.php                # Rute endpoint OIDC & Machine Gateway
└── tests/                      # Automated Feature & Unit Test Suite
```

---

## 👨‍💻 Pengembang & Lisensi

- **Lead Engineer**: Moch. Adam Athallahrich
- **Peran**: Fullstack & Frontend Developer / Technical Researcher (PT. Sigma Cipta Caraka / Telkomsigma)
- **Desain UI**: VyrnForge Design System (Cyber Dark Tech Specification)
- **Status Lisensi**: Proprietary / Private Internal Framework
