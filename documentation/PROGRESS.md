# Progress Implementasi

## Ringkasan

- **Tahap aktif:** fondasi Tahap 1 telah terdeploy dan bagian read-only MikroTik pada Tahap 2 telah aktif di production; modul perangkat lain pada Tahap 2-5 tetap mock/dry-run.
- **Status keseluruhan:** deployment production dan sinkronisasi baca-saja MikroTik aktif serta terverifikasi, tetapi controlled writes dan acceptance MVP perangkat lain belum selesai.
- **Pembaruan terakhir:** 2026-07-13, setelah verifikasi akhir integrasi MikroTik production read-only.
- **Aturan status:** `selesai` hanya digunakan jika implementasi dan verifikasi relevan telah dilakukan. Dokumen target/kelas/file yang sudah ada tidak otomatis berarti fitur berfungsi.

## Audit environment lokal awal

Audit ini hanya menggambarkan workstation pengembangan, bukan VPS Ubuntu target:

| Item | Hasil | Status |
|---|---|---|
| PHP CLI | 8.5.4 | sesuai baseline PHP 8.5 |
| Composer | 2.8.12 | tersedia |
| Laravel dependency terpasang | framework melaporkan 13.19.0 pada saat audit | tersedia, dapat berubah mengikuti lockfile |
| Node.js | 22.12.0 | build lulus, tetapi dependency lint terbaru meminta minimal 22.13; upgrade lokal disarankan |
| npm | 10.9.0 | tersedia |
| MariaDB client | perintah `mariadb` tidak ditemukan pada PATH | belum tersedia/terdeteksi |
| Redis CLI | perintah `redis-cli` tidak ditemukan pada PATH | belum tersedia/terdeteksi |
| Git repository root | repository baru diinisialisasi setelah scaffold | tersedia; belum memiliki commit |

MariaDB/Redis yang tidak ditemukan di PATH hanya berlaku untuk workstation lokal. Environment production telah diverifikasi terpisah seperti dicatat di bawah.

## Status production terverifikasi

| Item | Hasil verifikasi 2026-07-13 |
|---|---|
| URL | `https://202-10-36-11.nip.io` |
| Host | Ubuntu 24.04 pada `202.10.36.11` |
| Service | 13 service wajib aktif; 0 unit systemd gagal |
| TLS | valid sampai 2026-10-10 |
| API | detail router dan 10 endpoint nested MikroTik lulus smoke test terautentikasi |
| Database | users=1, roles=6; migration `2026_07_13_010000_add_read_only_mikrotik_sync_tables.php` diterapkan |
| Backup | backup sebelum perubahan `20260713-010656`, backup saat deploy `20260713-014703`, dan backup pascaintegrasi `20260713-015516` berhasil dibuat; snapshot terakhir telah diverifikasi |
| Bind internal | service internal hanya mendengarkan pada loopback |
| Firewall publik | UFW hanya membuka 22, 80, dan 443 sesuai rencana |
| Port integrasi | RADIUS UDP 1812/1813 dan CWMP TCP 7547 sengaja ditutup sampai allowlist tersedia |
| Admin awal | credential berada di `/etc/isp-manager/initial-admin.txt`; secret tidak dicatat di dokumentasi |
| Kapasitas | RAM 1 GB; monitoring stack dan service VPN opsional dinonaktifkan |

### Snapshot MikroTik production read-only

| Item | Hasil verifikasi 2026-07-13 UTC |
|---|---|
| Preflight jaringan | VPS `202.10.36.11` ke `103.87.202.202:8728` berhasil; outbound IP sesuai allowlist |
| Router | `WIFI-KAMPUNG`; RouterOS `7.20.8 (long-term)`; model `C53UiG+5HPaxD2HPaxD`; serial `HE708G63S8M` |
| Library | `evilfreelancer/routeros-api-php` 1.7.1 |
| Latensi koneksi | 261 ms pada pengujian awal; 264 ms pada verifikasi berikutnya |
| Full sync | 1.918 ms dan 1.882 ms; kedua hasil memiliki count identik |
| Snapshot terakhir | interfaces=134, PPPoE active=115, PPP secrets=119, PPP profiles=14, Hotspot active=14, Hotspot users=3, Hotspot profiles=5, DHCP current=183, queues=180 |
| Secret handling | password PPP/Hotspot tidak diambil; credential router encrypted-at-rest dan tidak muncul dalam source, log, audit, response API, atau plaintext database |
| Router safety | tidak ada write command atau perubahan konfigurasi router; controlled writes disabled |
| Transport | RouterOS API 8728 masih non-TLS, dibatasi allowlist hanya untuk VPS; migrasi API-SSL pending/manual |
| Laporan | [laporan verifikasi akhir](reports/mikrotik-production-readonly-final-20260713-015516.md); [raw preflight](reports/mikrotik-preflight-20260713-010655.log) |

Domain `nip.io` adalah domain sementara. Migrasi ke domain milik sendiri, penerbitan ulang TLS, dan pembaruan konfigurasi origin/cookie harus dijadwalkan sebelum penggunaan jangka panjang.

