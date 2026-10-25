# IAM-SSO-CORE

Core Identity + SSO (OIDC) berbasis Laravel. Mulai dari `docs/CHECKPOINT.md`, lalu `docs/PRD.md` dan `docs/ARCHITECTURE.md`.

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate && php artisan iam:create-admin email nama
php artisan serve
php artisan test
```

