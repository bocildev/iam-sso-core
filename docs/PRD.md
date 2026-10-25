# PRD: IAM-SSO-CORE

## Visi
Satu pusat identitas dan akses untuk semua aplikasi pemilik platform: login sekali (SSO OIDC), akses dikelola terpusat, token diverifikasi lokal oleh app klien, audit lengkap. Core harus reusable (domain terpisah dari HTTP) dan bisa dipakai app apa pun.

## Tujuan dan Ukuran
| ID | Tujuan | Ukuran |
|---|---|---|
| G1 | SSO lintas app | Semua app terdaftar memakai OIDC |
| G2 | Sumber tunggal akses (user, role, permission per app) | 0 tabel role duplikat di app |
| G3 | Verifikasi token lokal | 0 panggilan sinkron ke IAM per request |
| G4 | Onboarding app cepat via paket klien | <= 1 hari per app |
| G5 | Pencabutan akses cepat | Menyebar < 5 menit (access token 15 menit + back-channel + webhook) |
| G6 | Audit lengkap | 100% event kunci tercatat |

Non-tujuan: login sosial/federasi IdP eksternal, billing, menulis ulang UI klien, memindahkan data domain app ke IAM.

## Persona
Super Admin IAM, Developer app klien, Pengguna akhir, Client app (mesin, API key ber-scope).

## Kebutuhan Fungsional (status per CP-001)
| ID | Kebutuhan | Status |
|---|---|---|
| FR-01 | Discovery | Selesai |
| FR-02 | JWKS RS256 + `kid`, rotasi | Selesai |
| FR-03 | Authorize Code + PKCE S256, redirect_uri cocok persis | Selesai |
| FR-04 | Token: access 15 menit, refresh 30 hari rotasi + reuse detection | Selesai |
| FR-05 | ID token (`sub aud nonce name email at_hash auth_time sid`) | Selesai |
| FR-06 | Klaim `tenant_id roles permissions` per audience | Selesai |
| FR-07 | UserInfo | Selesai |
| FR-08 | Login SSO + rate limit + regenerate session | Selesai |
| FR-09 | SSO lintas app (sesi IAM bersama) | Selesai (perlu diuji dengan 2 app nyata) |
| FR-10 | Logout terpusat + back-channel | Selesai |
| FR-11 | Registri app (CRUD, rotasi secret tampil sekali) | Selesai |
| FR-12 | Kelola user/organisasi (suspend, reset password, role, akses app) | Selesai |
| FR-13 | RBAC per app (`resource.action`) | Selesai |
| FR-14 | Akses user ke app (`user_applications`) | Selesai |
| FR-15 | Outbox + webhook HMAC | Belum |
| FR-16 | Audit log (append-only, viewer) | Selesai |
| FR-17 | Paket klien `iam-client-core` | Belum |
| FR-18 | Adapter klien (Laravel, CI3, Nuxt) | Belum |
| FR-19 | Kompatibilitas password lama (rehash) | Sebagian (rehash saat login; import hash lama belum) |
| FR-20 | Koeksistensi `legacy/oidc/both` | Field ada; penegakan di klien belum |
| FR-21 | API key gateway mesin (scope, hash-only) | Sebagian (CRUD ada, middleware validasi belum) |
| FR-22 | MFA TOTP admin, reset password email | Belum |

## Non-Fungsional
Keamanan: PKCE S256, RS256, access <= 15 menit, secret via env, hash-only token, fail-closed. Performa: verifikasi lokal < 5 ms, `/oauth/token` p95 < 300 ms. Ketersediaan: klien cache JWKS 1 jam. Observability: audit tak bisa dihapus lewat UI. Test: otomatis untuk alur OIDC dan admin.

## Risiko
Cookie SSO lintas domain (pakai redirect flow standar, bukan cookie bersama); downtime cutover (mode `both`); scope creep (admin minimal dulu); kunci RSA hilang (backup `signing_keys` + `APP_KEY`, karena private key dienkripsi dengan APP_KEY).