## Tahap 1

| Deliverable | Status | Bukti/catatan |
|---|---|---|
| Audit environment | selesai untuk deployment awal | audit lokal dicatat dan VPS Ubuntu production telah diverifikasi 2026-07-13 |
| Arsitektur dan keputusan teknis | dokumentasi awal selesai | `ARCHITECTURE.md`; implementasi batas modul tetap harus diuji |
| Struktur repository | scaffold tersedia | `backend/` dan `frontend/` tersedia; folder operasional lain dikembangkan terpisah |
| Autentikasi Sanctum | selesai lokal | login/logout/profile/change password/reset/session API; smoke test HTTP login berhasil |
| RBAC dan permission | selesai untuk endpoint yang tersedia | enam role disediakan; negative permission test Teknisi lulus |
| Database dasar | selesai untuk deployment awal | test SQLite lulus; MariaDB production memiliki 6 migration, 1 user awal, dan 6 role |
| Dashboard dan daftar modul | selesai lokal | login/dashboard serta halaman dinamis 13 modul build; API dashboard cache/snapshot dan smoke test HTTP lulus |
| Mock adapter dasar | selesai | MikroTik, OLT, GenieACS snapshot, RADIUS dry-run, WireGuard, dan Telegram mock diuji fail-closed |
| API v1 dan response envelope | selesai untuk modul yang tersedia | 51 route versioned tersedia pada deployment, validation/auth error envelope, correlation ID, OpenAPI awal |
| Lint dan test | selesai | Pint lulus; PHPUnit 28 test/195 assertion lulus; frontend lint dan production build lulus; audit dependency Composer/npm 0 advisory |

### Dokumen yang dibuat pada baseline ini

- `README.md`
- `REQUIREMENTS.md`
- `documentation/ARCHITECTURE.md`
- `documentation/DATABASE.md`
- `documentation/API.md`
- `documentation/SECURITY.md`
- `documentation/MIKROTIK_INTEGRATION.md`
- `documentation/RADIUS_INTEGRATION.md`
- `documentation/GENIEACS_INTEGRATION.md`
- `documentation/OLT_ADAPTER.md`
- `documentation/VPN.md`
- `documentation/PROGRESS.md`

Koneksi dan sinkronisasi read-only MikroTik production sudah diverifikasi. Tidak ada perubahan konfigurasi router yang dilakukan. OLT, autentikasi FreeRADIUS melalui router, perangkat GenieACS, WireGuard, dan strongSwan belum diklaim terintegrasi dengan perangkat nyata.

## Tahap berikutnya

| Tahap | Ruang lingkup | Status |
|---|---|---|
| 2 | Router/MikroTik, polling resource, PPPoE, Hotspot | read-only production selesai dan terverifikasi; controlled writes tetap disabled, API-SSL masih pending/manual |
| 3 | Customer/package, FreeRADIUS, accounting, reconciliation | CRUD customer/package dan preview/mock tersedia; autentikasi FreeRADIUS nyata belum diuji |
| 4 | GenieACS, OLT adapter, ONT monitoring | inventory snapshot dan OLT mock tersedia; NBI/perangkat nyata belum diuji |
| 5 | VPN, Telegram, alert, audit lengkap, backup | WireGuard/Telegram mock dan alert/audit API tersedia; backup terenkripsi production berhasil dibuat/diverifikasi, tetapi restore drill dan VPN nyata belum selesai |
| 6 | test menyeluruh, hardening, deployment, dokumentasi akhir | apply Ubuntu dan verifikasi service/TLS/firewall selesai; restore drill, domain milik sendiri, integrasi nyata, dan security review akhir masih pending |

## Checklist acceptance criteria MVP

Semua item tetap terbuka sampai memiliki bukti test/artefak operasional:

- [x] Backend login/logout Sanctum dan frontend login tersedia; smoke test HTTP login lulus.
- [x] RBAC endpoint dan negative permission test berfungsi.
- [x] Router production dapat ditambahkan dengan secret encrypted dan connection test read-only lulus.
- [x] Detail router serta 10 endpoint nested menampilkan snapshot production tersimpan.
- [x] Resource MikroTik production dipolling read-only melalui job, scheduler, satu worker, dan Redis lock.
- [x] PPPoE secret tanpa password dan active session production dapat ditampilkan.
- [x] Hotspot user tanpa password dan active session production dapat ditampilkan.
- [x] Customer dan package dapat dikelola sesuai permission.
- [ ] FreeRADIUS authentication test berhasil pada lab.
- [ ] Accounting RADIUS dapat ditampilkan dengan perhitungan teruji.
- [x] Device GenieACS dapat ditampilkan melalui snapshot/mock contract.
- [x] OLT mock adapter berfungsi dan fail-closed contract test lulus.
- [x] WireGuard client mock dapat dibuat dengan encrypted-at-rest dan one-time secret handling teruji.
- [x] Telegram test message mock masuk queue tanpa token pada payload/log.
- [x] Audit log berfungsi dan secret redaction test lulus.
- [x] Queue dan scheduler berjalan di service runtime; satu worker aktif dan Redis memakai policy `noeviction`.
- [x] Backup production pascaintegrasi `20260713-015516` dibuat terenkripsi dan berhasil diverifikasi.
- [ ] Restore drill backup pada lingkungan terisolasi masih pending.
- [x] OpenAPI 3.1 awal tersedia dan YAML dapat diparse.
- [x] PHPUnit 28 test/195 assertion, lint, frontend build, dan dependency audit lulus.
- [x] Deployment Ubuntu 24.04 dan health service telah diverifikasi; 0 unit gagal.
- [ ] Rollback/restore drill masih pending.

