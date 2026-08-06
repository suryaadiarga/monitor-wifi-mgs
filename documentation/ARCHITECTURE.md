# Arsitektur Sistem

## Status dokumen

Keputusan di dokumen ini adalah baseline implementasi. **Tahap 1 sedang berjalan**; diagram dan kontrak menjelaskan target arsitektur, bukan klaim bahwa semua komponen telah tersedia.

## 1. Gaya arsitektur

Sistem menggunakan **modular monolith** pada backend Laravel. Satu unit deployment menjaga operasi tetap sederhana, sedangkan batas modul, service layer, event, job, dan adapter mencegah controller atau model menjadi tempat seluruh logika.

```text
Browser
  |
  v
Nginx (HTTPS, headers, rate/size limits)
  |-----------------------------|
  v                             v
Next.js 16                  Laravel 13 API
                                |
                 |--------------|----------------|
                 v              v                v
              MariaDB         Redis        Object/file storage
                 ^              ^
                 |              |
        Queue workers + Scheduler
                 |
     |-----------|-----------|-----------|-----------|
     v           v           v           v           v
 MikroTik    FreeRADIUS   GenieACS NBI  OLT adapters  VPN/Telegram
 RouterOS API  + SQL       -> MongoDB   SNMP/SSH/API  OS/API adapters
```

Next.js tidak boleh memegang credential perangkat atau mengakses perangkat secara langsung. Laravel menjadi policy enforcement point. Request halaman membaca data MariaDB/cache; komunikasi lambat dengan perangkat dikerjakan job background.

## 2. Batas modul backend

| Modul | Tanggung jawab | Dependensi keluar yang diizinkan |
|---|---|---|
| Identity | user, session, role, permission, scope | Audit, Notification |
| Inventory | area, POP, ODP, router, OLT, ONT | Device Integration, Audit |
| Customer | pelanggan, layanan, assignment reseller/teknisi | Package, Network Access, Audit |
| Package | speed profile, harga, mapping MikroTik/RADIUS | MikroTik, RADIUS melalui service |
| MikroTik | koneksi, snapshot, PPPoE, Hotspot, queue, command whitelist | Router adapter, Audit, Alert |
| RADIUS | NAS, user/group sync, accounting, auth diagnostic | DB connection RADIUS, CoA adapter |
| ACS | inventory TR-069, mapping, task/fault/provision | ACS adapter/GenieACS NBI |
| OLT | inventory PON/ONU, optical, provisioning plan | OLT adapter |
| VPN | WireGuard/IKEv2 server dan client lifecycle | VPN adapter, Secret service |
| Monitoring | metric snapshots, health, jobs, alert evaluation | Queue, Redis, Notification |
| Notification | Telegram setting, template, delivery history | Telegram adapter |
| Audit | append-oriented audit dan command history | Database/log sanitizer |
| Backup | plan, run, verify, retention, restore metadata | OS/process adapter, Notification |
| System | setting, health, maintenance, feature flags | Semua service via kontrak read-only |

Interaksi antar modul dilakukan melalui application service atau domain event, bukan query langsung yang menyebar. Modul boleh berbagi identifier, tetapi tidak boleh membaca tabel modul lain untuk melewati policy.

## 3. Lapisan backend

```text
HTTP/API: Controller -> FormRequest -> Policy/Gate -> API Resource
Application: Use case/service -> transaction -> event/job dispatch
Domain: entity/value object/rule/interface
Infrastructure: Eloquent repository, Redis, HTTP client, RouterOS, SNMP/SSH, OS service
```

- **Controller** hanya menerjemahkan HTTP dan tidak memanggil perangkat.
- **Service/use case** mengatur transaksi, idempotency, authorization context, audit, dan dispatch job.
- **Adapter** menerapkan interface vendor; hasil dinormalisasi ke DTO internal.
- **Job** memiliki timeout, retry/backoff, unique key/lock, correlation ID, dan pencatatan status.
- **Policy** menggabungkan permission dan scope objek (reseller/assignment/area).

