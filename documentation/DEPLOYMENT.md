# Deployment Ubuntu 24.04

Fondasi deployment ini ditujukan untuk Ubuntu Server 24.04 LTS. Core application, MariaDB, Redis, FreeRADIUS, MongoDB, GenieACS, WireGuard, dan strongSwan dipasang native. Stack Prometheus/Grafana/Loki bersifat opsional dan terisolasi di Docker Compose.

`deployment/deploy.sh` selalu berada dalam mode rencana (dry-run) kecuali dipanggil dengan `--apply` atau `APPLY=1`. Script tidak memakai `DROP DATABASE`, `migrate:fresh`, `ufw reset`, maupun `chmod 777`. Instalasi yang sudah memiliki `backend/.env` wajib lolos backup terlebih dahulu sebelum perubahan diterapkan.

## Prasyarat

- VPS Ubuntu 24.04 dengan minimal 2 GiB RAM dan 10 GiB ruang kosong; 4 GiB RAM disarankan bila GenieACS dan monitoring berjalan di host yang sama.
- Akses `root`/`sudo`, DNS A/AAAA untuk domain aplikasi, serta outbound HTTPS ke repository paket.
- Port 22, 80, dan 443 sesuai kebutuhan. IP/CIDR NAS dan jaringan ONT harus sudah diketahui sebelum membuka RADIUS/CWMP.
- Credential produksi dibuat sendiri. Jangan menyalin credential router, database, Telegram, SNMP, atau VPN ke repository.

## 1. Siapkan source dan konfigurasi

Contoh menempatkan source di `/opt/isp-manager-src`:

```bash
sudo install -d -m 0755 /opt/isp-manager-src
sudo rsync -a --exclude=.git ./ /opt/isp-manager-src/
cd /opt/isp-manager-src

sudo install -d -m 0700 /etc/isp-manager
sudo install -m 0600 deployment/.env.example /etc/isp-manager/deploy.env
sudoedit /etc/isp-manager/deploy.env
```

Wajib diganti sebelum apply:

- `SERVER_NAME`, `APP_URL`, dan `CERTBOT_EMAIL`;
- `DB_PASSWORD` dan `RADIUS_DB_PASSWORD` dengan nilai unik;
- `RADIUS_ALLOWED_IPS` hanya berisi IP/CIDR router/NAS;
- `CWMP_ALLOWED_IPS` hanya berisi jaringan ONT/private;
- `SSH_ALLOW_FROM` bila SSH dapat dibatasi ke IP kantor atau VPN.

Password database minimal 16 karakter dan menggunakan karakter aman untuk seluruh format konfigurasi: huruf, angka, serta `._~!@#%^+=,:/-`. Pembatasan ini mencegah nilai ambigu saat secret yang sama ditulis ke MariaDB, dotenv, FreeRADIUS, dan systemd.

Gunakan `ENABLE_HTTPS=0` pada apply pertama bila DNS belum siap. Aktifkan kemudian setelah DNS sudah menunjuk ke VPS. Demo seeder tidak dijalankan default; set `RUN_SEEDER=1` hanya jika `DatabaseSeeder` yang tersedia memang idempotent dan aman untuk production.

## 2. Audit dan lihat rencana

```bash
cd /opt/isp-manager-src
sudo ./deployment/preflight.sh --plan
sudo ./deployment/deploy.sh --plan --env-file /etc/isp-manager/deploy.env
```

Periksa setiap peringatan port yang sudah dipakai. Pada apply, laporan preflight disimpan di `/var/log/isp-manager/preflight/`. Script sengaja menjalankan `apt-get update`, bukan upgrade mayor sistem otomatis.

## 3. Terapkan

```bash
sudo ./deployment/deploy.sh --apply --env-file /etc/isp-manager/deploy.env
```

Alternatif untuk otomasi:

```bash
sudo env APPLY=1 ./deployment/deploy.sh --env-file /etc/isp-manager/deploy.env
```

Apply melakukan hal berikut secara idempotent:

