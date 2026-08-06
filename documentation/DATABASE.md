# Desain Database

## Status

Dokumen ini menetapkan skema target awal. Migration aktual harus menjadi sumber kebenaran setelah dibuat dan diuji. **Tahap 1 masih berjalan**, sehingga daftar tabel di bawah tidak berarti seluruh tabel sudah tersedia.

## 1. Mesin dan pemisahan data

MariaDB digunakan untuk data aplikasi dan FreeRADIUS, dengan logical database dan user terpisah:

- `isp_core`: dimiliki aplikasi; user runtime hanya memiliki hak yang diperlukan.
- `radius`: tabel standar FreeRADIUS; daemon RADIUS dan aplikasi memakai user/hak terpisah.
- MongoDB dimiliki GenieACS dan tidak diakses langsung oleh core application.
- Redis bukan database permanen; hanya cache, queue, session, lock, throttle, dan circuit state.

Nama database dapat dikonfigurasi melalui environment. Tidak ada SQLite dalam konfigurasi production.

## 2. Konvensi

- Primary key internal menggunakan `BIGINT UNSIGNED`; identifier yang terekspos publik memakai `public_id` ULID/UUID unik agar ID berurutan tidak menjadi batas keamanan.
- Semua foreign key diindeks. Unique constraint dipakai untuk natural key yang stabil dan selalu mempertimbangkan scope/soft delete.
- Timestamp disimpan UTC menggunakan presisi konsisten. Field perangkat yang bisa tidak tersedia bersifat nullable dan disertai `observed_at` bila relevan.
- Uang disimpan sebagai `DECIMAL(15,2)` dan bandwidth sebagai integer bit per second, bukan string format RouterOS.
- IP menggunakan `VARBINARY(16)` atau tipe/string tervalidasi yang mendukung IPv4/IPv6; CIDR/host tidak dicampur dalam satu field tanpa value object.
- JSON hanya untuk payload vendor yang jarang difilter. Field yang sering dicari harus menjadi kolom normal dan terindeks.
- Soft delete hanya untuk master data yang wajar dipulihkan. Audit, accounting, metric, command, dan history memakai retention/archive dan tidak dapat diedit melalui CRUD biasa.
- Optimistic concurrency (`lock_version` atau pemeriksaan `updated_at`) digunakan pada setting dan resource yang rawan perubahan bersamaan.

## 3. Secret dan data sensitif

Kolom password/token/key/community tidak menyimpan plaintext. Pola penyimpanan:

```text
credential_ciphertext   BLOB/TEXT encrypted
credential_key_version VARCHAR(32)
credential_updated_at  TIMESTAMP
credential_fingerprint VARCHAR(64) nullable, non-secret
```

Laravel application encryption dapat menjadi baseline Tahap 1, tetapi access harus melalui `SecretService` agar key rotation dan envelope encryption dapat ditambahkan. Model harus menyembunyikan ciphertext dari serialization. API read hanya memberi `has_credential`, `updated_at`, dan bila aman fingerprint; tidak pernah memberi secret asli.

Password user disimpan sebagai hash adaptif. PPPoE password yang perlu dikirim ke perangkat/RADIUS disimpan terenkripsi, bukan di-hash, dengan permission dan audit lebih ketat.

## 4. Relasi inti

```text
Area 1--* POP 1--* ODP
  |        |        |
  +--------+--------+----* Customer *----1 Reseller(User)
                              |
                              +--* CustomerService *--1 Package
                                      |       |
                                      |       +--0..1 PPPoEAccount --* PPPoESession
                                      |       +--0..1 HotspotUser  --* HotspotSession
                                      |
                                      +--0..1 Router
                                      +--0..1 OntDevice --1 OltPort --1 OLT

Router 1--* RouterInterface
Router 1--* RouterMetric
OLT    1--* OltBoard 1--* OltPort 1--* OntDevice --* OntOpticalHistory
```

`customer_services` menjadi penghubung layanan pelanggan dengan paket dan endpoint jaringan. Ini menghindari semua detail akses ditaruh langsung pada `customers` serta memungkinkan lebih dari satu layanan per pelanggan.

## 5. Katalog tabel core

### Identity dan scope

| Tabel | Kolom penting | Constraint/index utama |
|---|---|---|
| `users` | public_id, name, email, phone, password, status, reseller_owner_id, last_login_at | unique email; index status/owner; soft delete |
| `roles` | name, code, description, is_system | unique code |
| `permissions` | name, code, module | unique code; index module |
| `role_user` | role_id, user_id | unique(role_id,user_id), FK cascade pivot |
| `permission_role` | permission_id, role_id | unique(permission_id,role_id), FK cascade pivot |
| `user_assignments` | user_id, assignable_type/id, role_context | unique assignment; index polymorphic target |
| `user_sessions` | user_id, session_id, ip, user_agent, last_activity_at, revoked_at | unique session_id; index user/last activity |

Jika paket RBAC menggunakan nama pivot berbeda, migration dan dokumentasi OpenAPI harus diselaraskan; kode permission tetap stabil, misalnya `routers.view`, `routers.manage`, `ont.factory_reset`.

