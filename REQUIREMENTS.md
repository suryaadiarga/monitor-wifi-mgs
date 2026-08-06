# Kebutuhan Sistem ISP Terpadu

Dokumen ini adalah baseline ruang lingkup. Kata **wajib** berarti acceptance criteria, bukan pernyataan bahwa fitur sudah selesai. Status implementasi aktual dicatat di [documentation/PROGRESS.md](documentation/PROGRESS.md).

## 1. Tujuan dan aktor

Aplikasi menjadi pusat kendali operasional ISP untuk monitoring dan manajemen jaringan, pelanggan, AAA, ACS, OLT, VPN, notifikasi, serta audit. Aktor awal:

| Peran | Ruang lingkup utama | Pembatasan penting |
|---|---|---|
| Super Admin | Semua fitur, user, permission, setting, credential | Aksi berbahaya tetap wajib konfirmasi dan audit |
| Administrator | Router, pelanggan, PPPoE, Hotspot, RADIUS, ACS, OLT, VPN | Tidak otomatis boleh mengubah RBAC global |
| NOC | Monitoring dan diagnostik terbatas | Tidak boleh menghapus pelanggan atau menjalankan terminal bebas |
| Teknisi | Pelanggan yang ditugaskan, ONT/OLT, lokasi, tiket | Tidak boleh melihat secret perangkat |
| Finance | Pelanggan, paket, invoice, pembayaran | Tidak boleh mengubah konfigurasi jaringan |
| Reseller | Pelanggan miliknya sendiri | Scope tenant wajib ditegakkan di query/policy backend |

Permission diperiksa pada setiap endpoint dan job sensitif. Menyembunyikan menu frontend bukan kontrol keamanan.

## 2. Kebutuhan fungsional prioritas MVP

### Autentikasi dan platform

- Login, logout, perubahan/lupa password, pengelolaan session, rate limit login, audit login berhasil/gagal.
- Sanctum untuk API, RBAC berbasis role/permission, policy dan tenant scope reseller.
- REST API berversi `/api/v1`, response konsisten, dokumentasi OpenAPI.
- Queue, scheduler, event, cache, session, distributed lock melalui Redis.
- Audit log append-oriented dengan correlation ID dan redaksi secret.

### Dashboard

- Ringkasan router, OLT, pelanggan, PPPoE, Hotspot, ONT, GenieACS, VPN, trafik, resource, alert, dan aktivitas admin.
- Filter router, area, POP, reseller, paket, status, dan rentang tanggal.
- Membaca snapshot/cache; tidak membuat koneksi ke perangkat saat request halaman.

### MikroTik, PPPoE, dan Hotspot

- Multi-router dengan credential terenkripsi, connection test, timeout, retry terbatas, lock polling, status/last seen.
- Snapshot resource, interface, trafik, route, address, lease, PPP, Hotspot, queue, log, dan Netwatch sesuai kapabilitas perangkat.
- Kelola PPP Secret/Hotspot user, profile, simple queue, address-list, disconnect session, backup/export, serta script whitelist.
- Reconciliation data aplikasi, MikroTik, dan RADIUS.
- Voucher batch, import/export, masa aktif, limit, MAC/IP binding, dan PDF dari template.

### Pelanggan dan paket

- CRUD pelanggan dengan area/POP/ODP/OLT/PON/ONT/router/paket/reseller serta status Prospek, Aktif, Isolir, Suspend, Berhenti.
- Import/export CSV, search/filter/pagination, histori, dan bulk update dua langkah (preview/apply).
- Paket memetakan speed/burst/priority/harga ke PPP Profile, rate-limit MikroTik, dan `radgroupreply`.
- Perubahan paket tidak boleh otomatis mengubah seluruh pelanggan tanpa pilihan scope dan preview.

### FreeRADIUS

- SQL MariaDB dengan tabel standar `radcheck`, `radreply`, `radgroupcheck`, `radgroupreply`, `radusergroup`, `radacct`, `radpostauth`, `nas`.
- NAS/user/group, accounting, auth log, CoA/Disconnect bila didukung, simultaneous-use, expiration, timeout, rate limit, usage harian/bulanan.
- Diagnostik service, port, database, NAS, auth test, accept/reject terakhir.

### GenieACS dan OLT

- GenieACS NBI: inventory, status, parameter penting, task/fault/provision/tag, reboot, refresh, set parameter.
- Parameter mapping dipilih berdasarkan vendor, product class, dan software version.
- Factory reset memerlukan permission khusus dan pengetikan ulang serial number.
- OLT menggunakan adapter Huawei/ZTE/FiberHome/Mock; SNMP/SSH/API, sedangkan Telnet legacy default nonaktif.
- Monitoring board/slot/PON/ONU/optical/status; mutasi ONU melalui job, preview, audit, serta command history tersanitasi.

### VPN, alert, Telegram, dan backup