## Verification log

| Waktu (WIB) | Pemeriksaan | Hasil |
|---|---|---|
| 2026-07-13 05:46 | versi PHP/Composer/Node/npm dan Laravel CLI | berhasil dibaca |
| 2026-07-13 05:46 | deteksi MariaDB/Redis CLI lokal | tidak ditemukan pada PATH; dicatat sebagai gap |
| 2026-07-13 05:46 | inventaris awal file dokumentasi | 11 dokumen selain progress tersedia |
| 2026-07-13 05:46 | validasi 12 file dokumentasi yang diwajibkan | seluruh file tersedia; 1.756 baris total pada snapshot pemeriksaan |
| 2026-07-13 05:46 | validasi link Markdown lokal | seluruh target lokal ditemukan |
| 2026-07-13 05:46 | scan marker mojibake umum | tidak ditemukan |
| 2026-07-13 06:08 | migration fresh + demo seed + seed ulang | lulus; 2 router, 2 OLT, 20 customer, 5 paket, data PPPoE/Hotspot/GenieACS/VPN tersedia |
| 2026-07-13 06:10 | Pint dan PHPUnit | lulus; 17 test, 102 assertion, termasuk wiring seluruh endpoint mock |
| 2026-07-13 06:11 | frontend ESLint dan Next.js production build | lulus; `/`, `/login`, `/dashboard` terprerender |
| 2026-07-13 06:12 | smoke test HTTP backend | `/up`, login, dashboard, router list, correlation ID lulus |
| 2026-07-13 06:13 | deployment/backup dry-run dan sintaks | enam script bash, YAML monitoring, deploy plan, backup plan lulus |
| 2026-07-13 06:17 | Composer/npm security audit | 0 advisory; PostCSS ditimpa ke 8.5.10 melalui lockfile/override |
| 2026-07-13 | live deployment Ubuntu 24.04 | `https://202-10-36-11.nip.io` aktif; 13 service wajib aktif dan 0 failed unit |
| 2026-07-13 | TLS, route, dan database production | TLS valid sampai 2026-10-10; 51 API route; users=1, roles=6, migrations=6 |
| 2026-07-13 | backup dan network exposure | backup encrypted `20260713-001606` terverifikasi; internal loopback-only; UFW publik hanya 22/80/443 |
| 2026-07-13 | port integrasi dan kapasitas | RADIUS 1812/1813 serta CWMP 7547 tetap ditutup; monitoring/VPN opsional disabled karena RAM 1 GB |
| 2026-07-13 UTC | MikroTik network preflight | koneksi VPS `202.10.36.11` ke `103.87.202.202:8728` dan outbound IP allowlist lulus; [raw log](reports/mikrotik-preflight-20260713-010655.log) |
| 2026-07-13 UTC | backup sebelum perubahan dan deployment | backup `20260713-010656` dan `20260713-014703` selesai sebelum migration/deploy terkait |
| 2026-07-13 UTC | koneksi dan dua full sync MikroTik | identitas `WIFI-KAMPUNG` terverifikasi; latency 261/264 ms; sync 1.918/1.882 ms dengan count identik |
| 2026-07-13 UTC | API dan quality gate pascaintegrasi | detail + 10 nested endpoint lulus; PHPUnit 28 test/195 assertion; frontend lint/build; audit Composer/npm 0 advisory |
| 2026-07-13 UTC | security dan kapasitas | credential encrypted dan tidak bocor; tanpa write command; UFW tetap 22/80/443; RAM 590 MiB used/371 MiB available; swap 667 MiB used dari 2,2 GiB |
| 2026-07-13 UTC | verifikasi akhir | semua service sehat, 0 failed unit; backup terenkripsi `20260713-015516` berhasil diverifikasi; [laporan akhir](reports/mikrotik-production-readonly-final-20260713-015516.md) |

Perintah verifikasi ulang minimum:

```bash
cd backend
vendor/bin/pint --test
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit

cd ../frontend
npm run lint
npm run build
```

SQLite hanya dipakai sebagai database test lokal. Deployment production telah memakai migration MariaDB; sebelum perubahan schema berikutnya, migration/feature test tetap harus diulang pada MariaDB dan Redis staging/test instance.