Interface minimum:

```php
interface RouterAdapterInterface {}
interface OltAdapterInterface {}
interface AcsAdapterInterface {}
interface VpnAdapterInterface {}
```

Kontrak konkret akan berkembang per use case, bukan menjadi satu interface besar. Adapter `Mock*` wajib deterministic, dapat mensimulasikan timeout/error, dan tidak memerlukan secret.

## 4. Alur baca monitoring

1. Scheduler membuat job polling menurut interval, maintenance mode, dan jitter.
2. Job mengambil Redis lock `poll:{device_type}:{device_id}:{metric}`.
3. Adapter membaca perangkat dengan timeout dan retry terbatas.
4. Data divalidasi, dinormalisasi, lalu disimpan sebagai current snapshot dan histori ber-retensi.
5. Evaluator membandingkan rule serta status sebelumnya untuk menghindari alert berulang.
6. API dashboard membaca agregat cache/DB dan menyertakan `observed_at`/status stale.

Jika perangkat gagal berulang, circuit breaker berstatus `open`; probe berikutnya memakai interval lebih jarang. Membuka halaman tidak pernah memaksa polling sinkron.

## 5. Alur mutasi perangkat

```text
UI confirmation
 -> POST preview (validasi + permission + diff, tanpa perangkat)
 -> token preview singkat dan terikat actor/input
 -> POST apply + Idempotency-Key
 -> command record queued
 -> adapter execution
 -> sanitized result + audit success/failure
 -> event/cache refresh
```

Untuk aksi tunggal berisiko rendah, preview dapat berupa halaman konfirmasi detail. Bulk action dan provisioning OLT wajib menggunakan token preview. Factory reset ONT ditambah challenge serial number. Tidak ada terminal bebas; script MikroTik dibatasi allowlist.

## 6. Data dan konsistensi

- MariaDB adalah source of truth aplikasi. FreeRADIUS memakai schema/database SQL terpisah dan koneksi Laravel khusus.
- MongoDB hanya dimiliki GenieACS; aplikasi mengakses perangkat melalui NBI, bukan menulis MongoDB secara langsung.
- Redis menyimpan cache, queue, session, lock, throttle, dan state circuit breaker; Redis bukan source of truth.
- Operasi DB lokal memakai transaction. Operasi lintas perangkat memakai saga sederhana: intent/command dicatat, job dieksekusi, hasil disimpan, lalu reconciliation memperlihatkan drift.
- Secret memakai enkripsi aplikasi dengan dukungan key rotation/versioning; ciphertext tidak dapat dicari dan tidak dimasukkan ke cache/log.
- Timestamp disimpan UTC dan ditampilkan dalam zona waktu pengguna (default Asia/Jakarta).

## 7. REST API dan frontend

- Namespace `/api/v1`; JSON envelope konsisten dan error memakai status HTTP yang benar.
- Sanctum menjadi mekanisme autentikasi. Untuk web same-site diprioritaskan cookie HttpOnly + CSRF; token personal hanya untuk client yang disetujui.
- Next.js memakai server/client component sesuai kebutuhan, tetapi authorization selalu diulang pada backend.
- Table state (search/filter/sort/page/column) dikirim sebagai query terkontrol; field filter/sort di-allowlist.
- Realtime awal menggunakan polling cache terjadwal. WebSocket hanya ditambahkan jika manfaat dan operasionalnya terukur.

## 8. Reliability dan observability

- Queue dipisah minimal: `critical`, `device-router`, `device-olt`, `acs`, `radius`, `vpn`, `notifications`, `backups`, `default`.
- Satu perangkat tidak boleh memonopoli queue; concurrency dan rate limit diatur per perangkat/site.
- Setiap request/job/command membawa UUID correlation ID. Log terstruktur berisi actor ID dan target ID, tidak berisi secret.
- `jobs_history` dan `device_commands` menyimpan status operasional tersanitasi; audit log menyimpan aktivitas keamanan/bisnis.
- Health endpoint memisahkan liveness (proses hidup) dan readiness (DB/Redis/queue siap). Detail internal hanya untuk permission/system monitor.

