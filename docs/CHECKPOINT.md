# CHECKPOINT: IAM-SSO-CORE

> Baca file ini PERTAMA di percakapan baru, lalu lanjutkan dari bagian "Langkah Berikutnya".
> Terakhir diperbarui: 2026-10-26 (CP-008)

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

## Status Terkini (CP-003 — Core 100% Selesai + UI/UX Pro Max Console, Test 27/27 Lulus)
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
   - **Pengujian Otomatis**: `iam-sso-core` **29/29 lulus (204 assertions)**, `mochi-finance-admin` **7/7 lulus (35 assertions)**.
2. **Tahap 2 Berikutnya (`portfolio-admin` + `portofolio-motion`)**:
   - Bangun aplikasi Laravel CMS & API Portofolio di `C:\laragon\www\portfolio-admin` (`http://portfolio-admin.test:8888`, DB `portfolio_admin_db`, `client_id: APP-0001`).
   - Hubungkan Nuxt 3 `e:\Data Pribadi\web-porto\portofolio-motion` ke `http://portfolio-admin.test:8888/api/motion/portfolio` dengan API Key dari `iam-sso-core`.