### Topologi dan pelanggan

| Tabel | Kolom penting | Constraint/index utama |
|---|---|---|
| `areas` | code, name, status | unique code; soft delete |
| `pops` | area_id, code, name, address, latitude, longitude | unique(area_id,code); index area/status |
| `odps` | pop_id, code, name, latitude, longitude, capacity | unique(pop_id,code); index pop |
| `customers` | public_id, customer_number, name, phone, email, addresses, coordinate, area/pop/odp, reseller_id, status, install/due date, notes | unique customer_number; index status/area/pop/reseller/package lookup; soft delete |
| `packages` | code, name, down/up bps, burst fields, priority, price, mikrotik_profile, radius_group, validity_days, status | unique code; index status; soft delete |
| `customer_services` | customer_id, package_id, router_id, ont_device_id, service_type, status, static_ip, activated/suspended_at | unique active service rule at application layer; index customer/status/router/package |
| `customer_histories` | customer_id, event_type, before/after sanitized, actor_id, correlation_id | index customer/created/event; immutable |
| `package_change_plans` | package_id, scope/filter, preview_hash, expires_at, status, requested_by | index status/expires; supports preview/apply |

Invoice/payment/ticket belum termasuk tabel minimum spesifikasi, tetapi dibutuhkan untuk peran Finance/Teknisi secara penuh. Tambahkan sebagai modul terpisah ketika requirement bisnisnya disepakati; jangan memalsukan fitur hanya dengan menu.

### Router, PPPoE, dan Hotspot

| Tabel | Kolom penting | Constraint/index utama |
|---|---|---|
| `routers` | public_id, name, host, api/api_ssl port, username ciphertext password, TLS mode/fingerprint, location, area_id, model/serial/version, status, last_seen, maintenance, notes | unique(host,port); index area/status/last_seen; soft delete |
| `router_metrics` | router_id, observed_at, cpu, memory/storage totals/free, uptime, temperature, raw sanitized | index(router_id,observed_at); retention/partition candidate |
| `router_interfaces` | router_id, external_id/name, type, mac, running/disabled, last rx/tx, observed_at | unique(router_id,external_id); index router/status |
| `router_interface_metrics` | interface_id, observed_at, rx/tx bps/bytes/errors | index(interface_id,observed_at); retention candidate |
| `pppoe_accounts` | customer_service_id, router_id, username, password ciphertext, profile, address, caller binding, auth_source, disabled, sync hashes/timestamps | unique(router_id,username) untuk local; index username/customer/status; soft delete |
| `pppoe_sessions` | account_id, router_id, external_session_id, caller_id, addresses, started/ended, terminate cause, counters, status | unique(router_id,external_session_id); index account/status/started |
| `hotspot_servers` | router_id, external_id, name, interface, address_pool, status | unique(router_id,external_id) |
| `hotspot_profiles` | server_id, name, rate/uptime/session/cookie settings | unique(server_id,name) |
| `hotspot_users` | customer_service_id nullable, server/profile, username, password ciphertext, MAC/IP binding, validity, disabled | unique(server_id,username); index status/valid_until; soft delete |
| `hotspot_sessions` | user_id, router_id, external_id, MAC/IP, login/logout, counters, status | unique(router_id,external_id); index user/status/login |
| `voucher_batches` | profile_id, prefix, quantity, template, created_by, status | index status/created |

### RADIUS dan reconciliation

| Tabel | Kolom penting | Constraint/index utama |
|---|---|---|
| `radius_nas` | core representation/NAS mapping, name, IP, type, secret ciphertext, active, last_test | unique IP; soft delete |
| `radius_sync_logs` | entity type/id, direction, desired/actual hash, diff sanitized, status, error, correlation_id | index entity/status/created |
| `reconciliation_runs` | source set, filter, counts, status, started/finished | index status/created |
| `reconciliation_items` | run_id, entity ref, state, diff sanitized, selected_resolution | index run/state/entity |

Tabel standar `rad*` tetap berada di database `radius`; lihat bagian 6.

### GenieACS dan OLT

| Tabel | Kolom penting | Constraint/index utama |
|---|---|---|
| `genieacs_devices` | acs_device_id, serial, manufacturer, product_class, software, WAN IP, last_inform, online status, selected mapping | unique acs_device_id; index serial/status/last inform |
| `genieacs_tasks` | device_id, acs_task_id, action, parameters sanitized, status/fault, requested_by, timestamps | unique acs_task_id; index device/status/created |
| `genieacs_parameter_mappings` | vendor, product_class pattern, software pattern, semantic key, TR-069 path, transform, priority, active | unique selector+semantic key+priority; index active/vendor |
| `olts` | public_id, vendor/model, host/port/protocol, username/password ciphertext, SNMP version/credential ciphertext, location, status/last seen, adapter, maintenance | unique(host,port,protocol); index vendor/status; soft delete |
| `olt_boards` | olt_id, slot/external_id, type, serial, status, observed_at | unique(olt_id,external_id) |
| `olt_ports` | board_id, external_id, pon index, admin/oper status, capacity, observed_at | unique(board_id,external_id) |
| `ont_devices` | olt_port_id, onu_id, serial, description, status/reason, distance, profile/VLAN/service port, uptime, observed_at | unique(olt_port_id,onu_id), unique per OLT serial where reliable; index status/serial |
| `ont_optical_histories` | ont_id, observed_at, rx_dbm, tx_dbm, temperature/voltage/bias | index(ont_id,observed_at); retention candidate |