1. memasang Nginx, PHP 8.3, Composer tervalidasi, MariaDB, Redis, Node.js 22, FreeRADIUS SQL, MongoDB 8, GenieACS, WireGuard, strongSwan, Certbot, dan alat backup;
2. membuat database/user dengan `IF NOT EXISTS`, tanpa menghapus database yang sudah ada;
3. menyalin source tanpa opsi `--delete`, memasang dependency, build Next.js, dan menjalankan migration biasa;
4. merender Nginx, FreeRADIUS, systemd, logrotate, timer backup, serta timer health check;
5. mengaktifkan UFW deny-by-default dan allowlist RADIUS/CWMP;
6. menjalankan pemeriksaan Nginx, FreeRADIUS, service, Redis, disk, dan endpoint `/up`.

Database FreeRADIUS menggunakan database terpisah `isp_manager_radius` secara default. Tabel standar hanya diimpor jika `radcheck` belum ada. `read_clients = yes` membaca NAS dari tabel `nas`; tetap daftarkan NAS melalui aplikasi/API supaya secret dicatat dan dikelola secara aman.

## 4. Verifikasi

```bash
sudo nginx -t
sudo freeradius -XC
sudo systemctl --no-pager --full status \
  isp-manager-frontend isp-manager-queue isp-manager-scheduler \
  nginx php8.3-fpm mariadb redis-server freeradius mongod
sudo systemctl list-timers 'isp-manager-*'
sudo HEALTH_HOST=isp.example.net /usr/local/sbin/isp-manager-healthcheck
sudo ufw status numbered
curl -I --resolve isp.example.net:80:127.0.0.1 http://isp.example.net/up
```

Verifikasi firewall harus menunjukkan UDP 1812/1813 hanya dari `RADIUS_ALLOWED_IPS`. Port MariaDB 3306, Redis 6379, MongoDB 27017, GenieACS NBI/UI/FS, SNMP, MikroTik API, dan OLT SSH/Telnet tidak boleh memiliki rule publik.

Setelah HTTPS aktif:

```bash
curl -fsS https://isp.example.net/up
sudo certbot renew --dry-run
```

## Service dan lokasi penting

| Komponen | Lokasi/unit |
|---|---|
| Laravel | `/var/www/isp-manager/backend` |
| Next.js | `isp-manager-frontend.service` |
| Queue | `isp-manager-queue.service` |
| Scheduler | `isp-manager-scheduler.service` |
| Backup | `isp-manager-backup.timer` |
| Health check | `isp-manager-healthcheck.timer` |
| GenieACS | `genieacs@cwmp`, `@nbi`, `@fs`, `@ui` |
| Deploy config | `/etc/isp-manager/deploy.env` mode `0600` |
| Laravel env | `backend/.env` mode `0640`, owner `root:www-data` |
| Backup key | `/etc/isp-manager/backup-passphrase` mode `0600` |

## Monitoring opsional

Grafana, Prometheus, Loki, Grafana Alloy, Node Exporter, dan SNMP Exporter hanya bind ke localhost. Akses melalui VPN, SSH tunnel, atau reverse proxy yang diautentikasi.

```bash
cd /var/www/isp-manager/monitoring
cp .env.example .env
chmod 600 .env
editor .env                    # isi GRAFANA_ADMIN_PASSWORD yang unik
docker compose config
docker compose up -d
docker compose ps
```

Target SNMP diletakkan sebagai file discovery JSON di `monitoring/targets/snmp/`. Community/credential SNMP jangan ditulis ke Git; gunakan auth module SNMP Exporter yang diprovisikan server-side. Image monitoring dipin melalui `.env`; uji upgrade di staging sebelum mengubah versinya.

## Pembaruan berikutnya

1. Tarik source baru ke direktori source, bukan langsung menimpa credential production.
2. Jalankan `deploy.sh --plan` dengan env yang sama.
3. Pastikan backup terakhir valid.
4. Jalankan `deploy.sh --apply`; script membuat safety backup lagi sebelum migration.
5. Periksa health check, queue, scheduler, serta UI/API.

Untuk rollback data, gunakan prosedur eksplisit pada [BACKUP_RESTORE.md](BACKUP_RESTORE.md). Jangan memakai `migrate:fresh` di production.
