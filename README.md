# ISP Terpadu

ISP Terpadu adalah aplikasi web untuk mengelola pelanggan, router MikroTik, PPPoE, Hotspot, FreeRADIUS, perangkat TR-069 melalui GenieACS, OLT multi-vendor, VPN, alert, dan audit aktivitas dari satu tempat.

> **Status: deployment production dan integrasi MikroTik read-only telah diverifikasi pada 13 Juli 2026.** Aplikasi aktif di [https://202-10-36-11.nip.io](https://202-10-36-11.nip.io) pada Ubuntu 24.04. Router production sudah tersambung untuk inventaris dan sinkronisasi baca-saja; controlled writes tetap dinonaktifkan. Integrasi OLT/GenieACS/RADIUS/WireGuard/Telegram masih menggunakan batas mock/dry-run atau menunggu allowlist/perangkat nyata, sehingga seluruh acceptance MVP jaringan belum dinyatakan selesai. Lihat [progress proyek](documentation/PROGRESS.md).

## Sasaran teknis

| Komponen | Standar proyek |
|---|---|
| Backend | Laravel 13, PHP 8.3+, REST API `/api/v1` |
| Frontend | Next.js 16, React 19, TypeScript, Tailwind CSS |
| Data utama | MariaDB; SQLite tidak digunakan untuk production |
| Cache/queue/session/lock | Redis |
| AAA | FreeRADIUS dengan backend SQL MariaDB |
| ACS | GenieACS dengan MongoDB khusus GenieACS |
| Runtime production | Ubuntu Server 24.04 LTS, Nginx, PHP-FPM, systemd/Supervisor |
| VPN | WireGuard dan strongSwan IKEv2 |

Arsitektur yang dipilih adalah **modular monolith**: satu backend Laravel dengan batas modul yang jelas, service layer untuk aturan bisnis, dan adapter layer untuk perangkat/vendor. Integrasi nyata harus dapat diganti dengan mock adapter agar pengembangan dan test tidak memerlukan credential production.

## Struktur repository

```text
backend/        Laravel API, queue, scheduler, service, adapter
frontend/       Next.js web app berbahasa Indonesia
deployment/     preflight dan otomasi instalasi Ubuntu yang dry-run secara default
documentation/ arsitektur, kontrak, keamanan, integrasi, dan progress
scripts/        backup, restore, dan health check operasional
monitoring/     stack Prometheus/Grafana/Loki opsional
```

## Status deployment production

Snapshot verifikasi 2026-07-13:

- Host Ubuntu 24.04: `202.10.36.11`.
- URL HTTPS: [https://202-10-36-11.nip.io](https://202-10-36-11.nip.io); sertifikat TLS valid sampai 2026-10-10.
- Seluruh 13 service wajib aktif dan tidak ada unit systemd yang gagal.
- API detail router beserta 10 endpoint nested MikroTik lulus smoke test; database memiliki migration read-only `2026_07_13_010000_add_read_only_mikrotik_sync_tables.php`.
- Backup terenkripsi setelah integrasi `20260713-015516` berhasil diverifikasi. Restore drill tetap harus dilakukan terpisah.
- Service internal hanya bind ke loopback. UFW hanya membuka port publik yang dimaksudkan: 22, 80, dan 443.
- RADIUS UDP 1812/1813 dan CWMP TCP 7547 sengaja masih ditutup sampai allowlist sumber disetujui.
- Credential administrator awal disimpan di `/etc/isp-manager/initial-admin.txt`; nilainya tidak didokumentasikan atau dimasukkan ke repository.
- Stack monitoring dan service VPN opsional dinonaktifkan karena VPS hanya memiliki RAM 1 GB.

### MikroTik production read-only

- Jalur VPS `202.10.36.11` ke RouterOS API `103.87.202.202:8728` lulus preflight pada 2026-07-13 UTC; outbound IP VPS sesuai allowlist router.
- Router `WIFI-KAMPUNG` menjalankan RouterOS `7.20.8 (long-term)` pada model `C53UiG+5HPaxD2HPaxD`.
- Integrasi memakai `evilfreelancer/routeros-api-php` 1.7.1. Credential disimpan terenkripsi dan tidak dikembalikan melalui API, log, audit, atau source code.
- Dua full sync selesai dalam 1.918 ms dan 1.882 ms dengan count identik. Snapshot terakhir: 134 interface, 115 PPPoE aktif, 119 PPP secret tanpa password, 14 PPP profile, 14 Hotspot aktif, 3 Hotspot user tanpa password, 5 Hotspot profile, 183 DHCP current, dan 180 queue.
- Tidak ada perintah tulis atau perubahan konfigurasi yang dilakukan terhadap router. API port 8728 masih non-TLS dan hanya menerima VPS yang di-allowlist; migrasi ke API-SSL adalah pekerjaan manual berikutnya.
- Laporan lengkap: [verifikasi akhir MikroTik read-only](documentation/reports/mikrotik-production-readonly-final-20260713-015516.md) dan [raw preflight](documentation/reports/mikrotik-preflight-20260713-010655.log).

Domain `nip.io` tersebut bersifat sementara dan harus diganti dengan domain milik sendiri sebelum penggunaan production jangka panjang.

## Menjalankan secara lokal

### Prasyarat

- PHP 8.5 beserta ekstensi umum Laravel (`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`).
- Composer 2.
- Node.js 22.13 atau lebih baru dan npm.
- MariaDB 10.11 atau versi kompatibel yang masih didukung.
- Redis 7 atau versi kompatibel.

FreeRADIUS, MongoDB, GenieACS, WireGuard, dan strongSwan tidak wajib untuk menyalakan scaffold Tahap 1. Gunakan mock adapter sampai layanan tersebut dikonfigurasi.

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Sebelum migration, ubah `.env` lokal ke MariaDB dan Redis. Jangan memakai nilai contoh ini di production:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=isp_terpadu
DB_USERNAME=isp_local
DB_PASSWORD=ganti-dengan-password-lokal

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Hanya untuk lingkungan demo lokal:
DEMO_DATA_ENABLED=true
DEMO_ADMIN_EMAIL=admin@isp.test
DEMO_ADMIN_PASSWORD=buat-password-lokal-minimal-12-karakter
```

Jalankan migration dan seeder. Password demo wajib dipilih sendiri dan tidak disimpan di repository:

```bash
php artisan migrate --seed
php artisan serve
```

Pada terminal lain jalankan worker dan scheduler:

```bash
cd backend
php artisan queue:work --tries=3 --timeout=120
php artisan schedule:work
```

Pastikan `DB_CONNECTION=mysql` sebelum menjalankan migration production. Seeder demo nonaktif secara default dan tidak boleh digunakan untuk membuat akun production.

### Frontend

```bash
cd frontend
npm ci
```

Buat `.env.local` tanpa memasukkannya ke Git:

```dotenv
NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api/v1
NEXT_PUBLIC_APP_URL=http://localhost:3000
```

Kemudian:

```bash
npm run dev
```

Buka `http://localhost:3000`. Ketersediaan halaman dan login mengikuti status implementasi di `documentation/PROGRESS.md`.

## Pemeriksaan pengembangan

```bash
cd backend
vendor/bin/pint --test
php artisan test

cd ../frontend
npm run lint
npm run build
```

Pada PHP Windows yang ekstensi SQLite-nya belum aktif, jalankan suite lokal tanpa mengubah konfigurasi global:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit
```

Test integrasi harus menggunakan mock atau perangkat lab. Jangan mengarahkan test destruktif ke perangkat production.

## Gambaran deployment production

Deployment pertama pada Ubuntu 24.04 telah melewati verifikasi dasar. Untuk deployment ulang, perpindahan host, atau perubahan besar, prosedur berikut tetap wajib:

1. Siapkan DNS, HTTPS, akun deploy non-root, firewall, dan backup konfigurasi/database yang sudah ada.
2. Pasang PHP 8.3+/PHP-FPM, Nginx, MariaDB, Redis, Node.js 22.13+, serta layanan integrasi yang dibutuhkan.
3. Buat database dan user dengan hak minimum; MariaDB, Redis, MongoDB, GenieACS internal, RADIUS, API perangkat, SNMP, dan OLT tidak boleh terbuka ke Internet.
4. Salin environment production melalui kanal rahasia, buat `APP_KEY`, dan gunakan credential berbeda untuk core, RADIUS, serta GenieACS.
5. Jalankan `composer install --no-dev --optimize-autoloader`, migration terkontrol, lalu build frontend dengan `npm ci && npm run build`.
6. Jalankan PHP-FPM, queue worker, scheduler, dan Next.js melalui systemd/Supervisor; aktifkan Nginx dan HTTPS.
7. Verifikasi health check, queue, scheduler, login/RBAC, backup/restore, dan log sebelum menghubungkan perangkat nyata.

Otomasi idempotent tersedia di `deployment/` dan selalu dry-run secara default. Jangan memakai mode `--apply` pada server yang memiliki data penting sebelum membaca laporan preflight dan memastikan backup dapat dipulihkan.

## Prinsip keselamatan

- Credential perangkat disimpan terenkripsi dan tidak pernah dikembalikan lagi oleh API setelah tersimpan.
- Semua mutasi perangkat memerlukan permission backend, konfirmasi UI, audit log, correlation ID, dan hasil yang disanitasi.
- Bulk action selalu memakai alur preview lalu apply; tidak ada terminal bebas MikroTik di browser.
- Factory reset ONT memerlukan permission khusus dan konfirmasi ulang serial number.
- Perintah OLT nyata hanya diaktifkan setelah diverifikasi di perangkat lab; default pengembangan adalah mock.
- Polling berjalan melalui queue/scheduler dan Redis lock, bukan ketika halaman dibuka.

## Dokumentasi

- [Kebutuhan](REQUIREMENTS.md)
- [Arsitektur](documentation/ARCHITECTURE.md)
- [Database](documentation/DATABASE.md)
- [REST API](documentation/API.md)
- [OpenAPI 3.1](documentation/openapi.yaml)
- [Deployment Ubuntu](documentation/DEPLOYMENT.md)
- [Backup dan restore](documentation/BACKUP_RESTORE.md)
- [Keamanan](documentation/SECURITY.md)
- [MikroTik](documentation/MIKROTIK_INTEGRATION.md)
- [FreeRADIUS](documentation/RADIUS_INTEGRATION.md)
- [GenieACS](documentation/GENIEACS_INTEGRATION.md)
- [OLT adapter](documentation/OLT_ADAPTER.md)
- [VPN](documentation/VPN.md)
- [Progress](documentation/PROGRESS.md)
