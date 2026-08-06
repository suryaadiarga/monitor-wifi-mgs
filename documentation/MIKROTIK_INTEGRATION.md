# Integrasi MikroTik

## Status

Integrasi Tahap 2 **read-only telah aktif dan diverifikasi terhadap router production** pada 2026-07-13 UTC. Ruang lingkupnya hanya koneksi, pembacaan inventaris, sinkronisasi snapshot, dan penyajian data melalui API aplikasi. Controlled writes tetap dinonaktifkan; tidak ada write command atau perubahan konfigurasi yang dilakukan pada router selama preflight, deployment, sinkronisasi, maupun verifikasi akhir.

### Bukti production 2026-07-13 UTC

| Item | Hasil |
|---|---|
| Jalur koneksi | VPS `202.10.36.11` ke RouterOS API `103.87.202.202:8728` berhasil; outbound IP VPS sesuai allowlist |
| Identitas | `WIFI-KAMPUNG` |
| RouterOS | `7.20.8 (long-term)` |
| Perangkat | model `C53UiG+5HPaxD2HPaxD`, serial `HE708G63S8M` |
| Client API | `evilfreelancer/routeros-api-php` 1.7.1 |
| Migration | `2026_07_13_010000_add_read_only_mikrotik_sync_tables.php` |
| Latensi | 261 ms pada koneksi awal; 264 ms pada verifikasi berikutnya |
| Full sync | 1.918 ms dan 1.882 ms; count kedua sinkronisasi identik |
| Snapshot terakhir | interfaces=134, PPPoE active=115, PPP secrets=119, PPP profiles=14, Hotspot active=14, Hotspot users=3, Hotspot profiles=5, DHCP current=183, queues=180 |
| API aplikasi | detail router dan 10 endpoint nested lulus smoke test terautentikasi |
| Quality gate | PHPUnit 28 test/195 assertion, frontend lint/build, Composer/npm audit 0 advisory |

Jumlah DHCP bersifat dinamis: observasi sebelumnya 184 lalu berubah alami menjadi 183 pada snapshot verifikasi terakhir. Perubahan count tersebut bukan hasil perintah aplikasi. Password pada PPP secret dan Hotspot user tidak pernah diambil atau disimpan dalam snapshot.

Artefak: [raw network preflight](reports/mikrotik-preflight-20260713-010655.log) dan [laporan verifikasi akhir](reports/mikrotik-production-readonly-final-20260713-015516.md).

## 1. Boundary dan komponen

Laravel berkomunikasi ke RouterOS melalui `RouterAdapterInterface`. Controller tidak boleh membuka koneksi langsung.

```text
API -> MikroTikService -> DeviceCommand/Poll Job -> RouterAdapterInterface
                                         |-> RouterOsApiAdapter
                                         |-> MockRouterAdapter
```

Boundary service yang digunakan/dipertahankan:

- `MikroTikService`: use case konfigurasi dan normalisasi error.
- `RouterMonitoringService`: jadwal polling, snapshot, freshness, circuit breaker.
- `RouterCredentialService`: decrypt sesaat sebelum koneksi, rotate, redaction.
- `DeviceCommandService`: preview/apply, idempotency, command/audit history.

Adapter konkret dipilih melalui `adapter`/capability router dan dependency injection, bukan `if vendor` di controller.

## 2. Kontrak adapter

Interface dipecah per kapabilitas agar router/versi yang tidak mendukung fitur tertentu dapat menolaknya dengan jelas:

```text
testConnection(context): RouterIdentity
getSystemResource(context): RouterResourceSnapshot
getInterfaces(context): list<RouterInterfaceSnapshot>
getInterfaceTraffic(context, interfaceIds): list<TrafficSample>
getPppActive(context): list<PppSessionSnapshot>
getHotspotActive(context): list<HotspotSessionSnapshot>
preview(command): CommandPreview
execute(command): CommandResult
```

`context` memuat endpoint, TLS policy, timeout, correlation ID, dan credential handle. DTO hasil tidak membawa object/client vendor. Capability minimum dilaporkan saat connection test, misalnya `api_ssl`, `temperature`, `traceroute`, `backup`, `ppp_secret_write`.

## 3. Koneksi dan TLS

Kondisi production saat ini adalah RouterOS API pada port 8728 tanpa TLS. Risiko ini dibatasi dengan allowlist sumber tunggal untuk VPS, credential least-privilege read-only, controlled writes disabled, dan tidak ada rule inbound 8728 pada UFW VPS. Kondisi ini adalah pengecualian sementara yang tercatat; migrasi ke API-SSL harus dilakukan manual setelah sertifikat dan trust policy router siap.

- API SSL diprioritaskan. API plaintext hanya diperbolehkan pada management network/VPN dengan flag eksplisit dan warning.
- Host, port, username, password ciphertext, TLS mode, CA/fingerprint opsional, connect/read timeout, dan adapter disimpan per router.
- Certificate/host verification wajib production. Mode insecure hanya lab, default off, terlihat di UI, dan diaudit.
- Timeout awal: connect 3-5 detik, operation 10-30 detik sesuai aksi. Backup/export mempunyai timeout terpisah.
- Retry hanya untuk read idempotent atau command dengan idempotency/read-back yang jelas; jangan mengulang mutasi buta.
- Log hanya router ID, host yang telah diizinkan kebijakan logging, operation, durasi, status/error code; tidak ada username/password/raw sentence sensitif.

## 4. Polling

