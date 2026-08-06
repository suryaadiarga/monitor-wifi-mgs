# Backend ISP Terpadu

REST API Laravel 13 untuk autentikasi Sanctum, RBAC, dashboard, inventaris jaringan, pelanggan/paket, polling queue, serta adapter perangkat mock-first.

## Menjalankan lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Konfigurasi production menggunakan MariaDB dan Redis. Seeder demo hanya aktif bila `DEMO_DATA_ENABLED=true` dan `DEMO_ADMIN_PASSWORD` dipilih sendiri dengan panjang minimal 12 karakter.

## Verifikasi

```bash
vendor/bin/pint --test
php artisan test
composer audit --locked
```

Pada workstation Windows ini, SQLite test dapat dimuat khusus dengan:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit
```

Kontrak API tersedia di [`../documentation/API.md`](../documentation/API.md) dan [`../documentation/openapi.yaml`](../documentation/openapi.yaml).