## 9. Keputusan teknis (ADR ringkas)

| ID | Keputusan | Alasan dan konsekuensi |
|---|---|---|
| ADR-001 | Modular monolith | Lebih mudah dioperasikan daripada microservices; disiplin batas modul wajib dijaga melalui test dan review |
| ADR-002 | Laravel 13 + PHP 8.5 | Baseline proyek; Ubuntu 24.04 default berbeda sehingga deployment harus memasang/pin repository PHP tepercaya |
| ADR-003 | Next.js 16 + Node.js 22 | UI modern dan TypeScript; build artifact dijalankan sebagai service terpisah |
| ADR-004 | MariaDB sebagai database utama | Cocok untuk transactional core dan SQL FreeRADIUS; core dan RADIUS dipisahkan logical database/user |
| ADR-005 | Redis wajib | Menyatukan cache/queue/session/lock/throttle; butuh persistence/monitoring dan tidak boleh terekspos publik |
| ADR-006 | Native core, RADIUS, GenieACS | Sesuai kebutuhan operasi; monitoring opsional dapat memakai Docker |
| ADR-007 | Async device I/O | Melindungi request/UI dari perangkat lambat; data selalu menyertakan freshness timestamp |
| ADR-008 | Adapter + mock first | Vendor/protokol berubah dan perangkat nyata belum selalu tersedia; command nyata disabled sampai diverifikasi |
| ADR-009 | Preview/apply untuk mutasi besar | Mengurangi salah konfigurasi; perlu penyimpanan intent/token dan idempotency |
| ADR-010 | GenieACS hanya melalui NBI | Menjaga boundary dan menghindari ketergantungan schema MongoDB internal |
| ADR-011 | Polling sebagai realtime awal | Operasional lebih sederhana; WebSocket merupakan optimasi kemudian, bukan dependency MVP |
| ADR-012 | Secret write-only | API hanya menerima create/rotate, read memberi flag keberadaan/fingerprint nonrahasia |
| ADR-013 | Soft delete selektif | Data master dapat dipulihkan; audit, accounting, metric, dan command memakai retention/archival, bukan soft delete umum |
| ADR-014 | UTC at rest | Konsisten lintas service; tampilan dikonversi ke Asia/Jakarta/user timezone |

## 10. Topologi production minimum

Satu VPS dapat menjalankan Nginx, PHP-FPM, Laravel worker/scheduler, Next.js, MariaDB, Redis, FreeRADIUS, GenieACS, MongoDB, WireGuard, dan strongSwan untuk instalasi kecil. Namun isolasi user/service, bind address private/localhost, firewall, resource limit, dan backup tetap wajib. Untuk skala atau risiko lebih tinggi, database/ACS/monitoring dapat dipisah tanpa mengubah batas aplikasi.

Port publik default hanya 80/443 dan SSH yang dibatasi. UDP WireGuard serta 500/4500 IKEv2 dibuka hanya bila fitur aktif. RADIUS, database, Redis, MongoDB, GenieACS internal, MikroTik API, SNMP, dan OLT management menggunakan private network/VPN/allowlist.

## 11. Risiko yang masih terbuka

- Library RouterOS, OpenAPI, RBAC, chart, QR/PDF, dan WebSocket belum dikunci sebelum proof-of-concept dan review maintenance/security.
- Command/OID/path vendor OLT serta parameter TR-069 harus diuji per model/firmware.
- Kapasitas satu VPS tergantung jumlah router/ONT, frekuensi polling, dan retensi metric; load test diperlukan.
- Strategi key rotation, HA Redis/MariaDB, dan disaster recovery lintas host harus dituntaskan sebelum production kritis.

