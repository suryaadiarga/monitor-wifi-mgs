# Laporan Akhir Integrasi MikroTik Production Read-only

Tanggal verifikasi: 2026-07-13 UTC  
VPS aplikasi: `202.10.36.11`  
Router target: `103.87.202.202:8728`  
Status: **berhasil dan aktif dalam mode read-only**

## Ringkasan hasil

Integrasi MikroTik production telah dipasang sebagai jalur baca-saja untuk koneksi, sinkronisasi inventaris, penyimpanan snapshot, dan penyajian data melalui API aplikasi. Preflight jaringan, migration, dependency, deployment, dua full sync, API smoke test, automated test, frontend build, audit dependency, service health, firewall, secret handling, resource usage, dan backup terenkripsi telah diverifikasi.

Tidak ada RouterOS write command yang dijalankan dan tidak ada konfigurasi router yang diubah. Controlled writes tetap disabled.

## Boundary yang disetujui

Ruang lingkup aktif:

- menguji koneksi dan membaca identitas/resource router;
- membaca interface, PPPoE active, PPP secrets tanpa password, PPP profiles;
- membaca Hotspot active, Hotspot users tanpa password, Hotspot profiles;
- membaca DHCP current dan queues;
- menyimpan snapshot read-only;
- menampilkan detail router dan dataset nested melalui API aplikasi;
- menjalankan sinkronisasi melalui queue/scheduler dan Redis lock.

Ruang lingkup yang tidak dilakukan:

- create, update, enable, disable, disconnect, reboot, backup/export, atau raw command pada router;
- perubahan user, service, firewall, address-list, PPP, Hotspot, DHCP, queue, route, atau konfigurasi RouterOS lainnya;
- pembacaan atau penyimpanan password PPP/Hotspot;
- pengaktifan controlled writes;
- migrasi API 8728 ke API-SSL. Migrasi tersebut tetap pending dan harus dilakukan manual dalam change tersendiri.

## Preflight dan proteksi perubahan

Preflight 2026-07-13 UTC dari VPS ke RouterOS API berhasil:

- tujuan `103.87.202.202:8728` dapat dijangkau;
- outbound IP dari VPS sesuai sumber yang di-allowlist pada router;
- tidak diperlukan pembukaan port inbound baru pada VPS;
- [raw preflight log](mikrotik-preflight-20260713-010655.log) disimpan sebagai artefak.

Backup berlapis yang dibuat:

| Tahap | Snapshot |
|---|---|
| Sebelum perubahan | `20260713-010656` |
| Saat deployment | `20260713-014703` |
| Setelah integrasi dan verifikasi | `20260713-015516` |

Snapshot pascaintegrasi `20260713-015516` terenkripsi dan berhasil diverifikasi.

## File yang diubah

- Dependency: `backend/composer.json`, `backend/composer.lock`.
- Database/model: migration `backend/database/migrations/2026_07_13_010000_add_read_only_mikrotik_sync_tables.php`, `backend/app/Models/Router.php`, `RouterMetric.php`, `RouterInterface.php`, serta model snapshot `Router*` baru.
- Integrasi RouterOS: `backend/app/Adapters/Router/RouterOsAdapter.php`, `backend/app/Services/RouterOs/*`, `MikroTikService.php`, `RouterSyncService.php`, dan kontrak/exception terkait.
- Job dan command: `PollRouterJob.php`, `DispatchRouterPolls.php`, `AddRouter.php`, `SyncRouter.php`, `PruneRouterData.php`, dan `SystemHealthCommand.php`.
- API/keamanan: `RouterController.php`, `SystemHealthController.php`, `RouterResource.php`, `StoreRouterRequest.php`, `AuditLogService.php`, `SystemHealthService.php`, `AppServiceProvider.php`, `routes/api.php`, `routes/console.php`, `config/isp.php`, dan `RolePermissionSeeder.php`.
- Frontend: `frontend/src/app/dashboard/routers/[routerId]/page.tsx`, `router-data-table.tsx`, `router-monitoring.ts`, `api.ts`, dan halaman daftar module router.
- Operasional: unit queue/healthcheck pada `deployment/systemd/`, `deployment/low-memory-profile.sh`, dan `scripts/healthcheck.sh`.
- Test: test adapter, sinkronisasi, command/job, API nested, credential redaction, dan health di `backend/tests/`.
- Dokumentasi: `README.md`, `documentation/PROGRESS.md`, `MIKROTIK_INTEGRATION.md`, `SECURITY.md`, `TROUBLESHOOTING.md`, raw preflight, dan laporan ini.