- WireGuard client, key/PSK, alokasi IP, config satu kali, QR, expiry, handshake, RX/TX.
- strongSwan IKEv2 mengutamakan certificate, menyediakan PSK hanya untuk kompatibilitas.
- Alert deduplikasi berbasis perubahan status; Telegram memakai queue, quiet hours, dan token terenkripsi.
- Backup MariaDB, MongoDB, aplikasi, environment terenkripsi, konfigurasi layanan/VPN, dan backup MikroTik; retensi harian/mingguan/bulanan serta restore terdokumentasi.

## 3. Kebutuhan nonfungsional

| ID | Kebutuhan | Kriteria minimum |
|---|---|---|
| NFR-01 | Keamanan | HTTPS production, deny-by-default firewall, encryption at rest untuk secret, hashing password, CSRF/rate limit/input validation/security headers |
| NFR-02 | Auditabilitas | Setiap mutasi sensitif memiliki actor, target, before/after tersanitasi, status, IP, user-agent, timestamp, correlation ID |
| NFR-03 | Ketahanan | Timeout, retry dengan exponential backoff, circuit breaker sederhana, idempotency, Redis lock, maintenance mode perangkat |
| NFR-04 | Kinerja | Request UI membaca DB/cache; polling perangkat dilakukan background; pagination wajib untuk daftar besar |
| NFR-05 | Isolasi data | Finance/teknisi/reseller dibatasi sesuai role, assignment, dan ownership pada backend |
| NFR-06 | Operasional | Health check, structured log, queue/scheduler supervision, backup terverifikasi, log rotation |
| NFR-07 | UX | Bahasa Indonesia, responsif, dark mode, loading/empty/error state, tabel search/filter/sort/page/column/export |
| NFR-08 | Testability | Mock untuk MikroTik, GenieACS, OLT, VPN; tidak ada test destruktif ke production |
| NFR-09 | Portabilitas | Ubuntu Server 24.04 LTS; core/FreeRADIUS/GenieACS native, monitoring opsional boleh Docker |
| NFR-10 | Observabilitas | Correlation ID lintas HTTP/job/adapter dan histori job/command; Prometheus/Grafana/Loki opsional |

## 4. Polling dan alert

Interval awal dapat diubah melalui setting, dengan jitter agar tidak terjadi lonjakan bersamaan:

| Data | Interval awal |
|---|---|
| Status router, RADIUS health, VPN status | 1 menit |
| Resource router, OLT status, GenieACS last inform | 1-5 menit |
| Trafik interface | 1 menit |
| Optical ONU | 5-15 menit |

Queue dipisahkan per domain/perangkat. Kegagalan berulang membuka circuit breaker; alert baru dikirim saat status berubah atau setelah reminder window yang dikonfigurasi.

## 5. Batas keamanan dan operasional

- Tidak ada secret dalam repository, response read, log, audit, exception, atau export.
- Tidak ada SQLite untuk production dan tidak ada database/service internal yang dipublikasikan ke Internet.
- Tidak ada `chmod 777`, mematikan firewall, terminal MikroTik bebas, mass update tanpa preview, atau command OLT yang belum teruji.
- Port UDP RADIUS 1812/1813 hanya menerima NAS terdaftar. MikroTik API, SNMP, OLT management, Redis, MariaDB, MongoDB, dan GenieACS internal dibatasi jaringan privat/VPN/allowlist.
- Perubahan besar pada server wajib melewati detect, preflight report, backup, review rencana, dan persetujuan eksplisit sebelum aksi destruktif.

## 6. Tahapan delivery

1. **Tahap 1:** audit environment, arsitektur/repository, autentikasi/RBAC, database dasar, dashboard dummy.
2. **Tahap 2:** router/MikroTik, resource polling, PPPoE, Hotspot.
3. **Tahap 3:** pelanggan/paket, FreeRADIUS, accounting, reconciliation.
4. **Tahap 4:** GenieACS, OLT adapter, ONT monitoring.
5. **Tahap 5:** VPN, Telegram, alert, audit, backup.
6. **Tahap 6:** testing menyeluruh, hardening, deployment, dokumentasi akhir.

Setiap tahap wajib mencatat file yang berubah, menjalankan lint/test/build relevan, memperbaiki error, dan memperbarui progress tanpa membuat klaim yang belum diuji.

## 7. Definition of Done MVP

MVP baru selesai jika 20 acceptance criteria dalam spesifikasi utama terbukti melalui test/artefak verifikasi: login dan RBAC; router/add/test/status/resource; PPPoE dan Hotspot; pelanggan/paket; RADIUS auth/accounting; GenieACS inventory; OLT mock; WireGuard provisioning; Telegram test; audit; queue/scheduler; backup; OpenAPI; test utama; serta dokumentasi deployment. Checklist buktinya dikelola di `documentation/PROGRESS.md`.