| Dataset | Interval awal | Penyimpanan |
|---|---|---|
| identity/status | 1 menit | current + status history/alert |
| resource CPU/RAM/storage/uptime | 1-5 menit | current + time series ber-retensi |
| interface running dan traffic | 1 menit | current + time series/agregasi |
| PPP/Hotspot active | 1 menit | current session reconciliation |
| route/address/DHCP/queue/netwatch | on demand/5-15 menit | snapshot sesuai kebutuhan |

Scheduler memberikan jitter dan mengirim job ke queue perangkat. Job mengambil Redis lock:

```text
router:{router_id}:poll:{dataset}
router:{router_id}:command
```

Lock mempunyai TTL lebih panjang sedikit dari timeout dan release aman. Maintenance mode menghentikan polling/command non-darurat. Setelah kegagalan berulang, circuit breaker mengurangi probe; sukses probe menutup breaker. Dashboard tetap menampilkan data terakhir dengan `observed_at` dan `stale=true`.

## 5. Normalisasi data

- ID eksternal (`.id`) hanya valid dalam router dan dataset terkait; jangan mengeksposnya sebagai primary key aplikasi.
- Nilai RouterOS seperti `10M/20M`, duration, boolean, byte, temperature diubah ke tipe internal sebelum disimpan.
- Counter rollover/reset dideteksi; rate dihitung dari dua sample valid beserta interval.
- Field yang tidak tersedia disimpan `null` dengan capability/quality flag, bukan `0` palsu.
- Waktu router dan server dibandingkan pada connection test; drift besar membuat warning.

## 6. PPPoE dan Hotspot

Source of truth harus eksplisit per account: `local`, `radius`, atau `application_managed`. Reconciliation membandingkan key stabil dan hash state non-secret:

- sama;
- hanya aplikasi;
- hanya MikroTik;
- hanya RADIUS;
- profile berbeda;
- password tidak dapat diverifikasi;
- disabled berbeda.

Password tidak dibaca kembali dari RouterOS untuk dibandingkan. Status “tidak dapat diverifikasi” lebih benar daripada menganggap sama/berbeda. Apply reconciliation menggunakan preview, item pilihan, idempotency key, dan audit per target.

Daftar fitur mutasi di bawah ini masih merupakan rancangan dan **belum diaktifkan pada production**: create/update/enable/disable PPP Secret atau Hotspot user, ubah profile, disconnect active session, simple queue terstruktur, address-list terstruktur, backup/export, serta script yang sudah didaftarkan administrator. Tidak ada endpoint raw command.

## 7. Command lifecycle untuk controlled writes mendatang

Lifecycle ini belum diaktifkan terhadap router production. Aktivasi memerlukan persetujuan terpisah, pengujian lab, akun dengan scope yang sesuai, API-SSL, preview, dan audit lengkap.

1. Backend memeriksa permission dan scope router/customer.
2. Service membuat preview yang menyebut target, state sekarang, perubahan, risiko, dan apakah read-back tersedia.
3. UI meminta konfirmasi; bulk menunjukkan jumlah/diff/error potensial.
4. Apply memakai preview belum kedaluwarsa dan `Idempotency-Key`.
5. Job mengambil command lock dan mengecek maintenance/circuit/credential status.
6. Adapter mengeksekusi operasi terstruktur, menyimpan request/result tersanitasi.
7. Bila aman, adapter read-back state dan menandai `verified`, `unverified`, atau `mismatch`.
8. Audit success/failure dan event refresh dibuat dengan correlation ID yang sama.

Error publik diklasifikasikan `connection_timeout`, `tls_verification_failed`, `authentication_failed`, `permission_denied`, `unsupported`, `validation_failed`, `device_rejected`, `verification_mismatch`. Pesan detail vendor disanitasi dan hanya tersedia bagi operator berizin.

## 8. Backup/export router

- Nama file ditentukan server, bukan input shell pengguna.
- Binary backup dan text export dianggap sensitif, disimpan privat/terenkripsi, checksum dan expiry dicatat.
- Download dari router memakai timeout/size limit dan file sementara berpermission minimum.
- Export disanitasi bila akan ditampilkan; raw export tidak masuk log/audit.
- Restore bukan bagian mutasi awal dan memerlukan prosedur terpisah serta persetujuan eksplisit.

## 9. Mock adapter

`MockRouterAdapter` harus menyediakan fixture deterministic untuk dua router, interface, metric, PPP/Hotspot active, drift reconciliation, dan mode error (`timeout`, `auth_failed`, `unsupported`, `partial`). Mutasi mengubah state mock terisolasi atau fake repository sehingga read-back dapat diuji. Mock ditandai jelas di UI/API dan tidak menerima credential.

## 10. Test dan kriteria aktivasi

Gate read-only production telah lulus: preflight koneksi, identitas/capability, dua full sync konsisten, secret redaction, API detail + 10 endpoint nested, 28 backend test/195 assertion, frontend lint/build, dependency audit tanpa advisory, service health, dan backup terenkripsi pascaintegrasi.

Sebelum router lab:

- unit test parser/normalizer/duration/rate limit/counter rollover;
- contract test yang sama untuk mock dan adapter nyata;
- test timeout/retry/lock/circuit/idempotency/audit/redaction;
- permission test setiap action dan negative scope;
- test bulk preview/partial failure/reconciliation.

Sebelum controlled writes diaktifkan, uji API-SSL/TLS, versi RouterOS yang didukung, dan seluruh mutasi terkontrol pada router lab. Catat model/RouterOS/fitur yang lulus. Adapter write harus tetap feature-flagged/dry-run sampai bukti dan persetujuan operasional tersedia.
