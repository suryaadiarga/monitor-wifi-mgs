# Backup dan Restore

Backup harian dijalankan oleh `isp-manager-backup.timer` pada sekitar pukul 02:30 dengan random delay. Snapshot disimpan di `/var/backups/isp-manager/{daily,weekly,monthly}` dan diverifikasi dengan SHA-256 sebelum dipublikasikan. Direktori staging yang gagal tidak dianggap snapshot valid.

## Cakupan backup

- MariaDB aplikasi dan database SQL FreeRADIUS, keduanya terenkripsi;
- MongoDB GenieACS yang terenkripsi;
- source dan file aplikasi terenkripsi, tanpa dependency/cache/log;
- `.env` Laravel yang dienkripsi AES-256 oleh GnuPG;
- konfigurasi deploy, Nginx, FreeRADIUS, GenieACS, WireGuard, dan strongSwan dalam arsip terenkripsi;
- manifest waktu, host, database, dan checksums.

Backup MikroTik yang diunduh oleh modul aplikasi dapat ikut melalui direktori storage aplikasi. Pastikan job perangkat menyimpannya di disk yang tercakup dan tidak menulis password/private key ke log.

## Kunci enkripsi

Deploy membuat passphrase acak di `/etc/isp-manager/backup-passphrase` dengan mode `0600`. Salin kunci ini ke password manager/offline vault yang berbeda dari VPS:

```bash
sudo install -m 0600 /etc/isp-manager/backup-passphrase /media/offline/isp-manager-backup-passphrase
```

Tanpa kunci tersebut, `.env` dan konfigurasi service tidak dapat direstore. Menyimpan satu-satunya salinan kunci pada VPS tidak melindungi dari kehilangan total host.

## Menjalankan backup manual

```bash
sudo /usr/local/sbin/isp-manager-backup --plan
sudo /usr/local/sbin/isp-manager-backup --apply
sudo systemctl start isp-manager-backup.service
sudo journalctl -u isp-manager-backup.service -n 100 --no-pager
```

Verifikasi snapshot terbaru:

```bash
SNAPSHOT=$(find /var/backups/isp-manager/daily -mindepth 1 -maxdepth 1 -type d | sort | tail -n1)
sudo sh -c 'cd "$1" && sha256sum -c SHA256SUMS' sh "$SNAPSHOT"
sudo sh -c 'gpg --batch --quiet --pinentry-mode loopback --passphrase-file /etc/isp-manager/backup-passphrase --decrypt "$1/app-mariadb.sql.gz.gpg" | gzip -t' sh "$SNAPSHOT"
sudo sh -c 'gpg --batch --quiet --pinentry-mode loopback --passphrase-file /etc/isp-manager/backup-passphrase --decrypt "$1/radius-mariadb.sql.gz.gpg" | gzip -t' sh "$SNAPSHOT"
```

Retensi default adalah 14 hari untuk daily, 56 hari untuk weekly, dan 365 hari untuk monthly. Snapshot mingguan dibuat hari Minggu dan snapshot bulanan pada tanggal 1. Ubah nilai retensi melalui `/etc/isp-manager/backup.env`, lalu jalankan backup manual untuk menguji.

Notifikasi kegagalan Telegram dapat diaktifkan dengan menambahkan `TELEGRAM_BOT_TOKEN` dan `TELEGRAM_CHAT_ID` ke `/etc/isp-manager/backup.env` (mode `0600`). Token hanya dibaca oleh service backup dan tidak masuk snapshot plaintext atau log command.

## Off-site backup

Salin snapshot yang sudah final, bukan direktori `.staging-*`, ke object storage/server kedua dengan enkripsi transport. Contoh `rsync` ke target yang sudah diamankan:

```bash
sudo rsync -a --partial /var/backups/isp-manager/ backup@backup-private:/srv/backups/isp-manager/
```

Credential remote harus berada di root-owned SSH key/secret manager, bukan source. Lakukan uji restore berkala di staging; checksum saja tidak membuktikan bahwa kredensial dan versi database kompatibel.

## Restore: selalu mulai dengan plan

Restore dapat mengganti isi tabel dari SQL dump dan menimpa file/config yang ada. Script karena itu:

1. memverifikasi checksum;
2. mewajibkan root, `--apply`, dan frasa konfirmasi tepat;
3. membuat safety backup kondisi saat ini;
4. mengaktifkan maintenance mode dan menghentikan worker;
5. merestore lalu menghidupkan kembali service.

```bash
SNAPSHOT=/var/backups/isp-manager/daily/20260713-023000
sudo /usr/local/sbin/isp-manager-restore --snapshot "$SNAPSHOT" --plan
sudo /usr/local/sbin/isp-manager-restore \
  --snapshot "$SNAPSHOT" \
  --apply \
  --confirm RESTORE:20260713-023000
```

Secara default, MongoDB direstore tanpa `--drop`. Bila pemulihan harus mengganti collection MongoDB secara penuh, gunakan flag tambahan dan frasa yang lebih kuat:

```bash
sudo /usr/local/sbin/isp-manager-restore \
  --snapshot "$SNAPSHOT" \
  --mongo-drop \
  --apply \
  --confirm RESTORE:20260713-023000:DROP_MONGO
```

`--mongo-drop` menghapus collection target sebelum restore. Gunakan hanya setelah safety backup sudah terbukti valid dan dampak disetujui eksplisit.

Setelah restore, rekonsiliasi dependency dan migration secara aman:

```bash
cd /opt/isp-manager-src
sudo ./deployment/deploy.sh --plan --env-file /etc/isp-manager/deploy.env
sudo ./deployment/deploy.sh --apply --env-file /etc/isp-manager/deploy.env
sudo /usr/local/sbin/isp-manager-healthcheck
```

Lakukan pengecekan fungsional: login, permission, dashboard, satu query RADIUS, satu perangkat mock, queue/scheduler, dan audit log. Jangan menjalankan authentication/provisioning test destruktif ke perangkat production.

## Restore ke host baru

1. Instal Ubuntu 24.04 bersih dan salin source, snapshot, serta kunci enkripsi melalui kanal aman.
2. Jalankan deploy plan lalu apply untuk menyiapkan package/database kosong.
3. Salin snapshot ke `/var/backups/isp-manager/daily/<timestamp>` dan verifikasi checksum.
4. Jalankan restore dengan konfirmasi eksplisit.
5. Jalankan deploy apply lagi untuk rekonsiliasi service, dependency, dan migration.
6. Perbarui DNS hanya setelah healthcheck dan uji login/RBAC lulus.
