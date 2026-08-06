# Troubleshooting Operasional

Mulai dari signal yang nyata dan gunakan pemeriksaan read-only. Jangan mematikan firewall, membuka database ke Internet, mencetak `.env`, atau menjalankan migration destruktif untuk mencari jalan pintas.

## Pemeriksaan awal

```bash
sudo /usr/local/sbin/isp-manager-healthcheck
sudo systemctl --failed
sudo journalctl -p warning..alert --since '-30 min' --no-pager
sudo ufw status numbered
df -h
free -h
```

Jalankan kembali preflight dan deployment plan bila masalah muncul setelah perubahan server:

```bash
cd /opt/isp-manager-src
sudo ./deployment/preflight.sh --plan
sudo ./deployment/deploy.sh --plan --env-file /etc/isp-manager/deploy.env
```

## HTTP 502 atau aplikasi tidak terbuka

Pisahkan frontend dari API/PHP:

```bash
sudo nginx -t
sudo systemctl status nginx php8.3-fpm isp-manager-frontend --no-pager
sudo journalctl -u isp-manager-frontend -u php8.3-fpm -n 100 --no-pager
curl -I -H 'Host: isp.example.net' http://127.0.0.1/
curl -I -H 'Host: isp.example.net' http://127.0.0.1/up
ss -lntp | grep -E ':(80|443|3000)\b'
```

- `/` gagal tetapi `/up` berhasil: periksa build Next.js dan `isp-manager-frontend`.
- `/up` gagal: periksa PHP-FPM, permission `storage`/`bootstrap/cache`, `.env`, database, dan log Laravel.
- Nginx gagal reload: perbaiki `nginx -t` sebelum restart; jangan menghapus virtual host lain.

Permission yang diharapkan bukan `777`:

```bash
sudo chown -R www-data:www-data /var/www/isp-manager/backend/storage /var/www/isp-manager/backend/bootstrap/cache
sudo find /var/www/isp-manager/backend/storage /var/www/isp-manager/backend/bootstrap/cache -type d -exec chmod 775 {} +
sudo find /var/www/isp-manager/backend/storage /var/www/isp-manager/backend/bootstrap/cache -type f -exec chmod 664 {} +
```

## Queue atau scheduler tidak berjalan

```bash
sudo systemctl status isp-manager-queue isp-manager-scheduler --no-pager
sudo journalctl -u isp-manager-queue -u isp-manager-scheduler -n 150 --no-pager
sudo -u www-data sh -lc 'cd /var/www/isp-manager/backend && php artisan queue:failed'
sudo -u www-data sh -lc 'cd /var/www/isp-manager/backend && php artisan schedule:list'
redis-cli ping
```

Setelah deploy kode job baru, restart worker secara graceful:

```bash
sudo -u www-data sh -lc 'cd /var/www/isp-manager/backend && php artisan queue:restart'
```

Jangan menghapus failed jobs sebelum correlation ID, payload tersanitasi, dan akar masalah diperiksa.

## MariaDB gagal terhubung

```bash
sudo systemctl status mariadb --no-pager
sudo mariadb-admin ping
sudo mariadb -e 'SHOW DATABASES;'
sudo ss -lntp | grep ':3306'
```

MariaDB seharusnya hanya listen lokal/private dan tidak memiliki rule UFW publik. Jangan menampilkan password dengan `env`, `cat .env`, atau command line. Uji dari konteks aplikasi:

```bash
sudo -u www-data sh -lc 'cd /var/www/isp-manager/backend && php artisan migrate:status'
```

## MikroTik read-only gagal tersambung atau sinkronisasi stale

Baseline production 2026-07-13 UTC adalah koneksi keluar VPS `202.10.36.11` ke `103.87.202.202:8728`, identitas `WIFI-KAMPUNG`, latency 261-264 ms, dan dua full sync 1.918/1.882 ms dengan count identik. Pemeriksaan awal harus tetap read-only:

```bash
# Hanya memeriksa jalur TCP; tidak mengirim credential atau command RouterOS.
timeout 5 bash -c ':</dev/tcp/103.87.202.202/8728'

sudo systemctl status isp-manager-queue isp-manager-scheduler --no-pager
sudo journalctl -u isp-manager-queue -u isp-manager-scheduler --since '-30 min' --no-pager
sudo -u www-data sh -lc 'cd /var/www/isp-manager/backend && php artisan queue:failed'
redis-cli ping
redis-cli CONFIG GET maxmemory-policy
sudo ufw status numbered
```

Interpretasi umum:

- TCP timeout: periksa routing keluar VPS, status service API RouterOS, dan allowlist IP sumber di router. Jangan membuka 8728 inbound pada VPS.
- Authentication/permission failure: verifikasi akun RouterOS masih aktif dan tetap memiliki hak read-only. Jangan menampilkan password melalui shell history, `.env`, log, atau tiket.
- Job tertahan: periksa satu worker yang memang dikonfigurasi untuk VPS 1 GB, failed jobs, Redis lock/queue, dan scheduler. Redis production diharapkan memakai `noeviction`.
- Data stale tetapi koneksi berhasil: jalankan sync melalui endpoint/UI aplikasi yang berizin, lalu periksa correlation ID dan `observed_at`. Jangan menjalankan raw RouterOS write command sebagai diagnosis.
- Perbedaan count tidak selalu kegagalan. DHCP current tercatat berubah alami dari 184 menjadi 183; bandingkan timestamp dan error dataset sebelum menyimpulkan drift.
- PPP secrets dan Hotspot users tidak boleh memiliki field password dalam response, snapshot, log, atau audit. Hentikan investigasi dan ikuti prosedur insiden bila secret terlihat.

