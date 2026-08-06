# Modul VPN

## Status

WireGuard dan IKEv2 strongSwan ditargetkan pada Tahap 5. Dokumen ini adalah desain provisioning; belum ada klaim service, firewall, certificate authority, client generation, QR, atau rotasi key telah diimplementasikan/diuji.

## 1. Tujuan dan boundary

VPN memberikan akses administrator/teknisi ke management network. Laravel tidak menjalankan string shell dari request. `VpnService` membuat intent terstruktur dan `VpnAdapterInterface` melakukan perubahan OS melalui helper/command allowlist dengan privilege minimum.

```text
API -> VpnService -> preview/provision job -> VpnAdapterInterface
                                             |-- WireGuardAdapter
                                             |-- StrongSwanAdapter
                                             `-- MockVpnAdapter
```

Perubahan konfigurasi memakai render ke file sementara berpermission ketat, validasi, atomic replace, reload, health check, dan rollback file terakhir bila reload gagal.

## 2. WireGuard

Data server: interface, listen port, endpoint, address pool, DNS/allowed routes, public key, status. Data client: owner, name, assigned IP, public key, encrypted private/preshared key, allowed IPs, expiry, enabled, last handshake, RX/TX.

Lifecycle provisioning:

1. Validasi permission, owner/scope, pool, route allowlist, dan konflik IP.
2. Preview server, endpoint, assigned IP, routes, expiry, dan dampak firewall.
3. Generate private/public key dan preshared key menggunakan CSPRNG/tool resmi di host; tidak menerima key lemah buatan client secara default.
4. Simpan private/PSK encrypted dan tulis peer server secara atomic.
5. Tampilkan/download konfigurasi dan QR **satu kali** melalui response/file `no-store` ber-expiry pendek.
6. Setelah provisioning selesai, read API hanya memberi public key/fingerprint/status; untuk config baru harus rotate/reprovision.
7. Audit tanpa private key/PSK/config/QR.

QR dianggap secret setara config file: tidak masuk log, cache, screenshot otomatis, object storage publik, atau histori browser yang tidak perlu.

Status berasal dari `wg show`/API adapter yang diparse terstruktur. “Aktif” dibedakan antara peer enabled dan handshake baru dalam threshold. Counter/handshake bersifat snapshot dan dashboard membacanya dari cache.

## 3. Alokasi IP dan route

- Pool disimpan per server dan unique constraint mencegah IP ganda.
- IP network/broadcast/gateway/reserved tidak dapat dialokasikan.
- Route client menggunakan template berdasarkan role/area, bukan input CIDR bebas kecuali permission khusus.
- Teknisi hanya mendapat subnet yang diperlukan; akses database/Redis/host management tidak otomatis diberikan.
- Revoke peer menghapus akses server segera, tetapi metadata/audit tetap ada.

## 4. IKEv2 strongSwan

Autentikasi certificate adalah pilihan utama. CA/private key dikelola di luar web root dengan permission ketat atau secret/HSM yang sesuai. PSK hanya mode kompatibilitas dan diberi warning.

Fitur target:

- server/profile dan traffic selector;
- identity/user;
- issue/revoke certificate, expiry, serial, fingerprint;
- encrypted private key/PSK dan one-time provisioning bundle;
- status systemd/SA/client yang tersanitasi;
- CRL/OCSP atau mekanisme revoke yang benar sesuai desain PKI.

Jangan membangun CA production secara otomatis tanpa keputusan nama, lifetime, key custody, backup, dan prosedur revoke. Certificate client bukan pengganti authorization jaringan; firewall tetap membatasi subnet/role.

## 5. Secret handling

- Key/PSK encrypted at rest; encryption key tidak berada bersama backup tanpa proteksi terpisah.
- Process argument, stdout/stderr, job payload, audit, log, dan exception tidak membawa secret.
- Temporary file memakai directory privat, mode minimum, cleanup pada success/failure, dan tidak disimpan di `/tmp` umum bila dapat dihindari.
- Download provisioning membutuhkan recent re-auth, owner/permission, expiry pendek, satu kali, HTTPS, dan `Cache-Control: no-store`.
- Rotasi membuat key baru, mengganti peer/identity secara terkontrol, merevoke yang lama, dan mengaudit fingerprint non-secret.

## 6. Firewall dan routing

- WireGuard UDP dibuka hanya pada port yang dikonfigurasi; IKEv2 memakai UDP 500/4500 hanya bila aktif.
- Forwarding/NAT tidak diaktifkan global tanpa preview rule dan scope jaringan.
- Aturan dibuat idempotent, diberi marker/chain khusus aplikasi, divalidasi sebelum apply, dan tidak flush ruleset yang sudah ada.
- Management subnet hanya dapat dicapai oleh group yang berhak. Client-to-client default deny.
- DNS leak, MTU, IPv6, split/full tunnel, dan kill-switch adalah keputusan profil eksplisit.

## 7. Operasi, monitoring, dan expiry

Scheduler mempoll service status, peer/SA, handshake, counter, dan certificate expiry setiap menit atau interval yang disepakati. Expiry job menonaktifkan peer/identity secara idempotent dan mengirim alert tanpa membocorkan key. Clock/NTP host wajib sehat karena certificate dan expiry bergantung waktu.

Health result: `service_up`, `configuration_valid`, `interface_up`, `firewall_expected`, serta error code sanitized. API tidak menampilkan full config/service journal kepada role biasa.

## 8. Mock dan test

Mock mendukung create/one-time reveal/reveal denial/rotate/disable/expire, IP pool exhaustion, config validation failure, reload rollback, handshake/counter, dan strongSwan unsupported.

Test minimum:

- key generation wrapper dan redaction semua channel;
- alokasi IP concurrency/unique/reserved/pool exhaustion;
- permission/re-auth/one-time download/expiry;
- render validation, atomic update, reload failure rollback;
- idempotent create/disable/rotate dan firewall diff;
- WireGuard handshake pada host lab;
- strongSwan certificate issue/connect/revoke pada client lab sebelum production.

## 9. Recovery

Backup mencakup metadata DB, konfigurasi service, CA/key material sesuai policy, dan encryption key escrow yang dipisah. Restore dilakukan pada host terisolasi, memvalidasi permission/config, lalu merotasi key bila backup berpotensi terekspos. Jangan memulihkan peer lama tanpa memeriksa status revoke/expiry terbaru.