### VPN, alert, audit, dan operasi

| Tabel | Kolom penting | Constraint/index utama |
|---|---|---|
| `vpn_servers` | type, name, endpoint, interface/identity, subnet, public metadata, encrypted server secret refs, status | unique type/name; soft delete |
| `vpn_clients` | server_id, user/customer nullable, name, public key/identity, private/PSK ciphertext, assigned_ip, expires, enabled, handshake/counters | unique(server_id,name), unique(server_id,assigned_ip); index enabled/expires |
| `alert_rules` | type, scope/filter, threshold, duration, severity, channels, cooldown, active | index active/type |
| `alerts` | rule_id, source type/id, fingerprint, severity, state, first/last seen, acknowledged/resolved, context sanitized | unique active fingerprint enforced by service; index state/severity/last_seen |
| `telegram_settings` | name, bot token ciphertext, chat_id, event types, quiet hours/timezone, active | index active; soft delete |
| `audit_logs` | actor_id nullable, occurred_at, IP/user-agent, module/action, target type/id, before/after sanitized, success, error code/message, correlation_id | index actor/time, target/time, module/action/time, correlation; immutable |
| `device_commands` | device type/id, action, request/result sanitized, status, attempt, actor, correlation/idempotency, timestamps | unique idempotency scope; index device/status/created |
| `jobs_history` | job UUID/type/queue, target, status, attempts, timing, sanitized error, correlation | unique job UUID; index status/queue/created |
| `system_settings` | namespaced key, typed value/ciphertext, public flag, lock_version | unique key |
| `backups` | type, location reference, encrypted flag, checksum, size, status, retention class, started/finished, verified_at, error sanitized | index status/type/created/retention |

## 6. Database FreeRADIUS

Gunakan schema resmi paket FreeRADIUS untuk versi yang dipasang, bukan migration buatan yang menghilangkan index penting:

| Tabel | Fungsi |
|---|---|
| `radcheck`, `radreply` | check/reply per user |
| `radgroupcheck`, `radgroupreply` | policy dan reply per group |
| `radusergroup` | urutan membership user-group |
| `radacct` | accounting session dan usage |
| `radpostauth` | hasil autentikasi accept/reject |
| `nas` | daftar NAS dan shared secret |

Shared secret pada `nas` adalah kebutuhan runtime FreeRADIUS dan schema upstream umumnya berupa plaintext. Mitigasinya: database/user khusus, least privilege, host encryption/permission, jaringan lokal, backup terenkripsi, larangan menampilkan/log, dan rotasi. Core application tetap menyimpan secret melalui secret service dan menyinkronkannya secara terkontrol; jangan menduplikasi secret ke audit.

Index `radacct` untuk query aktif (`acctstoptime`) dan periode usage harus diverifikasi terhadap schema upstream dan beban nyata. Data accounting bervolume tinggi memerlukan retention/archive yang disepakati secara bisnis.

## 7. Integritas dan aksi delete

- Master yang masih direferensikan tidak boleh hard-delete; gunakan deactivate/soft delete.
- Foreign key transaksi/history umumnya `RESTRICT` atau `SET NULL` untuk actor opsional, bukan cascade yang menghapus bukti.
- Pivot murni boleh `CASCADE` dari parent.
- Router/OLT/customer yang dinonaktifkan menghentikan job baru, tetapi history, audit, dan command tetap ada.
- Perubahan credential adalah operasi rotate, bukan update biasa, dan selalu membuat audit tanpa nilai secret.

## 8. Migration dan seeding

Urutan awal: identity/RBAC -> topology -> router/customer/package -> access accounts -> monitoring -> integrations -> alert/audit/system. Migration harus reversible hanya jika rollback aman; migration destruktif membutuhkan backup, preflight, dan strategi expand/migrate/contract.

Seeder demo yang direncanakan: 2 router mock, 2 OLT mock, 20 pelanggan, 5 paket, kombinasi PPPoE/Hotspot/ONT, alert, audit, dan VPN client. Credential wajib dummy dan seeder demo dilarang pada production kecuali flag eksplisit.

## 9. Retention awal yang perlu disahkan

Baseline untuk diskusi, bukan default yang sudah aktif:

- Interface/resource raw metric: 30-90 hari, kemudian agregasi.
- Optical history: 90-180 hari sesuai kebutuhan troubleshooting.
- Job/command technical history: 90 hari atau lebih sesuai kebijakan audit.
- `radacct`, audit, login, dan backup metadata: ditentukan oleh kebutuhan legal/bisnis; tidak dihapus diam-diam.
- Backup harian/mingguan/bulanan mengikuti kebijakan terpisah dan harus diuji restore.