Snapshot pembanding terakhir: interfaces=134, PPPoE active=115, PPP secrets=119, PPP profiles=14, Hotspot active=14, Hotspot users=3, Hotspot profiles=5, DHCP current=183, dan queues=180. Nilai ini adalah observasi, bukan target konfigurasi; jangan mengubah router agar count "cocok".

RouterOS API 8728 masih non-TLS dan hanya diizinkan dari IP VPS. Migrasi API-SSL harus dilakukan dalam maintenance change manual dengan sertifikat/trust verification; jangan mengaktifkan mode insecure yang lebih luas. Controlled writes tetap disabled. Bukti jaringan awal tersedia di [raw preflight](reports/mikrotik-preflight-20260713-010655.log) dan hasil lengkap di [laporan akhir](reports/mikrotik-production-readonly-final-20260713-015516.md).

## RADIUS timeout atau reject

Pertama pastikan paket benar-benar mencapai FreeRADIUS:

```bash
sudo systemctl status freeradius --no-pager
sudo freeradius -XC
sudo journalctl -u freeradius -n 200 --no-pager
sudo ss -lunp | grep -E ':(1812|1813)\b'
sudo ufw status numbered
```

Interpretasi umum:

- `Ignoring request ... from unknown client <IP>` berarti paket sudah tiba, tetapi router belum terdaftar sebagai NAS/client. Cocokkan IP sumber nyata, record tabel `nas`, secret, lalu restart/reload FreeRADIUS. Jangan langsung membuka 1812/1813 ke seluruh Internet.
- `No reply from RADIUS server` tanpa log masuk mengarah ke routing, source IP router, UFW allowlist, atau service/listener.
- Username berbentuk `user@area` dapat ditolak policy realm/filter tertentu. Periksa log debug dan sesuaikan policy secara terbatas; jangan menonaktifkan validasi global tanpa memahami dampaknya.
- `Access-Accept` belum membuktikan PPPoE berhasil. Verifikasi alamat yang dialokasikan, profile/pool yang benar, accounting, dan session `/ppp active` pada MikroTik.

Untuk debug sementara, hentikan service lalu jalankan foreground hanya pada maintenance window:

```bash
sudo systemctl stop freeradius
sudo freeradius -X
# Ctrl+C setelah bukti cukup
sudo systemctl start freeradius
```

Sanitasi shared secret/password sebelum menyalin output debug ke tiket atau audit log.

## GenieACS atau MongoDB

```bash
sudo systemctl status mongod 'genieacs@cwmp' 'genieacs@nbi' 'genieacs@fs' 'genieacs@ui' --no-pager
sudo journalctl -u mongod -u 'genieacs@*' -n 200 --no-pager
sudo ss -lntp | grep -E ':(27017|7547|7557|7567|3001)\b'
mongosh --quiet --eval 'db.runCommand({ ping: 1 })'
```

NBI, FS, UI, dan MongoDB harus bind ke localhost. CWMP boleh bind ke semua interface hanya bila UFW membatasi 7547 ke `CWMP_ALLOWED_IPS`. Jika ONT tidak inform, periksa URL ACS, waktu perangkat, DNS/routing, allowlist, dan log CWMP sebelum mengubah parameter provisioning.

## Backup gagal

```bash
sudo systemctl status isp-manager-backup.service --no-pager
sudo journalctl -u isp-manager-backup.service -n 200 --no-pager
sudo /usr/local/sbin/isp-manager-backup --plan
sudo test -s /etc/isp-manager/backup-passphrase && echo 'backup key present'
sudo find /var/backups/isp-manager -maxdepth 2 -type d -printf '%p\n'
```

Penyebab umum: ruang disk kurang, credential database berubah tetapi `/etc/isp-manager/backup.env` belum diperbarui, `mongodump` tidak tersedia, key enkripsi hilang, atau snapshot off-site tidak dapat ditulis. Script menghapus staging gagal dan tidak memublikasikannya sebagai snapshot valid.

## Terblokir UFW/SSH

Deployment menambahkan rule SSH sebelum mengubah default incoming. Sebelum menutup sesi SSH pertama, buka sesi kedua dan uji login. Jika `SSH_ALLOW_FROM` salah tetapi masih memiliki console VPS:

```bash
sudo ufw status numbered
sudo ufw allow from <IP-ADMIN>/32 to any port 22 proto tcp comment 'temporary admin recovery'
```

Jangan menjalankan `ufw reset` atau `ufw disable`. Setelah akses pulih, perbaiki `/etc/isp-manager/deploy.env`, jalankan firewall plan, baru apply ulang.

## Node.js/MongoDB repository gagal

```bash
apt-cache policy nodejs mongodb-org
sudo apt-get update
ls -l /etc/apt/keyrings/nodesource.gpg /etc/apt/keyrings/mongodb-server-*.gpg
cat /etc/apt/sources.list.d/nodesource.list
cat /etc/apt/sources.list.d/mongodb-org-*.list
```

Pastikan host benar-benar Ubuntu 24.04 (`noble`) dan waktu sistem benar. Jangan mengatasi signature error dengan menambahkan `trusted=yes` atau menonaktifkan validasi TLS/GPG.