## Implementasi yang terpasang

| Komponen | Versi/artefak |
|---|---|
| Library RouterOS | `evilfreelancer/routeros-api-php` 1.7.1 |
| Migration | `2026_07_13_010000_add_read_only_mikrotik_sync_tables.php` |
| Worker | satu queue worker untuk profil VPS 1 GB |
| Redis eviction policy | `noeviction` |
| Mode perangkat | production read-only; controlled writes disabled |

## Identitas router terverifikasi

| Field | Nilai |
|---|---|
| Name/identity | `WIFI-KAMPUNG` |
| RouterOS | `7.20.8 (long-term)` |
| Model | `C53UiG+5HPaxD2HPaxD` |
| Serial | `HE708G63S8M` |
| Latency awal | 261 ms |
| Latency verifikasi berikutnya | 264 ms |

## Hasil sinkronisasi

Dua full sync selesai dalam 1.918 ms dan 1.882 ms. Count kedua hasil identik, sehingga repeatability sinkronisasi pada saat pengujian terkonfirmasi.

Snapshot terverifikasi terakhir:

| Dataset | Count |
|---|---:|
| Interfaces | 134 |
| PPPoE active | 115 |
| PPP secrets | 119 |
| PPP profiles | 14 |
| Hotspot active | 14 |
| Hotspot users | 3 |
| Hotspot profiles | 5 |
| DHCP current | 183 |
| Queues | 180 |

DHCP current sebelumnya teramati 184 lalu berubah alami menjadi 183 pada snapshot terakhir. Tidak ada perintah aplikasi yang menyebabkan perubahan tersebut. Count inventaris adalah observasi waktu nyata, bukan target konfigurasi.

Password pada 119 PPP secrets dan 3 Hotspot users tidak diminta, tidak dipersistenkan, dan tidak dikembalikan oleh API aplikasi.

## Verifikasi aplikasi

- endpoint detail router lulus smoke test terautentikasi;
- 10 endpoint nested MikroTik lulus smoke test;
- dua full sync menghasilkan count identik;
- backend: 28 test dan 195 assertion lulus;
- frontend lint lulus;
- frontend production build lulus;
- Composer dependency audit: 0 advisory;
- npm dependency audit: 0 advisory.

## Keamanan dan exposure jaringan

- credential router encrypted-at-rest;
- secret tidak ditemukan dalam source code, application log, audit record, response API, atau plaintext database;
- tidak ada password, secret, atau token dalam laporan ini;
- UFW VPS tidak berubah: inbound yang diizinkan hanya TCP 22, 80, dan 443;
- tidak ada inbound allow untuk 1812, 1813, 7547, atau 8728;
- koneksi RouterOS adalah outbound dari VPS menuju router;
- API 8728 masih non-TLS dan dibatasi allowlist hanya untuk IP VPS;
- API-SSL dengan certificate/trust verification adalah mitigasi lanjutan yang wajib sebelum perluasan akses atau pertimbangan controlled writes.

## Health dan kapasitas VPS

| Resource | Sebelum | Setelah |
|---|---:|---:|
| RAM used | 505 MiB | 590 MiB |
| RAM available | 456 MiB | 371 MiB |
| Swap total | 2,2 GiB | 2,2 GiB |
| Swap used | - | 667 MiB |

Seluruh service wajib sehat dan tidak ada unit systemd gagal pada verifikasi akhir. Profil satu worker dipertahankan agar konsumsi memori sesuai kapasitas VPS.

## Status akhir dan tindak lanjut

Integrasi production read-only dinyatakan selesai untuk scope yang disebutkan dalam laporan ini. Operasi baca-saja dan penyajian snapshot dapat digunakan. Batas berikut tetap berlaku:

1. controlled writes tetap disabled;
2. jangan memperluas allowlist RouterOS API ke sumber selain VPS yang disetujui;
3. migrasi API 8728 ke API-SSL harus dilakukan manual dan diuji certificate/host trust-nya;
4. perubahan router apa pun membutuhkan scope, backup, preview, permission, audit, idempotency, dan pengujian lab terpisah;
5. count dinamis harus dinilai bersama `observed_at`, status dataset, dan error sync, bukan dipaksa agar sama dengan snapshot laporan.
